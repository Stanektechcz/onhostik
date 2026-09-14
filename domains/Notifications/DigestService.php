<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Carbon\CarbonImmutable;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Platform\Money\Money;

/**
 * Digests (audit §5e-5): one weekly summary per customer organization — what renews and expires in the next two weeks,
 * what the credit covers, how the backups and monitors did, what the plans are using up — and one daily summary for
 * staff — what is stuck, what failed, who is behind on payment, which nodes and integrations need a look. Both are
 * assembled from the records the platform already keeps; nothing is measured anew. The customer digest is a
 * preference kind (`digest`) the customer can switch off; the staff digest goes to the addresses in
 * `onhost.notifications.staff_digest_to` and to the internal feed.
 */
final class DigestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CalendarFeed $calendar,
        private readonly WalletForecast $forecast,
        private readonly OperationsBoard $board,
    ) {}

    public const FREQUENCIES = ['weekly', 'monthly', 'off'];

    /** The organization's digest frequency (audit §5f-5): weekly (default), monthly (the first digest run of the month) or off. */
    public static function frequency(Organization $organization): string
    {
        $value = (string) data_get($organization->settings, 'digest.frequency', 'weekly');

        return in_array($value, self::FREQUENCIES, true) ? $value : 'weekly';
    }

    /** Every organization with at least one service: a notification and a mail per organization. @return array{organizations:int, sent:int, skipped:int} */
    public function weekly(): array
    {
        $stats = ['organizations' => 0, 'sent' => 0, 'skipped' => 0];
        $ids = Service::query()->whereNotIn('state', [ServiceStateMachine::TERMINATED])->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $ids)->orderBy('created_at')->get() as $organization) {
            $stats['organizations']++;
            $frequency = self::frequency($organization);
            if ($frequency === 'off' || ($frequency === 'monthly' && CarbonImmutable::now()->day > 7)) { // monthly: the first weekly run of the month
                $stats['skipped']++;

                continue;
            }
            $digest = $this->customerWeekly($organization);
            if ($digest === null) {
                continue;
            }
            $this->notifications->notify('customer', 'digest', $digest['title'], $digest['body'], '/panel', $organization->id, null, 'organization', $organization->id, 'digest.weekly', 'info');
            if ($organization->billing_email) {
                $this->notifications->queueMail('digest-weekly', (string) $organization->billing_email, ['organizace' => (string) $organization->name, 'shrnuti' => $digest['text'], 'url' => rtrim((string) config('app.url'), '/').'/panel'], 'organization', $organization->id, $organization->id);
            }
            $stats['sent']++;
        }

        return $stats;
    }

    /** @return array{title:string, body:string, text:string, sections:array<string,list<string>>}|null */
    public function customerWeekly(Organization $organization): ?array
    {
        $now = CarbonImmutable::now();
        $services = Service::query()->where('organization_id', $organization->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get();
        if ($services->isEmpty()) {
            return null;
        }
        $currency = (string) $organization->currency;
        $en = ($organization->locale ?? 'cs') === 'en'; // EN accounts read the digest in English (audit §5f-5)
        $t = fn (string $cs, string $enText) => $en ? $enText : $cs;
        $sections = [];

        $byState = $services->groupBy('state')->map->count();
        $usage = $services->filter(fn (Service $s) => in_array(data_get($s->tags, 'usage.level'), ['warn', 'critical'], true));
        $sections[$t('Služby', 'Services')] = array_merge(
            [$byState->map(fn ($n, $s) => ServiceStateMachine::machine()->label($s)." {$n}")->implode(', ')],
            $usage->map(fn (Service $s) => ($s->label ?: $s->hostname ?: $s->name).': '.(data_get($s->tags, 'usage.level') === 'critical' ? $t('kapacita téměř vyčerpaná', 'capacity nearly used up') : $t('blíží se limitu tarifu', 'near the plan limit')))->values()->all(),
        );

        $events = $this->calendar->events($organization, null, 14);
        $upcoming = [];
        foreach ($events as $event) {
            $upcoming[] = substr((string) $event['starts_at'], 0, 10).' · '.$event['title'].(isset($event['amount']) && $event['amount'] instanceof Money ? ' · '.$event['amount']->format() : '');
        }
        $sections[$t('Příštích 14 dní', 'Next 14 days')] = $upcoming === [] ? [$t('žádné obnovy, expirace ani splatnosti', 'no renewals, expiries or due dates')] : array_slice($upcoming, 0, 12);

        $forecast = $this->forecast->forecast($organization, $currency);
        $sections[$t('Kredit', 'Credit')] = [$t('k dispozici ', 'available ').$forecast['available']->format().$t(' · měsíční útrata ', ' · monthly spend ').$forecast['monthly_burn']->format().($forecast['days'] !== null ? $t(' · vystačí ještě ', ' · lasts another ').$forecast['days'].$t(' dní', ' days') : $t(' · obnovy pokryté', ' · renewals covered'))];

        $week = $now->subDays(7);
        $backups = Backup::query()->where('organization_id', $organization->id)->where('created_at', '>=', $week)->get();
        $sections[$t('Zálohy (7 dní)', 'Backups (7 days)')] = [$backups->count().$t(' záloh · ', ' backups · ').$backups->whereNotNull('verified_at')->count().$t(' ověřených', ' verified').($backups->where('state', 'failed')->count() ? ' · '.$backups->where('state', 'failed')->count().$t(' selhalo', ' failed') : '')];

        $monitors = UptimeMonitor::query()->where('organization_id', $organization->id)->get();
        if ($monitors->isNotEmpty()) {
            $down = $monitors->where('state', 'down')->count();
            $sections['Monitoring'] = [$monitors->count().$t(' hlídaných adres', ' monitored addresses').($down ? " · {$down}".$t(' právě nedostupné', ' down right now') : $t(' · vše dostupné', ' · all up'))];
        }

        $tickets = Ticket::query()->where('organization_id', $organization->id)->where('updated_at', '>=', $week)->count();
        if ($tickets > 0) {
            $sections[$t('Podpora', 'Support')] = ["{$tickets}".$t(' tiketů s aktivitou za týden', ' tickets with activity this week')];
        }

        $text = '';
        foreach ($sections as $heading => $lines) {
            $text .= mb_strtoupper($heading)."\n".implode("\n", array_map(fn ($l) => '· '.$l, $lines))."\n\n";
        }
        $body = implode(' · ', array_map(fn ($lines) => $lines[0] ?? '', $sections));

        return ['title' => $t('Týdenní přehled ', 'Weekly summary ').$now->format('j. n.'), 'body' => mb_substr($body, 0, 900), 'text' => trim($text), 'sections' => $sections, 'frequency' => self::frequency($organization)];
    }

    /** Daily staff summary: the internal feed plus the configured addresses. @return array{title:string, lines:list<string>, recipients:int} */
    public function staffDaily(): array
    {
        $now = CarbonImmutable::now();
        $day = $now->subDay();
        $counts = $this->board->board()['counts'];
        $lines = [
            'Operace: '.$counts['stalled'].' čeká na uzel · '.$counts['failed_24h'].' selhalo za 24 h · '.$counts['long_running'].' běží dlouho · '.$counts['draining'].' odstavených uzlů',
            'Integrace: '.IntegrationHealth::query()->where('up', false)->count().' nedostupných'.(Node::query()->where('state', 'draining')->count() ? ' · uzly v odstavení: '.Node::query()->where('state', 'draining')->pluck('name')->implode(', ') : ''),
            'Zálohy: '.Backup::query()->where('created_at', '>=', $day)->count().' za 24 h · '.Backup::query()->where('created_at', '>=', $day)->where('state', 'failed')->count().' selhalo',
            'Obchod: '.Order::query()->where('placed_at', '>=', $day)->count().' objednávek za 24 h · '.Subscription::query()->whereBetween('next_renewal_at', [$now, $now->addDays(7)])->count().' obnov do týdne',
            'Pohledávky: '.DunningCase::query()->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->count().' otevřených upomínek',
            'Kapacita tarifů: '.Service::query()->where('tags->usage->level', 'critical')->count().' služeb u limitu · '.Service::query()->where('tags->usage->level', 'warn')->count().' se blíží',
            'Podpora: '.Ticket::query()->where('created_at', '>=', $day)->count().' nových tiketů za 24 h',
        ];
        $title = 'Denní provozní přehled '.$now->format('j. n.');
        $this->notifications->notify('internal', 'digest', $title, implode(' · ', $lines), '/sprava', null, null, null, null, 'digest.staff', 'info');
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string) config('onhost.notifications.staff_digest_to', ''))), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false));
        foreach ($recipients as $to) {
            $this->notifications->queueMail('digest-staff', $to, ['datum' => $now->format('j. n. Y'), 'shrnuti' => implode("\n", array_map(fn ($l) => '· '.$l, $lines)), 'url' => rtrim((string) config('app.url'), '/').'/sprava/nastaveni/provoz'], null, null, null);
        }

        return ['title' => $title, 'lines' => $lines, 'recipients' => count($recipients)];
    }
}
