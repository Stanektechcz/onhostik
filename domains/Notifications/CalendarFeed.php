<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/**
 * The customer's dates as a calendar: renewals with their gross price, domain expiries, invoice due dates, planned
 * maintenance touching their services and the day the credit stops covering renewals. Served as JSON to the panel and
 * as a signed ICS feed any calendar subscribes to; the signature carries a version the customer rotates to revoke a link.
 */
final class CalendarFeed
{
    public const HORIZON_DAYS = 180;

    public function __construct(private readonly WalletForecast $forecast, private readonly AuditRecorder $audit) {}

    /** @return list<array<string,mixed>> upcoming events sorted by date */
    public function events(Organization $organization, ?string $projectId = null, int $days = self::HORIZON_DAYS): array
    {
        $from = CarbonImmutable::now()->startOfDay();
        $to = CarbonImmutable::now()->addDays(max(1, min(365, $days)))->endOfDay();
        $services = Service::query()->where('organization_id', $organization->id)->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))->get(['id', 'label', 'name', 'hostname', 'project_id'])->keyBy('id');
        $events = [];

        $subscriptions = Subscription::query()->where('organization_id', $organization->id)->where('state', 'active')->whereBetween('next_renewal_at', [$from, $to])->orderBy('next_renewal_at')->get();
        foreach ($subscriptions as $subscription) {
            if ($subscription->service_id !== null ? ! $services->has($subscription->service_id) : $projectId !== null) {
                continue; // another project, or a service no longer listed
            }
            $service = $subscription->service_id !== null ? $services->get($subscription->service_id) : null;
            $label = (string) ($service?->label ?: $service?->hostname ?: $service?->name ?: 'předplatné');
            $gross = $this->forecast->gross($organization, $subscription);
            $events[] = $this->event('renewal', $subscription->id, $subscription->next_renewal_at, null, true, "Obnova: {$label}",
                'Obnova služby '.$label.' za '.$this->money($gross).($subscription->auto_renew ? ' z kreditu.' : '. Automatická obnova je vypnutá.'), ['amount' => $gross, 'service_id' => $subscription->service_id]);
        }

        $domains = Domain::query()->where('organization_id', $organization->id)->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->whereIn('state', [DomainStateMachine::ACTIVE, DomainStateMachine::EXPIRED, DomainStateMachine::GRACE])->whereBetween('expires_at', [$from->subDays(30), $to])->orderBy('expires_at')->get();
        foreach ($domains as $domain) {
            $events[] = $this->event('domain_expiry', $domain->id, $domain->expires_at, null, true, "Expirace domény {$domain->fqdn_unicode}",
                data_get($domain->meta, 'source') === 'connection' ? 'Registrována u připojeného účtu ('.(string) data_get($domain->meta, 'registrar_account', 'WEDOS').'); prodlužte ji tam, nebo ji převeďte k nám.' : ($domain->auto_renew ? 'Doména se obnoví automaticky z kreditu před expirací.' : 'Automatická obnova je vypnutá, doménu prodlužte ručně.'), ['domain' => $domain->fqdn_unicode]);
        }

        if ($projectId === null) {
            $invoices = Invoice::query()->where('organization_id', $organization->id)->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->whereIn('type', ['invoice', 'proforma'])
                ->whereNotNull('due_at')->whereBetween('due_at', [$from->subDays(60), $to])->orderBy('due_at')->get();
            foreach ($invoices as $invoice) {
                $total = Money::minor((int) $invoice->total_minor, (string) $invoice->currency);
                $events[] = $this->event('invoice_due', $invoice->id, $invoice->due_at, null, true, 'Splatnost dokladu '.$invoice->number,
                    ($invoice->type === 'proforma' ? 'Zálohová faktura ' : 'Faktura ').$invoice->number.' na '.$this->money($total).($invoice->state === Invoice::OVERDUE ? ' je po splatnosti.' : '.'), ['amount' => $total, 'number' => $invoice->number]);
            }

            $forecast = $this->forecast->forecast($organization, (string) $organization->currency);
            if ($forecast['depletes_at'] !== null && CarbonImmutable::parse($forecast['depletes_at'])->lessThanOrEqualTo($to)) {
                $events[] = $this->event('credit_depletion', $organization->id, CarbonImmutable::parse($forecast['depletes_at']), null, true, 'Kredit přestane stačit na obnovy',
                    'Podle nadcházejících obnov kredit vystačí do tohoto dne; na dalších 30 dní chybí '.$this->money($forecast['shortfall_30d']).'.', ['amount' => $forecast['shortfall_30d']]);
            }
        }

        $maintenances = Maintenance::query()->whereIn('state', ['planned', 'approved', 'in_progress'])->where('ends_at', '>=', CarbonImmutable::now())->where('starts_at', '<=', $to)->orderBy('starts_at')->get();
        foreach ($maintenances as $maintenance) {
            $affected = array_values(array_filter((array) ($maintenance->affected_services ?? []), 'is_string'));
            if ($affected !== [] && array_intersect($affected, $services->keys()->all()) === []) {
                continue; // touches other customers' services only
            }
            $events[] = $this->event('maintenance', $maintenance->id, $maintenance->starts_at, $maintenance->ends_at, false, 'Údržba: '.$maintenance->title,
                trim((string) ($maintenance->impact ?? 'Plánovaná údržba infrastruktury.')), ['number' => $maintenance->number, 'components' => (array) $maintenance->components]);
        }

        usort($events, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']) ?: strcmp($a['kind'], $b['kind']));

        return $events;
    }

    /** @param  list<array<string,mixed>>  $events */
    public function ics(Organization $organization, array $events): string
    {
        $stamp = CarbonImmutable::now()->utc()->format('Ymd\THis\Z');
        $portal = rtrim((string) config('onhost.portal_url', config('app.url')), '/');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ONhost//Calendar 1.0//CS', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->text('ONhost · '.$organization->name), 'X-WR-TIMEZONE:Europe/Prague', 'REFRESH-INTERVAL;VALUE=DURATION:PT6H', 'X-PUBLISHED-TTL:PT6H',
        ];
        foreach ($events as $event) {
            $start = CarbonImmutable::parse($event['starts_at']);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$event['uid'];
            $lines[] = 'DTSTAMP:'.$stamp;
            if ($event['all_day']) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.$start->addDay()->format('Ymd');
            } else {
                $end = $event['ends_at'] !== null ? CarbonImmutable::parse($event['ends_at']) : $start->addHour();
                $lines[] = 'DTSTART:'.$start->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.$end->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.$this->text($event['title']);
            $lines[] = 'DESCRIPTION:'.$this->text($event['description']);
            $lines[] = 'CATEGORIES:'.strtoupper(str_replace('_', '-', $event['kind']));
            $lines[] = 'URL:'.$portal.'/panel';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line) => $this->fold($line), $lines))."\r\n";
    }

    /** @return array{url:string, version:int} */
    public function feedUrl(Organization $organization, ?string $projectId = null): array
    {
        $version = $this->version($organization);
        $params = ['organization' => $organization->id, 'v' => $version] + ($projectId !== null ? ['project' => $projectId] : []);

        return ['url' => URL::signedRoute('calendar.feed', $params), 'version' => $version];
    }

    /** A new version invalidates every feed link handed out so far. @return array{url:string, version:int} */
    public function rotate(Organization $organization, CommandContext $context): array
    {
        $version = $this->version($organization) + 1;
        $organization->forceFill(['settings' => array_merge((array) $organization->settings, ['calendar_feed_version' => $version])])->save();
        $this->audit->record($context->withScope($organization->id), 'calendar.feed.rotate', 'succeeded', ['version' => $version], 'organization', $organization->id);

        return $this->feedUrl($organization);
    }

    public function version(Organization $organization): int
    {
        return max(1, (int) data_get($organization->settings, 'calendar_feed_version', 1));
    }

    /** @return array<string,mixed> */
    private function event(string $kind, string $id, \DateTimeInterface $start, ?\DateTimeInterface $end, bool $allDay, string $title, string $description, array $extra = []): array
    {
        return [
            'uid' => "{$kind}-{$id}@onhost", 'kind' => $kind, 'id' => $id, 'title' => $title, 'description' => $description,
            'starts_at' => CarbonImmutable::instance($start)->toIso8601String(), 'ends_at' => $end === null ? null : CarbonImmutable::instance($end)->toIso8601String(), 'all_day' => $allDay,
        ] + $extra;
    }

    private function money(Money $money): string
    {
        return number_format($money->minor / 100, 2, ',', ' ').' '.$money->currency->value;
    }

    private function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** RFC 5545 line folding: at most 75 octets per line, continuation lines start with a space, multibyte characters never split. */
    private function fold(string $line): string
    {
        $out = [];
        $first = true;
        while ($line !== '') {
            $chunk = mb_strcut($line, 0, $first ? 75 : 74, 'UTF-8');
            $out[] = ($first ? '' : ' ').$chunk;
            $line = substr($line, strlen($chunk));
            $first = false;
        }

        return implode("\r\n", $out);
    }
}
