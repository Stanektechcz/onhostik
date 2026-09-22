<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * Domain event → customer/internal notification + mail (blueprint §72). The table
 * below is the single place that decides who hears about what; templates carry
 * the wording. Secrets never travel through here (the outbox is redacted).
 */
final class NotificationRouter
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        $p = (array) $m->payload;
        $org = $m->organization_id ? Organization::query()->find($m->organization_id) : null;
        $locale = $org?->locale ?? 'cs';
        $email = $org?->billing_email ?: ($org?->owner?->email ?? null);
        $portal = rtrim((string) config('onhost.portal_url'), '/');
        $number = (string) ($p['number'] ?? '');
        $until = substr((string) ($p['expires_at'] ?? ''), 0, 10);
        $money = fn ($v) => is_array($v) && isset($v['minor'], $v['currency']) ? Money::minor((int) $v['minor'], (string) $v['currency'])->format($locale) : (string) ($v ?? '');
        $orderNumber = $m->name === 'payment.succeeded' && ($p['reference'][0] ?? '') === 'order' ? (string) (Order::query()->find((string) ($p['reference'][1] ?? ''))?->number ?? '') : '';

        match ($m->name) {
            // intake pre-check (audit §5f-8): staff decide, the customer hears "we are checking" and then the outcome
            'order.review.required' => $this->both($m, 'order', "Objednávka {$p['number']} čeká na kontrolu (skóre ".($p['score'] ?? '?').')', ($org?->name ?? '').' · '.$money($p['total'] ?? null).' · '.implode(', ', (array) ($p['reasons'] ?? [])), "Objednávku {$p['number']} ještě kontrolujeme", 'Zaplacená objednávka prochází krátkou kontrolou; služby zřídíme hned po jejím dokončení, obvykle do pár hodin.', '/sprava/objednavky', '/panel/objednavky', 'warn'),
            'order.review.released' => $this->customer($m, 'order', "Objednávka {$p['number']} byla schválena", 'Služby se právě zřizují.', '/panel/objednavky'),
            'order.review.rejected' => $this->customer($m, 'order', "Objednávku {$p['number']} jsme nemohli přijmout", (string) ($p['reason'] ?? 'Platba byla vrácena, kredit uvolněn. Napište podpoře, pokud jde o omyl.'), '/panel/objednavky', 'warn'),
            'order.placed' => $this->both($m, 'order', "Nová objednávka {$p['number']}", ($org?->name ?? '').' · '.$money($p['total'] ?? null).' · '.($p['mode'] ?? ''), 'Objednávka přijata', "{$p['number']} · ".$money($p['total'] ?? null), '/sprava/objednavky', '/panel/objednavky', 'info', $email, 'order-received', ['cislo' => $p['number'], 'castka' => $money($p['total'] ?? null), 'jmeno' => $org?->name, 'url' => "{$portal}/panel/objednavky"]),
            'order.paid' => $this->customer($m, 'order', 'Objednávka zaplacena', "{$p['number']} · zřizujeme služby", '/panel/objednavky'),
            'order.active' => $this->customer($m, 'order', 'Objednávka je hotová', "{$p['number']} · všechny služby jsou aktivní", '/panel/sluzby'),
            'provisioning.stranded.released' => $this->internal($m, 'provisioning', 'Služby uvolněné z mezistavu: '.(int) ($p['count'] ?? 0), implode(', ', array_map(fn ($s) => (string) ($s['name'] ?? $s['id'] ?? '').' ('.(string) ($s['from'] ?? '').' → '.(string) ($s['to'] ?? '').')', (array) ($p['services'] ?? []))), '/sprava/provoz', 'warn'),
            // four eyes: staff hear that somebody needs a second person, and what became of it
            'iam.approval.requested' => $this->internal($m, 'security', 'Žádost o schválení: '.($p['action'] ?? ''), trim((string) ($p['requester'] ?? '').' · '.(string) ($p['reason'] ?? ''), ' ·'), '/sprava/nastaveni/schvalovani', 'warn'),
            'iam.approval.decided' => $this->internal($m, 'security', (($p['decision'] ?? '') === 'approved' ? 'Žádost schválena: ' : 'Žádost zamítnuta: ').($p['action'] ?? ''), trim((string) ($p['decider'] ?? '').' · '.(string) ($p['note'] ?? ''), ' ·'), '/sprava/nastaveni/schvalovani'),
            // one service shared with another person: the organization sees who was let in and when that ended
            'service.access.granted' => $this->customer($m, 'account', 'Služba sdílena: '.($p['service'] ?? ''), ($p['email'] ?? '').(($p['state'] ?? '') === 'pending' ? ' · čeká na přijetí pozvánky' : ' · přístup je aktivní').($until !== '' ? ' · do '.$until : ''), '/panel/sluzby'),
            'service.access.revoked' => $this->customer($m, 'account', 'Sdílení služby ukončeno: '.($p['service'] ?? ''), (string) ($p['email'] ?? ''), '/panel/sluzby'),
            'service.access.expired' => $this->customer($m, 'account', 'Sdílení služby vypršelo: '.($p['service'] ?? ''), (string) ($p['email'] ?? ''), '/panel/sluzby'),
            // what could not be delivered went back to the customer (OrderSettlement): they hear it from us, with the amount and the place
            'order.refunded' => $this->both($m, 'order', "Objednávka {$number}: vráceno ".$money($p['amount'] ?? null), implode(', ', (array) ($p['items'] ?? [])).' · '.(($p['to'] ?? 'credit') === 'invoice' ? 'dobropis k faktuře' : 'zpět na kredit'),
                ! empty($p['nothing_delivered']) ? "Objednávku {$number} se nepodařilo zřídit" : "Část objednávky {$number} se nepodařilo zřídit", $money($p['amount'] ?? null).(($p['to'] ?? 'credit') === 'invoice' ? ' jsme odečetli z faktury' : ' jsme vrátili na váš kredit').' · '.implode(', ', (array) ($p['items'] ?? [])),
                '/sprava/objednavky', '/panel/objednavky', 'warn', $email, 'order-refunded', ['cislo' => $number, 'castka' => $money($p['amount'] ?? null), 'polozky' => '- '.implode("\n- ", (array) ($p['items'] ?? [])), 'kam' => ($p['to'] ?? 'credit') === 'invoice' ? 'odečtením z faktury' : 'na váš kredit', 'doklad' => (string) ($p['credit_note'] ?? ''), 'url' => $portal.'/panel/objednavky']),
            // a paid order that was cancelled: the customer hears where the money is and which document corrects the first one
            'order.cancelled' => match (true) {
                isset($p['returned']) => $this->customer($m, 'order', "Objednávka {$number} byla zrušena", $money($p['returned']).(($p['to'] ?? 'credit') === 'invoice' ? ' jsme odečetli z faktury' : ' jsme vrátili na váš kredit').' · opravný doklad '.implode(', ', (array) ($p['credit_notes'] ?? [])),
                    '/panel/objednavky', 'warn', $email, 'order-cancelled', ['cislo' => $number, 'castka' => $money($p['returned']), 'kam' => ($p['to'] ?? 'credit') === 'invoice' ? 'odečtením z faktury' : 'na váš kredit', 'doklad' => implode(', ', (array) ($p['credit_notes'] ?? [])), 'url' => $portal.'/panel/objednavky']), // the staff note stays inside: it is for the audit, not for the customer
                ($p['from'] ?? '') === 'PENDING_PAYMENT' => $this->customer($m, 'order', "Objednávka {$number} byla zrušena", 'Objednávka nebyla zaplacena a už není platná; můžete zadat novou.', '/panel/objednavky', 'warn'),
                default => null,
            },
            'provisioning.compensation.kept' => $this->internal($m, 'provisioning', 'Po nezdařeném zřízení zůstal zdroj na panelu: '.($p['label'] ?? ''), ($p['remote_type'] ?? '').' '.($p['remote_id'] ?? '').((string) ($p['node'] ?? '') !== '' ? ' @ '.(string) ($p['node'] ?? '') : '').' · identitu se nepodařilo potvrdit ('.implode(', ', (array) ($p['failed'] ?? [])).') — nic nebylo smazáno, zkontrolujte ručně', '/sprava/provoz', 'warn'),
            'dunning.enforcement_failed' => $this->internal($m, 'dunning', 'Neplacená služba stále běží: '.($p['label'] ?? ''), (($p['step'] ?? 'suspend') === 'terminate' ? 'zrušení' : 'pozastavení').' se nedaří'.(isset($p['attempt']) ? ' · pokus '.$p['attempt'] : '').((string) ($p['error'] ?? '') !== '' ? ' · '.(string) ($p['error'] ?? '') : ' · operace zadána znovu'), '/sprava/fakturace', 'hot'),
            'order.settlement_failed' => $this->internal($m, 'order', "Objednávka {$number}: zřízeno, ale nezaplaceno", $money($p['amount'] ?? null).' · '.($p['reason'] ?? ''), '/sprava/objednavky', 'hot'),
            'order.partially_active', 'order.failed', 'order.fulfilment_failed' => $this->internal($m, 'order', "Objednávka {$p['number']}: problém při zřizování", (string) ($p['reason'] ?? ($p['note'] ?? '')), '/sprava/objednavky', 'hot'),
            // ── web toolkit: monitoring, deploy, staging, import, certificates, CDN ──
            'monitoring.down' => $this->customer($m, 'service', 'Web neodpovídá', ($p['url'] ?? '').' · '.($p['error'] ?? ''), '/panel/sluzby', 'hot', ! empty($p['notify']) ? $email : null, 'site-down', ['web' => $p['url'] ?? '', 'chyba' => $p['error'] ?? '', 'url' => $portal.'/panel/sluzby']),
            'monitoring.up' => $this->customer($m, 'service', 'Web opět běží', ($p['url'] ?? '').' · výpadek '.(int) ($p['minutes'] ?? 0).' min', '/panel/sluzby', 'info', ! empty($p['notify']) ? $email : null, 'site-up', ['web' => $p['url'] ?? '', 'trvani' => (int) ($p['minutes'] ?? 0).' min', 'url' => $portal.'/panel/sluzby']),
            'deploy.succeeded' => $this->customer($m, 'service', 'Deploy dokončen', ($p['ref'] ?? '').' · '.substr((string) ($p['sha'] ?? ''), 0, 7), '/panel/sluzby'),
            'deploy.failed' => $this->customer($m, 'service', 'Deploy selhal', (string) ($p['error'] ?? ''), '/panel/sluzby', 'warn', $email, 'deploy-failed', ['ref' => $p['ref'] ?? '', 'chyba' => $p['error'] ?? '', 'url' => $portal.'/panel/sluzby']),
            'staging.synced', 'staging.pushed' => $this->customer($m, 'service', $m->name === 'staging.pushed' ? 'Staging přenesen do produkce' : 'Staging obnoven z produkce', (string) ($p['domain'] ?? ''), '/panel/sluzby'),
            'staging.failed' => $this->customer($m, 'service', 'Staging: operace selhala', (string) ($p['error'] ?? ''), '/panel/sluzby', 'warn'),
            'import.succeeded' => $this->customer($m, 'service', 'Import webu dokončen', (int) ($p['stats']['files'] ?? 0).' souborů · '.(int) ($p['stats']['databases'] ?? 0).' databází', '/panel/sluzby', 'info', $email, 'import-finished', ['stav' => 'dokončen', 'souhrn' => (int) ($p['stats']['files'] ?? 0).' souborů, '.(int) ($p['stats']['databases'] ?? 0).' databází', 'url' => $portal.'/panel/sluzby']),
            'import.failed' => $this->customer($m, 'service', 'Import webu selhal', (string) ($p['error'] ?? ''), '/panel/sluzby', 'warn', $email, 'import-finished', ['stav' => 'selhal', 'souhrn' => (string) ($p['error'] ?? ''), 'url' => $portal.'/panel/sluzby']),
            'integration.discord.linked' => $this->customer($m, 'account', 'Discord účet propojen', ($p['discord_username'] ?? '').' může přes /onhost zobrazit stav služeb a spouštět zálohy, restarty a deploy (po potvrzení)', '/panel/api'),
            'certificate.issued' => $this->customer($m, 'service', 'Certifikát vystaven', implode(', ', (array) ($p['domains'] ?? [])).' · platí do '.substr((string) ($p['expires_at'] ?? ''), 0, 10), '/panel/sluzby'),
            'certificate.failed' => $this->both($m, 'service', 'Certifikát se nepodařilo vystavit', implode(', ', (array) ($p['domains'] ?? [])).' · '.($p['error'] ?? ''), 'Certifikát se nepodařilo vystavit', (string) ($p['error'] ?? ''), '/sprava/provisioning', '/panel/sluzby', 'warn', $email, 'certificate-failed', ['domena' => (string) (($p['domains'] ?? [])[0] ?? ''), 'chyba' => $p['error'] ?? '', 'url' => $portal.'/panel/sluzby']),
            'cdn.enabled', 'cdn.activated', 'cdn.disabled' => $this->customer($m, 'service', match ($m->name) {
                'cdn.enabled' => 'CDN zapnuto', 'cdn.activated' => 'CDN je aktivní', default => 'CDN vypnuto'
            }, (string) ($p['domain'] ?? '').($m->name === 'cdn.enabled' && empty($p['nameservers_switched']) ? ' · nastavte nameservery u registrátora' : ''), '/panel/sluzby'),
            'service.activated' => $this->customer($m, 'service', 'Služba je aktivní', $this->access($p), '/panel/sluzby', 'info', $email, 'service-activated', ['sluzba' => $p['product_key'] ?? '', 'pristup' => $this->access($p), 'url' => "{$portal}/panel/sluzby"]),
            'service.failed' => $this->both($m, 'service', 'Zřizování služby selhalo', (string) ($p['reason'] ?? ''), 'Zřizování služby se nezdařilo — řešíme', 'Naši technici byli upozorněni, ozveme se v tiketu.', '/sprava/sluzby', '/panel/sluzby', 'hot'),
            'service.plan_changed' => ! empty($p['period_change']) && ($p['from_plan'] ?? null) === ($p['to_plan'] ?? null)
                // the same plan billed per the other period: a new period started now, the unused rest of the old one was credited
                ? $this->customer($m, 'service', 'Období platby služby '.($p['label'] ?: ($p['hostname'] ?? '')).' změněno na '.(($p['period'] ?? '') === 'year' ? 'roční' : 'měsíční'), 'Nové období běží od teď'.(! empty($p['current_period_end']) ? ' do '.date('j. n. Y', strtotime((string) $p['current_period_end'])) : '').'; nevyužitý zbytek původního období jsme odečetli.', '/panel/sluzby', 'info', $email, 'service-period-changed', ['sluzba' => (string) ($p['label'] ?: ($p['hostname'] ?? '')), 'obdobi' => ($p['period'] ?? '') === 'year' ? 'roční' : 'měsíční', 'konec' => ! empty($p['current_period_end']) ? date('j. n. Y', strtotime((string) $p['current_period_end'])) : '—', 'url' => "{$portal}/panel/sluzby"])
                : $this->customer($m, 'service', 'Tarif služby '.($p['label'] ?: ($p['hostname'] ?? '')).' změněn na '.($p['plan_name'] ?? $p['to_plan'] ?? ''), 'Nové limity platí do minuty; nová cena se účtuje od '.(! empty($p['period_change']) ? 'teď (nové období začalo dnes)' : 'příštího období').'.', '/panel/sluzby', 'info', $email, 'service-plan-changed', ['sluzba' => (string) ($p['label'] ?: ($p['hostname'] ?? '')), 'tarif' => (string) ($p['plan_name'] ?? $p['to_plan'] ?? ''), 'url' => "{$portal}/panel/sluzby"]),
            // usage watch (audit §5e-1): the plan is nearly used up — the next plan and today's price, or the automatic upgrade that was ordered
            'service.usage.high' => (function () use ($m, $p, $email, $portal) {
                $name = (string) ($p['label'] ?: ($p['hostname'] ?? ''));
                $top = (array) ($p['top'] ?? []);
                $metric = UsageWatch::metricLabel((string) ($top['key'] ?? 'disk'));
                $pct = (int) ($top['pct'] ?? 0);
                $upgrade = is_array($p['upgrade'] ?? null) ? $p['upgrade'] : null;
                $price = $upgrade !== null && is_array($upgrade['price'] ?? null) ? Money::minor((int) ($upgrade['price']['minor'] ?? 0), (string) ($upgrade['price']['currency'] ?? 'CZK'))->format() : null;
                $offer = $upgrade === null ? 'Vyšší tarif pro tuto službu nenabízíme; napište podpoře.' : "Vyšší tarif {$upgrade['name']} stojí {$price} / ".(($upgrade['period'] ?? 'month') === 'year' ? 'rok' : 'měsíc').' a přepnete ho jedním klikem v panelu (doplatek jen za zbytek období).';
                $order = is_array($p['order'] ?? null) ? $p['order'] : null;
                if ($order !== null) {
                    $this->customer($m, 'service', "Tarif služby {$name} automaticky navýšen na {$upgrade['name']}", "Služba využívala {$pct} % ({$metric}); podle vaší volby jsme objednali vyšší tarif (objednávka {$order['number']}) a uhradili ho z kreditu.", '/panel/sluzby', 'info', $email, 'service-usage-high', ['sluzba' => $name, 'metrika' => $metric, 'procenta' => (string) $pct, 'nabidka' => "Vyšší tarif {$upgrade['name']} byl objednán automaticky (objednávka {$order['number']}) a uhrazen z kreditu.", 'url' => "{$portal}/panel/sluzby"]);

                    return;
                }
                $this->customer($m, 'service', "Služba {$name} využívá {$pct} % ({$metric})", ($p['level'] ?? 'warn') === 'critical' ? "Kapacita je téměř vyčerpaná. {$offer}" : "Blížíte se limitu tarifu. {$offer}", '/panel/sluzby', ($p['level'] ?? 'warn') === 'critical' ? 'hot' : 'warn', $email, 'service-usage-high', ['sluzba' => $name, 'metrika' => $metric, 'procenta' => (string) $pct, 'nabidka' => $offer, 'url' => "{$portal}/panel/sluzby"]);
            })(),
            'service.migration.scheduled' => $this->customer($m, 'service', 'Stěhování serveru '.($p['label'] ?? '').' je naplánované', 'Začne '.self::when($p['starts_at'] ?? null).'; termín můžete posunout v okně '.self::when($p['from'] ?? null).' – '.self::when($p['to'] ?? null).' v panelu.', '/panel/sluzby', 'warn', $email, 'service-migration-scheduled', ['sluzba' => (string) ($p['label'] ?? ''), 'zacatek' => self::when($p['starts_at'] ?? null), 'od' => self::when($p['from'] ?? null), 'do' => self::when($p['to'] ?? null), 'duvod' => (string) ($p['reason'] ?? ''), 'url' => "{$portal}/panel/sluzby"]),
            'service.migration.rescheduled' => $this->internal($m, 'infra', 'Zákazník posunul stěhování '.($p['label'] ?? ''), 'Nový začátek '.self::when($p['starts_at'] ?? null), '/sprava#/gprov', 'info'),
            // chargeback in credit: support decides, the customer cancels, the share of the unused period comes back as credit
            'chargeback.requested' => $this->both($m, 'finance', 'Žádost o vrácení kreditu: '.($p['label'] ?? ''), ($org?->name ?? '').' · odhad '.$money($p['refund'] ?? null).' ('.(int) ($p['percent'] ?? 0).' %) · '.mb_substr((string) ($p['reason'] ?? ''), 0, 120), 'Žádost o vrácení kreditu přijata', 'Technická podpora ji posoudí; po schválení službu zrušíte v panelu a '.(int) ($p['percent'] ?? 0).' % nevyužitého období se vrátí jako kredit.', '/sprava#/money', '/panel/sluzby', 'info'),
            'chargeback.approved' => $this->customer($m, 'service', 'Vrácení kreditu za '.($p['label'] ?? '').' schváleno', 'Zrušte službu v panelu; vrátíme '.$money($p['refund'] ?? null).' ('.(int) ($p['percent'] ?? 0).' % nevyužitého období) jako kredit.', '/panel/sluzby', 'info', $email, 'chargeback-approved', ['sluzba' => (string) ($p['label'] ?? ''), 'castka' => $money($p['refund'] ?? null), 'procenta' => (string) (int) ($p['percent'] ?? 0), 'url' => "{$portal}/panel/sluzby"]),
            'chargeback.rejected' => $this->customer($m, 'service', 'Vrácení kreditu za '.($p['label'] ?? '').' jsme nemohli schválit', (string) ($p['reason'] ?? 'Napište podpoře, pokud chcete rozhodnutí probrat.'), '/panel/sluzby', 'warn', $email, 'chargeback-rejected', ['sluzba' => (string) ($p['label'] ?? ''), 'duvod' => (string) ($p['reason'] ?? ''), 'url' => "{$portal}/panel/podpora"]),
            'chargeback.refunded' => $this->both($m, 'finance', 'Kredit vrácen: '.($p['label'] ?? '').' · '.$money($p['refund'] ?? null), ($org?->name ?? ''), 'Kredit za zrušenou službu připsán', $money($p['refund'] ?? null).' ('.(int) ($p['percent'] ?? 0).' % nevyužitého období) je na vašem účtu.', '/sprava#/money', '/panel/fakturace', 'info', $email, 'chargeback-refunded', ['sluzba' => (string) ($p['label'] ?? ''), 'castka' => $money($p['refund'] ?? null), 'url' => "{$portal}/panel/fakturace"]),
            // loyalty programme: levels and badges
            'loyalty.level_up' => $this->customer($m, 'account', 'Nová úroveň věrnostního programu: '.($p['name'] ?? ''), ($p['reward'] ?? null) ? 'Odměna '.$money($p['reward']).' je na vašem promo kreditu.' : 'Díky, že jste s námi.', '/panel/nastaveni', 'info', $email, 'loyalty-level-up', ['uroven' => (string) ($p['name'] ?? ''), 'body' => (string) (int) ($p['points'] ?? 0), 'odmena' => ($p['reward'] ?? null) ? $money($p['reward']) : '—', 'url' => "{$portal}/panel/nastaveni"]),
            'loyalty.badge' => $this->customer($m, 'account', 'Nový odznak: '.($p['name'] ?? ''), 'Najdete ho v nastavení účtu.', '/panel/nastaveni'),
            'service.migration.collaborators_dropped' => $this->customer($m, 'service', 'Server '.($p['label'] ?? '').': spolupracovníky se nepodařilo přenést', 'Na nový server se nepřenesli spolupracovníci: '.(int) ($p['count'] ?? 0).'. Jejich práva by se při přenosu změnila, proto jsme je nepřidali. Přidejte je znovu v nastavení serveru.', '/panel/sluzby', 'warn'),
            'service.migrated' => $this->customer($m, 'service', 'Server '.($p['label'] ?? '').' byl přestěhován', 'Nová adresa: '.($p['address'] ?? '—').'. Data, nastavení i plány zůstaly.', '/panel/sluzby', 'info', $email, 'service-migrated', ['sluzba' => (string) ($p['label'] ?? ''), 'adresa' => (string) ($p['address'] ?? ''), 'url' => "{$portal}/panel/sluzby"]),
            'service.migration.failed' => $this->internal($m, 'infra', 'Stěhování serveru '.($p['label'] ?? '').' selhalo', (string) ($p['error'] ?? '').' · '.($p['source_node'] ?? '').' → '.($p['target_node'] ?? ''), '/sprava#/gprov', 'hot'),
            'service.suspended' => $this->customer($m, 'service', 'Služba byla pozastavena', (string) ($p['reason'] ?? ''), '/panel/sluzby', 'hot', $email, 'service-suspended', ['duvod' => (string) ($p['reason'] ?? ''), 'url' => "{$portal}/panel/fakturace"]),
            'service.resumed' => $this->customer($m, 'service', 'Služba byla obnovena', '', '/panel/sluzby'),
            'service.terminated' => $this->customer($m, 'service', 'Služba byla ukončena', 'Zálohy držíme po dobu retenční lhůty.', '/panel/sluzby', 'warn'),
            // the deletion lifecycle (audit §5ab): deactivation, the restore window, the removal and the archive
            'service.restore_test.failed' => $this->customer($m, 'service', 'Záloha se nepodařilo obnovit na zkoušku', 'Pravidelný test obnovy u služby '.($p['label'] ?? '').' neprošel: '.($p['problem'] ?? '').' Vaše data ani databáze jsme nijak nezměnili — test běží stranou. Díváme se na to.', '/panel/sluzby', 'warn'),
            'service.database.import.failed' => $this->customer($m, 'service', 'Import databáze se nedokončil', 'Import do databáze '.($p['database'] ?? '').' ('.($p['label'] ?? '').') se nedokončil'.(($p['restored'] ?? false) ? ' a vrátili jsme ji do stavu těsně před importem.' : '. Kopii z doby těsně před importem máme uloženou.').' Důvod: '.($p['reason'] ?? ''), '/panel/sluzby', 'warn'),
            'service.backup.schedule.paused' => $this->customer($m, 'service', 'Plánování záloh jsme zastavili', 'Zálohy služby '.($p['label'] ?? '').' selhaly '.(int) ($p['failures'] ?? 0).'× po sobě ('.($p['reason'] ?? '').'), tak jsme plán zastavili, aby se pokusy neopakovaly donekonečna. Hotové zálohy zůstávají. Plán se rozeběhne, jakmile ho znovu nastavíte.', '/panel/sluzby', 'warn'),
            'service.backup.schedule.stalled' => $this->customer($m, 'service', 'Plánovaná záloha se nespustila', 'Zálohu služby '.($p['label'] ?? '').' se nepodařilo spustit '.(int) ($p['missed'] ?? 0).'× po sobě ('.($p['reason'] ?? '').'). Díváme se na to; poslední hotová záloha zůstává k dispozici.', '/panel/sluzby', 'warn'),
            'service.rescue.started' => $this->customer($m, 'service', 'Server běží v záchranném režimu', 'Nastartovali jsme server ze záchranného obrazu ('.($p['image'] ?? '').'). Disky zůstaly nedotčené. Režim sám skončí '.($p['until'] ?? '').' a server nabootuje zpět do svého systému.', '/panel/sluzby', 'warn'),
            'service.rescue.ended' => $this->customer($m, 'service', 'Záchranný režim skončil', 'Server jsme vrátili do vlastního systému ('.($p['reason'] ?? '').').', '/panel/sluzby', 'info'),
            'service.deactivated' => $this->internal($m, 'service', 'Služba deaktivována ke zrušení', (string) ($p['reason'] ?? ''), '/sprava/sluzby'),
            'service.deletion.scheduled' => $this->customer($m, 'service', 'Služba byla zrušena a deaktivována', 'Zálohu máme hotovou. Obnovit službu můžete do '.(int) ($p['grace_days'] ?? 30).' dnů. Data uchováme dalších '.(int) ($p['retention_days'] ?? 60).' dní.', '/panel/sluzby', 'warn', $email, 'service-deletion-scheduled', ['lhuta' => (string) (int) ($p['grace_days'] ?? 30), 'uchovani' => (string) (int) ($p['retention_days'] ?? 60), 'url' => "{$portal}/panel/sluzby"]),
            'service.deletion.cancelled' => $this->customer($m, 'service', 'Zrušení služby jsme odvolali', 'Služba běží dál, plánované odstranění jsme zrušili.', '/panel/sluzby', 'info'),
            'organization.member.expired' => $this->customer($m, 'service', 'Dočasný přístup skončil', 'Přístup skončil k datu, které jste nastavili: '.(string) ($p['name'] ?? $p['email'] ?? '').((string) ($p['project'] ?? '') !== '' ? ' · projekt '.(string) ($p['project'] ?? '') : '').'. Účty spolupracovníka a SSH klíče této osoby rušíme.', '/panel/tym', 'info'),
            'access.ssh_keys.revoked' => $this->customer($m, 'service', 'Rušíme SSH klíče odebraného člena', 'Odebraný člen měl na vašich webech SSH klíče, které teď rušíme: '.(int) ($p['count'] ?? 0).'.'.((int) ($p['pending'] ?? 0) > 0 ? ' Panel zatím nepotvrdil: '.(int) ($p['pending'] ?? 0).'.' : '').' Pokud znal heslo shell nebo FTP účtu, změňte ho.', '/panel/sluzby', 'warn'),
            'access.review.findings' => $this->customer($m, 'service', 'Zkontrolujte přístupy spolupracovníků', 'Na vašich herních serverech mají přístup lidé, kteří už nejsou členy organizace: '.(int) ($p['count'] ?? 0).'.', '/panel/sluzby', 'warn'),
            'service.delegations.revoked' => $this->customer($m, 'service', 'Delegované přístupy ke zrušené službě byly odvolány', 'FTP, SSH a účty spolupracovníků jsme odstranili. Po případné obnově služby je založte znovu.', '/panel/sluzby', 'info'),
            'service.final_archive.created' => $this->internal($m, 'service', 'Záloha před zrušením hotová', number_format((int) ($p['bytes'] ?? 0) / 1048576, 1).' MB · '.(string) ($p['set'] ?? ''), '/sprava/sluzby'),
            'service.archive.downloaded' => $this->internal($m, 'service', 'Zákazník stáhl archiv zrušené služby', $money($p['fee'] ?? null).' · '.(string) ($p['backup_id'] ?? ''), '/sprava#/money'),
            // availability of a VPS / game server (H14): the customer always; operations only where an SLA class says somebody is on the hook
            'service.stopped_unexpectedly' => (function () use ($m, $p, $email, $portal) {
                $name = (string) (($p['label'] ?? '') ?: ($p['hostname'] ?? ''));
                $what = ($p['family'] ?? '') === 'game' ? 'Herní server' : 'Server';
                $this->customer($m, 'service', "{$what} {$name} neběží", 'Server je vypnutý, aniž jste ho u nás vypínali. Zapnete ho v panelu; pokud jste ho vypnuli sami zevnitř, nic se neděje.', '/panel/sluzby', 'hot', ! empty($p['notify']) ? $email : null, 'service-stopped', ['sluzba' => $name, 'url' => $portal.'/panel/sluzby']);
                if (($p['sla_class'] ?? 'standard') !== 'standard') {
                    $this->internal($m, 'service', "{$what} {$name} neběží (SLA ".($p['sla_class'] ?? '').')', 'Vypnutý bez zásahu zákazníka od '.($p['since'] ?? '?').'.', '/sprava/sluzby', 'hot');
                }
            })(),
            'service.running_again' => $this->customer($m, 'service', ((($p['family'] ?? '') === 'game') ? 'Herní server ' : 'Server ').(($p['label'] ?? '') ?: ($p['hostname'] ?? '')).' opět běží', 'Mimo provoz byl '.(int) ($p['minutes'] ?? 0).' min.', '/panel/sluzby', 'info', ! empty($p['notify']) ? $email : null, 'service-running', ['sluzba' => (string) (($p['label'] ?? '') ?: ($p['hostname'] ?? '')), 'trvani' => (int) ($p['minutes'] ?? 0).' min', 'url' => $portal.'/panel/sluzby']),
            'service.degraded' => $this->internal($m, 'service', 'Služba degradována', (string) ($p['reason'] ?? ''), '/sprava/sluzby', 'hot'),
            'service.recovered' => $this->internal($m, 'service', 'Služba opět v pořádku', '', '/sprava/sluzby'),
            // one payment produces a proforma, a receipt and a statement: only the documents the customer acts on or files (proforma,
            // invoice, credit note) are mailed as "Doklad"; the receipt is covered by "Platba přijata", the statement stays in the panel
            'invoice.issued' => $this->customer($m, 'invoice.issued', "Nový doklad {$p['number']}", $money($p['total'] ?? null).(($p['type'] ?? '') === 'invoice' ? ' · splatnost 14 dní' : ''), '/panel/fakturace', 'info', in_array($p['type'] ?? 'invoice', ['proforma', 'invoice', 'credit_note'], true) ? $email : null, 'invoice', ['cislo' => $p['number'], 'castka' => $money($p['total'] ?? null), 'splatnost' => $p['due_at'] ?? '', 'url' => "{$portal}/panel/fakturace"]),
            'invoice.cancelled' => $this->customer($m, 'invoice.issued', "Doklad {$p['number']} byl stornován", (string) ($p['reason'] ?? 'objednávka zrušena, nic k úhradě'), '/panel/fakturace'),
            'invoice.overdue' => $this->customer($m, 'invoice.issued', "Doklad {$p['number']} je po splatnosti", $money($p['outstanding'] ?? ($p['total'] ?? null)), '/panel/fakturace', 'hot', $email, 'invoice-overdue', ['cislo' => $p['number'], 'castka' => $money($p['outstanding'] ?? ($p['total'] ?? null)), 'url' => "{$portal}/panel/fakturace"]),
            'invoice.paid' => $this->both($m, 'invoice', "Platba {$p['number']}", ($org?->name ?? '').' · '.$money($p['amount'] ?? null), "Platba přijata: {$p['number']}", $money($p['amount'] ?? null).' · služby se zřizují', '/sprava/doklady', '/panel/fakturace', 'info', ($p['type'] ?? '') === 'invoice' ? $email : null, 'payment-received', ['cislo' => $p['number'] ?? '', 'castka' => $money($p['amount'] ?? null), 'url' => $portal.'/panel/fakturace']), // receipts are announced by payment.succeeded (order) or wallet.topup.completed (top-up)
            'wallet.runway.low' => $this->customer($m, 'wallet', 'Kredit vystačí ještě '.(int) ($p['days'] ?? 0).' dní', 'do '.substr((string) ($p['depletes_at'] ?? ''), 0, 10).' · na obnovy chybí '.$money($p['shortfall'] ?? null), '/panel/fakturace', 'warn', $email, 'wallet-runway', ['dni' => (string) (int) ($p['days'] ?? 0), 'datum' => substr((string) ($p['depletes_at'] ?? ''), 0, 10), 'chybi' => $money($p['shortfall'] ?? null), 'zustatek' => $money($p['available'] ?? null), 'url' => $portal.'/panel/fakturace']),
            // renewal guard (audit §5e-2): the renewals of the coming week outrun the credit — topped up automatically, or the customer is asked
            'billing.renewal.underfunded' => (function () use ($m, $p, $email, $portal, $money) {
                $topup = (array) ($p['auto_topup'] ?? []);
                $first = substr((string) ($p['first_renewal_at'] ?? ''), 0, 10);
                if (($topup['status'] ?? '') === 'charged') {
                    $this->customer($m, 'wallet', 'Kredit dobit automaticky o '.$money($topup['amount'] ?? null), 'Obnovy do '.$first.' by kredit nepokryly; podle vašeho nastavení jsme kredit dobili z uložené platební metody.', '/panel/fakturace', 'info');

                    return;
                }
                $body = 'Do '.$first.' se obnovují služby za '.$money($p['due'] ?? null).', k dispozici je '.$money($p['available'] ?? null).' — chybí '.$money($p['shortfall'] ?? null).'.'
                    .(($topup['status'] ?? '') === 'disabled' ? ' Dobijte kredit, nebo si zapněte automatické dobití.' : ' Automatické dobití se nepodařilo ('.(string) ($topup['reason'] ?? $topup['status'] ?? '').'); dobijte prosím kredit.');
                $this->customer($m, 'wallet', 'Na obnovy příštího týdne chybí '.$money($p['shortfall'] ?? null), $body, '/panel/fakturace', 'warn', $email, 'renewal-underfunded', ['castka' => $money($p['due'] ?? null), 'chybi' => $money($p['shortfall'] ?? null), 'datum' => $first, 'url' => "{$portal}/panel/fakturace"]);
            })(),
            // stored card (audit §5f-1): the customer sees which card the automatic top-up will charge
            'payment.method.saved' => $this->customer($m, 'wallet', 'Karta •••• '.($p['last4'] ?? '').' uložena pro automatické dobití', ! empty($p['auto_topup']) ? 'Automatické dobití kreditu ji použije, když kredit nepokryje obnovy příštího týdne. Odebrat ji můžete ve Fakturaci.' : 'Zapněte automatické dobití kreditu ve Fakturaci a obnovy proběhnou bez vašeho zásahu. Kartu můžete kdykoli odebrat.', '/panel/fakturace', 'info'),
            'payment.succeeded' => ($p['purpose'] ?? '') === 'order' ? $this->customer($m, 'invoice.issued', 'Platba přijata', $money($p['amount'] ?? null).($orderNumber !== '' ? " · objednávka {$orderNumber}" : '').' · služby se zřizují', '/panel/objednavky', 'info', $email, 'payment-received', ['cislo' => $orderNumber !== '' ? $orderNumber : ($p['reference'][1] ?? ''), 'castka' => $money($p['amount'] ?? null), 'url' => $portal.'/panel/objednavky']) : null,
            'wallet.topup.completed' => $this->customer($m, 'wallet', 'Kredit dobit', $money($p['amount'] ?? null), '/panel/fakturace', 'info', ($p['purpose'] ?? 'topup') === 'topup' ? $email : null, 'wallet-topup', ['castka' => $money($p['amount'] ?? null), 'zustatek' => $money($p['balance'] ?? null) ?: '—', 'url' => $portal.'/panel/fakturace']),
            'wallet.frozen' => $this->both($m, 'wallet', 'Peněženka zmrazena', (string) ($p['reason'] ?? ''), 'Peněženka byla zmrazena', 'Kontaktujte prosím podporu.', '/sprava/zakaznici', '/panel/fakturace', 'hot'),
            'budget.threshold' => $this->customer($m, 'wallet', 'Rozpočet: '.($p['threshold'] ?? '').' %', 'Útrata dosáhla nastaveného prahu.', '/panel/fakturace', 'warn'),
            'dunning.notice' => $this->customer($m, 'dunning', 'Upomínka — neuhrazený doklad', 'Po splatnosti '.($p['days_overdue'] ?? '').' dní.', '/panel/fakturace', 'hot', $email, 'dunning-notice', ['dni' => $p['days_overdue'] ?? '', 'url' => "{$portal}/panel/fakturace"]),
            'dunning.suspended' => $this->both($m, 'dunning', 'Služba pozastavena pro neplacení', $org?->name ?? '', 'Služba byla pozastavena pro neplacení', 'Po uhrazení se služba automaticky obnoví.', '/sprava/fakturace', '/panel/fakturace', 'hot', $email, 'dunning-suspended', ['url' => "{$portal}/panel/fakturace"]),
            'dunning.termination_scheduled' => $this->both($m, 'dunning', 'Naplánováno zrušení služby', ($org?->name ?? '').' · '.($p['termination_at'] ?? ''), 'Služba bude zrušena', 'Datum zrušení: '.($p['termination_at'] ?? '').'. Uhraďte doklad, zrušení se odvolá.', '/sprava/fakturace', '/panel/fakturace', 'hot', $email, 'dunning-termination', ['datum' => $p['termination_at'] ?? '', 'url' => "{$portal}/panel/fakturace"]),
            'dunning.resolved' => $this->customer($m, 'dunning', 'Platba přijata, vše v pořádku', '', '/panel/fakturace'),
            'subscription.renewed' => $this->customer($m, 'invoice', 'Služba prodloužena', 'do '.substr((string) ($p['period_end'] ?? ''), 0, 10), '/panel/fakturace'),
            'subscription.renewal_failed' => $this->customer($m, 'dunning', 'Prodloužení služby se nezdařilo', (($p['cause'] ?? 'credit') === 'budget' ? 'Měsíční rozpočet je vyčerpaný · potřeba ' : 'Nedostatek kreditu · potřeba ').$money($p['required'] ?? null), '/panel/fakturace', 'hot', $email, 'renewal-failed', ['castka' => $money($p['required'] ?? null), 'do' => substr((string) ($p['period_end'] ?? ''), 0, 10), 'url' => "{$portal}/panel/fakturace"]),
            'domain.registered', 'domain.transferred_in' => $this->customer($m, 'domain', "Doména {$p['fqdn']} je aktivní", 'platí do '.substr((string) ($p['expires_at'] ?? ''), 0, 10), '/panel/domeny', 'info', $email, 'domain-registered', ['domena' => $p['fqdn'], 'expirace' => substr((string) ($p['expires_at'] ?? ''), 0, 10), 'url' => "{$portal}/panel/domeny"]),
            'domain.registration_failed' => $this->both($m, 'domain', "Registrace {$p['fqdn']} selhala", (string) ($p['reason'] ?? ''), "Registraci {$p['fqdn']} se nepodařilo dokončit", 'Platbu vracíme na kredit, podpora vás kontaktuje.', '/sprava/objednavky', '/panel/domeny', 'hot', $email, 'domain-renewal-failed', ['domena' => $p['fqdn'], 'duvod' => (string) ($p['reason'] ?? ''), 'url' => "{$portal}/panel/domeny"]),
            'domain.renewal_notice' => $this->customer($m, 'domain.expiry', "Doména {$p['fqdn']} expiruje za {$p['days']} dní", ($p['auto_renew'] ?? false) ? 'Automatické prodloužení je zapnuté.' : 'Automatické prodloužení je vypnuté — prodlužte ručně.', '/panel/domeny', ((int) ($p['days'] ?? 60)) <= 7 ? 'hot' : 'warn', $email, 'domain-renewal', ['domena' => $p['fqdn'], 'dni' => $p['days'], 'expirace' => substr((string) ($p['expires_at'] ?? ''), 0, 10), 'autorenew' => ($p['auto_renew'] ?? false) ? 'zapnuté' : 'vypnuté', 'url' => "{$portal}/panel/domeny"]),
            'domain.renewed' => $this->customer($m, 'domain', "Doména {$p['fqdn']} prodloužena", 'do '.substr((string) ($p['expires_at'] ?? ''), 0, 10), '/panel/domeny', 'info', $email, 'domain-renewed', ['domena' => $p['fqdn'], 'expirace' => substr((string) ($p['expires_at'] ?? ''), 0, 10), 'url' => "{$portal}/panel/domeny"]),
            'domain.renewal_failed', 'domain.renewal_payment_failed', 'domain.renewal_abandoned' => $this->both($m, 'domain.expiry', "Prodloužení {$p['fqdn']} selhalo", (string) ($p['reason'] ?? ($p['error'] ?? '')), "Prodloužení domény {$p['fqdn']} se nezdařilo", $m->name === 'domain.renewal_failed' ? 'Registr prodloužení nepřijal. Řešíme to a zkoušíme to každý den znovu; pokud je potřeba něco od vás, ozveme se. Z kreditu jsme nic nestrhli.' : (! empty($p['in_grace']) ? 'Doména už expirovala. V ochranné lhůtě ji ještě prodloužíme za běžnou cenu — zbývá dní: '.(int) ($p['grace_days_left'] ?? 0).'. Dobijte kredit, prodloužíme ji hned.' : 'Dobijte kredit nebo prodlužte ručně, doména jinak expiruje.'), '/sprava/fakturace', '/panel/domeny', 'hot', $email, 'domain-renewal-failed', ['domena' => $p['fqdn'], 'duvod' => (string) ($p['reason'] ?? ($p['error'] ?? '')), 'url' => "{$portal}/panel/domeny"]),
            'registrar.connection.linked' => $this->customer($m, 'domain', "Účet {$p['provider_label']} připojen", ((int) ($p['domains'] ?? 0)).' domén · automatická synchronizace, upozornění na expirace a párování s hostingem', '/panel/registratori', 'info'),
            'registrar.connection.sync_failed' => $this->customer($m, 'domain', "Synchronizace účtu {$p['label']} selhala", (string) ($p['error'] ?? ''), '/panel/registratori', 'hot'),
            'registrar.connection.credit_low' => $this->customer($m, 'domain.expiry', "Kredit u registrátora klesl na {$p['balance']} {$p['currency']}", 'Obnovy domén v účtu '.($p['label'] ?? '').' by nemusely projít; dobijte kredit u registrátora.', '/panel/registratori', 'warn', $email, 'registrar-credit-low', ['ucet' => (string) ($p['label'] ?? ''), 'kredit' => ($p['balance'] ?? '').' '.($p['currency'] ?? ''), 'url' => "{$portal}/panel/registratori"]),
            'registrar.connection.unlinked' => $this->customer($m, 'domain', "Účet {$p['label']} odpojen", ((int) ($p['domains_removed'] ?? 0)).' zrcadlených domén bylo z panelu odebráno; u registrátora se nic nezměnilo.', '/panel/registratori', 'info'),
            'domain.external_expiry_notice' => $this->customer($m, 'domain.expiry', "Doména {$p['fqdn']} expiruje za {$p['days']} dní", 'Je registrována u '.($p['registrar'] ?? 'registrátora').' (účet '.($p['account'] ?? '').'). Prodlužte ji tam, nebo ji převeďte k nám.', '/panel/registratori', ((int) ($p['days'] ?? 60)) <= 7 ? 'hot' : 'warn', $email, 'domain-external-expiry', ['domena' => (string) $p['fqdn'], 'dni' => (string) $p['days'], 'expirace' => substr((string) ($p['expires_at'] ?? ''), 0, 10), 'registrator' => (string) ($p['registrar'] ?? ''), 'url' => "{$portal}/panel/registratori"]),
            'domain.connection.missing' => $this->customer($m, 'domain', "Doména {$p['fqdn']} už není v připojeném účtu", 'Byla převedena nebo smazána u registrátora; v panelu zůstává označená.', '/panel/registratori', 'warn'),
            'domain.paired' => $this->customer($m, 'domain', "Doména {$p['fqdn']} spárována s webem {$p['hostname']}", ($p['dns'] ?? '') === 'synced' ? 'Záznamy A pro @ a www míří na server; certifikát vystavíme, jakmile se změna rozšíří.' : 'Nastavte u svého DNS záznamy A pro @ a www na adresu serveru; certifikát vystavíme poté.', '/panel/registratori', 'info'),
            'domain.unpaired' => $this->customer($m, 'domain', "Doména {$p['fqdn']} odpojena od webu", 'Alias na serveru i záznamy, které párování přidalo, jsou pryč.', '/panel/registratori', 'info'),
            'domain.expired' => $this->customer($m, 'domain.expiry', "Doména {$p['fqdn']} expirovala", 'V ochranné lhůtě ji lze ještě obnovit.', '/panel/domeny', 'hot', $email, 'domain-expired', ['domena' => $p['fqdn'], 'url' => "{$portal}/panel/domeny"]),
            'domain.closed' => $this->both($m, 'domain',
                ($p['state'] ?? '') === 'DELETED' ? "Doména {$p['fqdn']} byla u registru smazána" : "Doména {$p['fqdn']} už není u našeho registrátora",
                'Prodlužování je zastaveno.'.(! empty($p['zone']) ? ' DNS zóna '.$p['zone'].' u nás zůstává publikovaná — smažte ji, až ji zákazník nebude potřebovat.' : ''),
                ($p['state'] ?? '') === 'DELETED' ? "Doména {$p['fqdn']} byla po expiraci smazána" : "Doména {$p['fqdn']} už není v naší správě",
                ($p['state'] ?? '') === 'DELETED' ? 'Registr ji po uplynutí ochranné lhůty smazal. Prodlužování jsme zastavili; jméno lze zaregistrovat znovu, jakmile je volné.' : 'Byla převedena k jinému registrátorovi. Prodlužování u nás jsme zastavili. Pokud jste o převod nežádali, kontaktujte ihned podporu.',
                '/sprava/domeny', '/panel/domeny', 'warn'),
            'domain.reopened' => $this->internal($m, 'domain', 'Doména je zpět u registrátora: '.($p['fqdn'] ?? ''), 'Byla uzavřená jako chybějící a registrátor ji znovu vypisuje; je opět aktivní, prodlužování jako dřív.', '/sprava/domeny', 'warn'),
            'domain.transferred_out' => $this->both($m, 'domain', "Doména {$p['fqdn']} převedena pryč", '', "Doména {$p['fqdn']} byla převedena k jinému registrátorovi", '', '/sprava/zakaznici', '/panel/domeny', 'warn'),
            'domain.auth_info_requested' => $this->customer($m, 'security.mfa', ($p['delivery'] ?? null) === 'inline' ? "AUTH-ID pro {$p['fqdn']} bylo vydáno v panelu" : "AUTH-ID pro {$p['fqdn']} bylo odesláno držiteli", 'Pokud jste o převod nežádali, kontaktujte ihned podporu.', '/panel/domeny', 'hot', $email, 'domain-auth-info', ['domena' => $p['fqdn'], 'url' => "{$portal}/panel/domeny"]),
            'assistant.budget.exhausted' => $this->internal($m, 'ai', 'AI asistent: vyčerpaný limit '.($p['limit'] ?? ''), 'kdo: '.($p['subject'] ?? '').' · hodnota limitu: '.(int) ($p['value'] ?? 0).' · asistent odpovídá podle nápovědy, nic se neodmítá', '/sprava/fronta', ($p['limit'] ?? '') === 'tokens_per_day' ? 'hot' : 'warn'),
            'dns.drift.detected' => $this->internal($m, 'dns', 'DNS zóna se liší od poskytovatele: '.($p['name'] ?? ''), ! empty($p['zone_missing']) ? 'zóna u poskytovatele neexistuje' : 'chybí u poskytovatele: '.(int) ($p['missing_at_provider'] ?? 0).' · navíc u poskytovatele: '.(int) ($p['unknown_at_provider'] ?? 0).' · '.implode(', ', (array) ($p['sample'] ?? [])), '/sprava/domeny', ! empty($p['zone_missing']) ? 'hot' : 'warn'),
            'dns.zone.republished' => ! empty($p['created']) || ! empty($p['dnssec_attention']) || empty($p['verified']) ? $this->internal($m, 'dns', 'DNS zóna znovu publikována: '.($p['name'] ?? ''), (! empty($p['created']) ? 'zóna u poskytovatele byla vytvořena znovu · ' : '').'přidáno: '.(int) ($p['added'] ?? 0).' · odebráno: '.(int) ($p['removed'] ?? 0).' · upraveno: '.(int) ($p['updated'] ?? 0).(empty($p['verified']) ? ' · po publikaci stále zbývá rozdílů: '.(int) ($p['left'] ?? 0) : '').(! empty($p['dnssec_attention']) ? ' · zóna byla podepsaná: zapněte DNSSEC znovu a zveřejněte nový DS u registru' : ''), '/sprava/domeny', ! empty($p['dnssec_attention']) || empty($p['verified']) ? 'hot' : 'info') : null,
            'dns.zone.committed' => $this->customer($m, 'dns', "DNS {$p['name']}: verze {$p['version']} publikována", ($p['records'] ?? 0).' záznamů', '/panel/domeny'),
            'security.login' => $this->user($m, 'security.login', 'Nové přihlášení', ($p['ip'] ?? '').' · '.($p['user_agent'] ?? ''), '/panel/nastaveni', 'info', 'security-login', ['ip' => $p['ip'] ?? '', 'zarizeni' => $p['user_agent'] ?? '', 'cas' => $p['at'] ?? now()->toIso8601String(), 'url' => "{$portal}/panel/nastaveni"]),
            'security.mfa' => $this->user($m, 'security.mfa', 'Dvoufázové ověření změněno', (string) ($p['change'] ?? ''), '/panel/nastaveni', 'warn', 'security-mfa', ['zmena' => $p['change'] ?? '', 'url' => "{$portal}/panel/nastaveni"]),
            // a reset revokes the API tokens, a change keeps them — the mail says which, and how many stay valid (it used to say "signed out" for both)
            'security.password_changed' => ($p['api_access'] ?? 'revoked') === 'kept'
                ? $this->user($m, 'security.mfa', 'Heslo bylo změněno', trim(($p['ip'] ?? '').' · API tokeny zůstávají platné: '.(int) ($p['api_access_count'] ?? 0)), '/panel/nastaveni', 'warn', 'security-password-kept', ['ip' => $p['ip'] ?? '', 'pocet' => (string) (int) ($p['api_access_count'] ?? 0), 'url' => "{$portal}/panel/nastaveni"])
                : $this->user($m, 'security.mfa', 'Heslo bylo změněno', ($p['ip'] ?? ''), '/panel/nastaveni', 'warn', 'security-password', ['ip' => $p['ip'] ?? '', 'url' => "{$portal}/panel/nastaveni"]),
            'security.account_locked' => $this->user($m, 'security.login', 'Účet dočasně uzamčen', 'Opakované neúspěšné přihlášení z '.($p['ip'] ?? ''), '/panel/nastaveni', 'hot', 'security-locked', ['ip' => $p['ip'] ?? '', 'url' => "{$portal}/obnova-hesla"]),
            'api_token.created' => $this->user($m, 'api_token.created', "Vytvořen API token „{$p['name']}“", 'rozsah: '.implode(', ', (array) ($p['scopes'] ?? [])), '/panel/api', 'warn', 'api-token', ['nazev' => $p['name'] ?? '', 'rozsah' => implode(', ', (array) ($p['scopes'] ?? [])), 'expirace' => substr((string) ($p['expires_at'] ?? ''), 0, 10), 'url' => "{$portal}/panel/api"]),
            'identity.registered' => $this->mailOnly($m, (string) ($p['email'] ?? ''), 'welcome', ['jmeno' => $p['name'] ?? '', 'organizace' => $org?->name ?? '', 'url' => "{$portal}/panel"], $locale),
            'organization.invitation.created' => $this->internal($m, 'account', 'Pozvánka do organizace odeslána', ($org?->name ?? '').' · '.($p['email'] ?? '').' · '.($p['role'] ?? ''), '/sprava/zakaznici'),
            'organization.invitation.cancelled' => $this->internal($m, 'account', 'Pozvánka do organizace zrušena', ($org?->name ?? '').' · '.($p['email'] ?? '').' · '.($p['role'] ?? ''), '/sprava/zakaznici'),
            'ticket.created' => $this->both($m, 'ticket', "Nový tiket {$p['number']}", ($p['name'] ?? $p['email'] ?? '').' · '.($p['subject'] ?? ''), "Přijali jsme váš požadavek {$p['number']}", (string) ($p['subject'] ?? ''), '/sprava/fronta', '/panel/tikety', ($p['priority'] ?? 'p3') === 'p1' ? 'hot' : 'info', (string) ($p['email'] ?? $email), 'ticket-ack', ['cislo' => $p['number'], 'predmet' => $p['subject'] ?? '', 'sla' => $p['first_response_minutes'] ?? '', 'url' => "{$portal}/panel/tikety"]),
            'ticket.replied' => (($p['author_type'] ?? '') === 'staff' || ($p['author_type'] ?? '') === 'ai')
                ? $this->customer($m, 'ticket', "Odpověď podpory · {$p['number']}", (string) ($p['subject'] ?? ''), '/panel/tikety', 'info', (string) ($p['email'] ?? $email), 'ticket-reply', ['cislo' => $p['number'], 'predmet' => $p['subject'] ?? '', 'uryvek' => mb_substr((string) ($p['excerpt'] ?? ''), 0, 300), 'url' => "{$portal}/panel/tikety"])
                : $this->internal($m, 'ticket', "Reakce zákazníka · {$p['number']}", (string) ($p['subject'] ?? ''), '/sprava/fronta'),
            // paid work on a ticket (H29): the customer hears there is a price to decide, staff hear the decision; the ticket mail carries the text
            'ticket.work_offer.proposed' => $this->customer($m, 'ticket', 'Nabídka placeného zásahu · '.($p['number'] ?? ''), 'Čeká na vaše rozhodnutí: '.$money($p['price'] ?? null).' bez DPH. Bez schválení nic neúčtujeme.', '/panel/tikety', 'warn'),
            'ticket.work_offer.approved' => $this->internal($m, 'ticket', 'Placený zásah schválen · '.($p['number'] ?? ''), $money($p['price'] ?? null).' bez DPH · '.($p['scope'] ?? ''), '/sprava/fronta', 'warn'),
            'ticket.work_offer.declined' => $this->internal($m, 'ticket', 'Placený zásah odmítnut · '.($p['number'] ?? ''), (string) ($p['subject'] ?? ''), '/sprava/fronta'),
            'ticket.escalated' => $this->internal($m, 'ticket', "Eskalace {$p['number']} → L".($p['level'] ?? ''), (string) ($p['reason'] ?? ''), '/sprava/fronta', 'hot'),
            'ticket.sla_breached' => $this->internal($m, 'ticket', "SLA porušeno · {$p['number']}", (string) ($p['kind'] ?? ''), '/sprava/fronta', 'hot'),
            'ticket.resolved' => $this->customer($m, 'ticket', "Tiket {$p['number']} vyřešen", 'Ohodnoťte prosím řešení.', '/panel/tikety'),
            'ticket.handoff' => $this->internal($m, 'ticket', "Předání od AI asistenta · {$p['number']}", (string) ($p['reason'] ?? ''), '/sprava/fronta', 'warn'),
            'incident.opened' => $this->both($m, 'incident.affecting', "{$p['number']} otevřen", (string) ($p['title'] ?? ''), 'Probíhá incident', (string) ($p['title'] ?? ''), '/sprava/incidenty', '/stav', 'hot', $email, 'incident', ['incident' => $p['number'], 'nazev' => $p['title'] ?? '', 'stav' => $p['state'] ?? '', 'dopad' => $p['impact'] ?? '', 'url' => "{$portal}/stav"]),
            'incident.updated' => $this->both($m, 'incident.affecting', "{$p['number']} → {$p['state_label']}", (string) ($p['note'] ?? ''), "{$p['number']} — {$p['state_label']}", (string) ($p['note'] ?? ''), '/sprava/incidenty', '/stav', 'warn'),
            'incident.resolved' => $this->both($m, 'incident.affecting', "{$p['number']} vyřešen", (string) ($p['note'] ?? ''), 'Incident vyřešen', (string) ($p['title'] ?? ''), '/sprava/incidenty', '/stav', 'info', $email, 'incident-resolved', ['incident' => $p['number'], 'nazev' => $p['title'] ?? '', 'trvani' => $p['duration'] ?? '', 'url' => "{$portal}/stav"]),
            'maintenance.scheduled' => $this->customer($m, 'incident.affecting', 'Plánovaná údržba', ($p['title'] ?? '').' · '.substr((string) ($p['starts_at'] ?? ''), 0, 16), '/stav', 'warn', $email, 'maintenance', ['nazev' => $p['title'] ?? '', 'od' => $p['starts_at'] ?? '', 'do' => $p['ends_at'] ?? '', 'dopad' => $p['impact'] ?? '', 'url' => "{$portal}/stav"]),
            'sla.credit.issued' => $this->customer($m, 'invoice.issued', 'SLA kredit připsán', $money($p['amount'] ?? null).' · '.($p['incident'] ?? ''), '/panel/fakturace', 'info', $email, 'sla-credit', ['castka' => $money($p['amount'] ?? null), 'incident' => $p['incident'] ?? '', 'procento' => $p['percent'] ?? '', 'url' => "{$portal}/panel/fakturace"]),
            'partner.application.received' => $this->internal($m, 'partner', 'Nová partnerská přihláška', ($p['company'] ?? '').' · '.($p['model'] ?? ''), '/sprava/zakaznici', 'info'),
            'partner.approved' => $this->customer($m, 'partner', 'Partnerský účet schválen', 'Kód '.($p['code'] ?? '').' · '.($p['tier'] ?? '').' '.($p['rate'] ?? '').' %', '/partner/prehled', 'info'),
            'partner.tier.changed' => $this->customer($m, 'partner', 'Stupeň přepočítán: '.($p['to'] ?? ''), 'Sazba '.($p['rate'] ?? '').' % · objem '.$money($p['volume'] ?? null), '/partner/prehled', 'info'),
            'lead.received' => $this->internal($m, 'sales', 'Nová poptávka: '.($p['kind'] ?? ''), ($p['company'] ?? $p['name'] ?? '').' · '.($p['email'] ?? ''), '/sprava/zakaznici', 'info'),
            'partner.payout.requested' => $this->internal($m, 'partner', 'Žádost o výplatu provize', ($org?->name ?? '').' · '.$money($p['amount'] ?? null), '/sprava/fakturace', 'warn'),
            'partner.payout.paid' => $this->customer($m, 'partner', 'Provize vyplacena', $money($p['amount'] ?? null), '/partner/vyplaty', 'info', $email, 'payout', ['castka' => $money($p['amount'] ?? null), 'obdobi' => $p['period'] ?? '', 'url' => "{$portal}/partner/vyplaty"]),
            'compliance.data_export.ready' => $this->customer($m, 'legal.notice', 'Export dat je připraven', 'Ke stažení '.($p['days'] ?? 30).' dní.', '/panel/nastaveni', 'info', $email, 'data-export', ['url' => "{$portal}/panel/nastaveni", 'dni' => $p['days'] ?? 30]),
            // a plan version decides what new customers get and pay (H01): finance hears about it, it is not an alarm
            'catalog.plan.version_published', 'catalog.plan.version_activated' => $this->internal($m, 'finance', self::internalTitle($m->name, $p), 'Změněno: '.implode(', ', array_merge((array) ($p['changed']['entitlements'] ?? []), (array) ($p['changed']['limits'] ?? []), (array) ($p['changed']['prices'] ?? []))).' · důvod: '.($p['reason'] ?? ''), '/sprava/fakturace', 'warn'),
            'platform.mail.failing', 'platform.mail.recovered',
            'registrar.credit.low', 'integration.down', 'integration.maintenance.lifted', 'integration.maintenance.overdue', 'security.ssh_key.revocation.stuck', 'platform.load_shedding.started', 'platform.load_shedding.ended', 'platform.queue.stalled', 'platform.queue.backlog', 'node.drained', 'node.resumed', 'node.qualified', 'node.synthetic.leftover', 'service.relocated', 'service.backup.schedule.stalled', 'service.backup.schedule.paused', 'service.database.import.failed', 'service.restore_test.failed', 'capacity.unavailable', 'ipam.exhausted', 'ipam.threshold', 'ipam.rdns.unpublished', 'ipam.rdns.failed', 'provisioning.drift.detected', 'operation.failed', 'finance.reconciliation.mismatch', 'registrar.notification.dead', 'domain.reconcile.missing_remote', 'domain.reconcile.unknown_remote', 'payment.orphan_callback', 'security.incident.opened', 'abuse.case.opened', 'compliance.timer.due', 'compliance.timer.missed', 'sla.burn_rate', 'sla.budget.exhausted', 'maintenance.unapproved' => $this->internal($m, self::internalKind($m->name), self::internalTitle($m->name, $p), mb_substr(json_encode(array_diff_key($p, array_flip(['row', 'raw'])), JSON_UNESCAPED_UNICODE) ?: '', 0, 250), self::internalSurface($m->name), 'hot'),
            // marketplace (audit §5j-1): the partner gets the brief, the customer the delivery; disputes reach support and the partner
            'marketplace.ordered' => $this->customer($m, 'order', 'Objednávka z marketplace: '.($p['title'] ?? ''), 'Partner dostal zadání; dodání do '.self::when($p['due_at'] ?? null).'. Zaplaceno z kreditu ('.$money($p['total'] ?? null).').', '/panel/nastaveni', 'info'),
            'marketplace.assigned' => $this->customer($m, 'order', 'Nová zakázka z marketplace: '.($p['title'] ?? ''), 'Zákazník '.($p['customer'] ?? '').' · dodání do '.self::when($p['due_at'] ?? null).' · '.mb_substr((string) ($p['brief'] ?? ''), 0, 200), '/partner', 'warn', $email, 'marketplace-assigned', ['sluzba' => (string) ($p['title'] ?? ''), 'zakaznik' => (string) ($p['customer'] ?? ''), 'termin' => self::when($p['due_at'] ?? null), 'zadani' => mb_substr((string) ($p['brief'] ?? ''), 0, 500), 'url' => "{$portal}/partner"]),
            'marketplace.delivered' => $this->customer($m, 'order', 'Dodáno: '.($p['title'] ?? ''), mb_substr((string) ($p['note'] ?? ''), 0, 200).' · potvrďte převzetí, nebo do '.(int) ($p['auto_accept_days'] ?? 14).' dní reklamujte.', '/panel/nastaveni', 'info', $email, 'marketplace-delivered', ['sluzba' => (string) ($p['title'] ?? ''), 'poznamka' => (string) ($p['note'] ?? ''), 'dni' => (string) (int) ($p['auto_accept_days'] ?? 14), 'url' => "{$portal}/panel/nastaveni"]),
            'marketplace.accepted' => $this->customer($m, 'order', 'Zakázka převzata: '.($p['title'] ?? ''), 'Váš podíl '.$money($p['partner_share'] ?? null).' je připraven k výplatě.', '/partner', 'info'),
            'marketplace.disputed' => $this->both($m, 'order', 'Reklamace z marketplace: '.($p['title'] ?? ''), ($p['customer'] ?? '').' · '.mb_substr((string) ($p['reason'] ?? ''), 0, 200), 'Reklamaci jsme přijali: '.($p['title'] ?? ''), 'Podpora ji posoudí a rozhodne o vrácení kreditu nebo potvrzení dodání.', '/sprava#/money', '/panel/nastaveni', 'warn'),
            'marketplace.refunded' => $this->customer($m, 'order', 'Kredit vrácen: '.($p['title'] ?? ''), $money($p['amount'] ?? null).' je zpět na vašem účtu · '.(string) ($p['reason'] ?? ''), '/panel/fakturace', 'info'),
            'marketplace.renewed' => $this->customer($m, 'order', 'Marketplace: '.($p['title'] ?? '').' prodlouženo', $money($p['total'] ?? null).' z kreditu · do '.self::when($p['period_end'] ?? null).' · doklad '.($p['invoice'] ?? ''), '/panel/fakturace'),
            'marketplace.renewal_failed' => $this->customer($m, 'order', 'Marketplace: '.($p['title'] ?? '').' se nepodařilo prodloužit', 'Chybí kredit '.$money($p['required'] ?? null).'; dobijte do '.self::when($p['grace_until'] ?? null).', jinak služba skončí.', '/panel/fakturace', 'warn'),
            'marketplace.ended' => $this->customer($m, 'order', 'Marketplace: '.($p['title'] ?? '').' skončilo', ($p['reason'] ?? '') === 'unpaid' ? 'Kredit nestačil na další období.' : 'Předplatné skončilo s koncem zaplaceného období.', '/panel/nastaveni', ($p['reason'] ?? '') === 'unpaid' ? 'warn' : 'info'),
            'status_page.domain.verified' => $this->customer($m, 'account', 'Stránka stavu běží na '.($p['domain'] ?? ''), 'Doména je ověřená; certifikát vystaví edge při první návštěvě.', '/panel/nastaveni'),
            'marketplace.overdue' => $this->customer($m, 'order', 'Zakázka po termínu: '.($p['title'] ?? ''), 'Termín byl '.self::when($p['due_at'] ?? null).'; po '.(int) ($p['grace_days'] ?? 7).' dnech může zákazník žádat vrácení bez sporu.', '/partner', 'hot'),
            'marketplace.delayed' => $this->customer($m, 'order', 'Dodání '.($p['title'] ?? '').' se zpozdilo', 'Partner nedodal do '.self::when($p['due_at'] ?? null).'; po '.(int) ($p['grace_days'] ?? 7).' dnech vám nabídneme vrácení kreditu bez sporu.', '/panel/nastaveni', 'warn'),
            'marketplace.refund_offered' => $this->customer($m, 'order', 'Nedodáno v termínu: můžete si vzít kredit zpět', ($p['title'] ?? '').' je '.(int) ($p['days_overdue'] ?? 0).' dní po termínu. Zrušte zakázku v panelu a kredit se vrátí hned, bez sporu.', '/panel/nastaveni', 'warn'),
            'referral.held' => $this->internal($m, 'finance', 'Doporučení k posouzení: skóre '.(int) ($p['score'] ?? 0), implode(', ', (array) ($p['signals'] ?? [])).' · '.($p['referred'] ?? ''), '/sprava#/customers', 'warn'),
            'referral.clawback' => $this->internal($m, 'finance', 'Doporučení vráceno (chargeback)', 'organizace '.($m->organization_id ?? '').' · signály '.implode(', ', (array) ($p['signals'] ?? [])), '/sprava#/money', 'warn'),
            'loyalty.campaign.started' => $this->customer($m, 'account', 'Nová kampaň: '.($p['title'] ?? ''), implode(' · ', (array) ($p['missions'] ?? [])).(! empty($p['until']) ? ' · do '.self::when($p['until']) : ''), '/panel/nastaveni', 'info', ! empty($p['mail']) ? $email : null, ! empty($p['mail']) ? 'loyalty-campaign' : null, ['kampan' => (string) ($p['title'] ?? ''), 'mise' => implode(', ', (array) ($p['missions'] ?? [])), 'do' => ! empty($p['until']) ? self::when($p['until']) : 'odvolání', 'url' => "{$portal}/panel/nastaveni"]),
            'loyalty.campaign.completed' => $this->customer($m, 'account', 'Kampaň splněna: '.($p['title'] ?? ''), 'Odznak '.($p['badge'] ?? '').' je váš.', '/panel/nastaveni', 'info'),
            'marketplace.late_credit' => $this->customer($m, 'order', 'Kredit za pozdní dodání: '.($p['title'] ?? ''), $money($p['credit'] ?? null).' za '.(int) ($p['days_late'] ?? 0).' dní zpoždění je na vašem účtu.', '/panel/fakturace', 'info'),
            'partner.model.requested' => $this->internal($m, 'finance', 'Partner žádá o změnu modelu provize: '.($p['from'] ?? '').' → '.($p['to'] ?? ''), ($p['partner_code'] ?? '').' · '.(string) ($p['note'] ?? ''), '/sprava#/money', 'info'),
            'partner.model.approved' => $this->customer($m, 'partner', 'Změna modelu provize schválena', 'Model '.($p['to'] ?? '').' platí od '.self::when($p['effective_from'] ?? null).'.', '/partner', 'info'),
            'partner.model.rejected' => $this->customer($m, 'partner', 'Změna modelu provize zamítnuta', (string) ($p['note'] ?? ''), '/partner', 'warn'),
            'partner.model.changed' => $this->customer($m, 'partner', 'Model provize se změnil na '.($p['to'] ?? ''), 'Nové provize se počítají podle nového modelu.', '/partner', 'info'),
            'partner.change.requested' => $this->internal($m, 'finance', 'Partner žádá o změnu podmínek: '.self::termName($p['kind'] ?? '').' → '.self::termValue($p['kind'] ?? '', $p['to'] ?? null), ($p['partner_code'] ?? '').' · '.(string) ($p['note'] ?? ''), '/sprava#/money', 'info'),
            'partner.change.approved' => $this->customer($m, 'partner', 'Změna podmínek schválena: '.self::termName($p['kind'] ?? ''), self::termValue($p['kind'] ?? '', $p['to'] ?? null).' platí od '.self::when($p['effective_from'] ?? null).'.', '/partner#/provize', 'info'),
            'partner.change.rejected' => $this->customer($m, 'partner', 'Změna podmínek zamítnuta: '.self::termName($p['kind'] ?? ''), (string) ($p['note'] ?? ''), '/partner#/provize', 'warn'),
            'partner.change.applied' => $this->customer($m, 'partner', self::termName($p['kind'] ?? '').': '.self::termValue($p['kind'] ?? '', $p['to'] ?? null), 'Nová podmínka platí od dnešního dne.', '/partner#/provize', 'info'),
            'partner.payout.auto' => $this->customer($m, 'partner', 'Výplata provize požádána automaticky', $money($p['amount'] ?? null).' · podle vašich výplatních podmínek ('.($p['terms'] ?? '').'); finance ji schválí a odešlou.', '/partner#/vyplaty', 'info'),
            'marketplace.period_delivered' => $this->customer($m, 'order', 'Měsíční plnění dodáno: '.($p['title'] ?? ''), (string) ($p['note'] ?? ''), '/panel/sluzby', 'info'),
            'marketplace.period_due' => $this->customer($m, 'partner', 'Měsíční plnění ještě není odevzdané: '.($p['title'] ?? ''), 'Období končí '.self::when($p['period_end'] ?? null).'; bez odevzdání dostane zákazník kredit '.(int) ($p['credit_pct'] ?? 0).' % z vašeho podílu.', '/partner#/marketplace', 'warn'),
            'marketplace.period_missed' => $this->customer($m, 'order', 'Kredit za chybějící měsíční plnění: '.($p['title'] ?? ''), $money($p['credit'] ?? null).' je na vašem účtu; období do '.self::when($p['period_end'] ?? null).' zůstalo bez dodávky.', '/panel/fakturace', 'info'),
            'marketplace.period_missed_partner' => $this->customer($m, 'partner', 'Období bez plnění: '.($p['title'] ?? ''), 'Zákazník dostal kredit '.$money($p['credit'] ?? null).'; váš podíl za nové období je o něj nižší.', '/partner#/marketplace', 'warn'),
            'capacity.request.proposed' => $this->internal($m, 'infra', 'Návrh nákupu uzlu: '.($p['role'] ?? '').' '.($p['region'] ?? ''), ((int) round(((int) ($p['wanted']['ram_mb'] ?? 0)) / 1024)).' GB RAM · '.($p['days_left'] !== null ? 'zbývá '.(int) $p['days_left'].' dní' : 'bez rezervy').(! empty($p['vendor']) ? ' · dodavatel '.$p['vendor'] : ' · ruční nákup'), '/sprava#/fleet', 'warn'),
            'capacity.request.ordered' => $this->internal($m, 'infra', 'Uzel objednán: '.($p['node_name'] ?? ''), ($p['vendor'] ?? '').' · '.($p['ip'] ?? '').' · po instalaci přepněte uzel na active', '/sprava#/fleet', 'info'),
            'capacity.request.delivered' => $this->internal($m, 'infra', 'Uzel dodán: '.($p['node_name'] ?? ($p['role'] ?? '')), 'Fond '.($p['role'] ?? '').' '.($p['region'] ?? '').' má novou kapacitu.', '/sprava#/fleet', 'info'),
            'capacity.request.failed' => $this->internal($m, 'infra', 'Objednávka uzlu selhala: '.($p['role'] ?? '').' '.($p['region'] ?? ''), (string) ($p['error'] ?? ''), '/sprava#/fleet', 'hot'),
            'partner.change.auto_approved' => $this->internal($m, 'finance', 'Smluvní změna schválena automaticky: '.self::termName($p['kind'] ?? '').' → '.self::termValue($p['kind'] ?? '', $p['to'] ?? null), ($p['partner_code'] ?? '').' · '.($p['reason'] ?? '').' · platí od '.self::when($p['effective_from'] ?? null), '/sprava#/money', 'info'),
            // on-call (audit §5q-1): the escalation is the loud one; acknowledgements and resolutions keep the trail
            'oncall.alert.escalated' => $this->internal($m, 'infra', 'Eskalace on-call '.(int) ($p['escalations'] ?? 0).': '.($p['title'] ?? ''), (! empty($p['final']) ? 'poslední eskalace, pager už nevolá · ' : '').'nikdo nepotvrdil'.(! empty($p['paged']) ? ' · pager '.($p['provider'] ?? '') : ' · bez pageru'), '/sprava#/incidents', 'hot'),
            'oncall.alert.acknowledged' => $this->internal($m, 'infra', 'On-call potvrzen: '.($p['title'] ?? ''), 'potvrdil '.($p['acked_by'] ?? ''), '/sprava#/incidents', 'info'),
            'oncall.alert.resolved' => $this->internal($m, 'infra', 'On-call vyřešen: '.($p['title'] ?? ''), 'vyřešil '.($p['resolved_by'] ?? ''), '/sprava#/incidents', 'info'),
            'game.setup.attention' => $this->customer($m, 'service', 'Herní server '.($p['label'] ?? '').' potřebuje nastavení', 'Doplňte v záložce Startup platnou hodnotu: '.implode(', ', (array) ($p['variables'] ?? [])).'. Bez ní server nenastartuje.', '/panel/sluzby', 'warn'), // §5t-3
            'oncall.shift.starting' => $this->user($m, 'security.mfa', 'Za hodinu začíná vaše on-call směna', 'Od '.($p['starts'] ?? '').' do '.($p['ends'] ?? '').' držíte pager; alerty najdete v konzoli.', '/sprava', 'info', 'oncall-shift', ['od' => $p['starts'] ?? '', 'do' => $p['ends'] ?? '']), // §5t-4
            'game.operator_variables.stale' => $this->internal($m, 'security', 'Proměnné provozovatele herních šablon jsou staré '.(int) ($p['days'] ?? 0).' dní', 'Vyměňte heslo účtu ('.implode(', ', (array) ($p['names'] ?? [])).') a uložte nové v konzoli Šablony her → Proměnné provozovatele.', '/sprava#/geggs', 'warn'), // §5u-5
            'game.template.missing' => $this->internal($m, 'infra', 'Herní šablona zmizela z panelu: '.($p['template'] ?? ''), ($p['instance'] ?? '').' · egg #'.(int) ($p['egg'] ?? 0).' '.($p['name'] ?? '').' · šablona se neprodává, dokud ji znovu nenamapujete', '/sprava#/geggs', 'warn'), // §5s
            'files.infected' => $this->internal($m, 'security', 'Zachycen infikovaný soubor: '.($p['name'] ?? ''), ($p['malware'] ?? 'neznámý podpis').' · '.($p['subject'] ?? '').' '.$m->aggregate_id.' · soubor smazán', '/sprava#/security', 'hot'), // §5r-4
            'capacity.budget.forecast_over' => $this->internal($m, 'finance', 'Kapacita příští měsíc přesáhne rozpočet', 'odhad nákupu '.($p['total'] ?? '').' proti limitu '.($p['budget'] ?? '').' · '.(int) ($p['nodes'] ?? 0).' uzlů podle trendu', '/sprava#/nodecost', 'warn'), // §5r-5
            'capacity.budget.exceeded' => $this->internal($m, 'finance', 'Rozpočet kapacity vyčerpán: '.($p['role'] ?? '').' '.($p['region'] ?? ''), 'objednávka uzlu za '.($p['cost'] ?? '').' by překročila měsíční limit '.($p['budget'] ?? '').' (utraceno '.($p['spent'] ?? '').') · schválení nad limit v konzoli kapacity', '/sprava#/nodecost', 'hot'),
            'node.bmc.alert' => $this->internal($m, 'infra', 'Hardware hlásí problém: '.($p['node'] ?? ''), implode(' · ', (array) ($p['problems'] ?? [])).' · '.($p['role'] ?? '').' '.($p['region'] ?? ''), '/sprava#/fleet', 'hot'),
            'capacity.request.activated' => $this->internal($m, 'infra', 'Uzel je aktivní: '.($p['node_name'] ?? ($p['role'] ?? '')), 'Playbook doběhl · '.(string) ($p['report']['ip'] ?? $p['ip'] ?? '').' · fond '.($p['role'] ?? '').' '.($p['region'] ?? '').' má novou kapacitu.', '/sprava#/nodecost', 'info'),
            'integration.prereqs.regressed' => $this->internal($m, 'infra', 'Noční kontrola integrace: '.($p['key'] ?? '').' se zhoršila', ($p['api'] !== 'up' ? 'API neodpovídá · ' : '').implode(' · ', (array) ($p['appeared'] ?? [])), '/sprava/nastaveni/integrace', 'warn'),
            'integration.prereqs.recovered' => $this->internal($m, 'infra', 'Noční kontrola integrace: '.($p['key'] ?? '').' je zase v pořádku', implode(' · ', (array) ($p['cleared'] ?? [])), '/sprava/nastaveni/integrace', 'info'),
            'integration.version.held' => $this->internal($m, 'infra', 'Panel '.($p['key'] ?? '').' běží na neověřené verzi '.($p['version'] ?? '').' a nebere nové objednávky', (string) ($p['why'] ?? '').(empty($p['previous']) ? '' : ' · dříve '.$p['previous']).' · stávající služby se spravují dál · onhost:integrations:versions', '/sprava/nastaveni/integrace', 'hot'),
            'integration.version.verified' => $this->internal($m, 'infra', 'Panel '.($p['key'] ?? '').' ověřen na verzi '.($p['version'] ?? ''), (empty($p['previous']) ? '' : 'dříve '.$p['previous'].' · ').'kontroly prošly, nové objednávky tam jdou', '/sprava/nastaveni/integrace', 'info'),
            'integration.version.accepted' => $this->internal($m, 'infra', 'Verze '.($p['version'] ?? '').' panelu '.($p['key'] ?? '').' přijata operátorem', (string) ($p['reason'] ?? '').' · '.(string) ($p['by'] ?? ''), '/sprava/nastaveni/integrace', 'info'),
            'capacity.request.ready' => $this->internal($m, 'infra', 'Uzel je připraven na instalaci: '.($p['node_name'] ?? ($p['role'] ?? '')), (string) ($p['report']['ip'] ?? $p['ip'] ?? '').' · '.(string) ($p['report']['os'] ?? '').' · '.(string) ($p['playbook'] ?? ''), '/sprava#/nodecost', 'info'),
            'capacity.forecast.low' => $this->internal($m, 'infra', 'Kapacita dochází: '.($p['role'] ?? '').' '.($p['region'] ?? ''), ($p['days_left'] !== null ? 'zbývá '.(int) $p['days_left'].' dní' : 'bez rezervy').' · rezerva '.(int) round(((int) ($p['headroom_mb'] ?? 0)) / 1024).' GB · růst '.(int) round(((int) ($p['growth_mb_per_day'] ?? 0)) / 1024).' GB/den', '/sprava#/fleet', 'hot'),
            'marketplace.listing.submitted' => $this->internal($m, 'partner', 'Nová nabídka na marketplace: '.($p['title'] ?? ''), ($p['key'] ?? '').' · '.$money($p['price'] ?? null).' · čeká na zveřejnění', '/sprava#/money', 'info'),
            'marketplace.listing.published' => $this->customer($m, 'partner', 'Nabídka zveřejněna: '.($p['title'] ?? ''), 'Zákazníci ji vidí v marketplace.', '/partner', 'info'),
            'marketplace.listing.retired' => $this->customer($m, 'partner', 'Nabídka stažena: '.($p['title'] ?? ''), (string) ($p['reason'] ?? ''), '/partner', 'warn'),
            // referrals and missions (audit §5j-2, §5j-3)
            'referral.registered' => $this->customer($m, 'account', 'Na vaše doporučení se registroval nový zákazník', 'Odměnu připíšeme po jeho první zaplacené platbě.', '/panel/nastaveni'),
            'referral.rewarded' => $this->customer($m, 'account', 'Odměna za doporučení', '+'.(int) ($p['points'] ?? 0).' bodů a '.$money($p['credit'] ?? null).' promo kreditu za '.($p['referred'] ?? ''), '/panel/nastaveni', 'info', $email, 'referral-rewarded', ['body' => (string) (int) ($p['points'] ?? 0), 'kredit' => $money($p['credit'] ?? null), 'url' => "{$portal}/panel/nastaveni"]),
            'referral.welcomed' => $this->customer($m, 'account', 'Uvítací odměna za doporučení', '+'.(int) ($p['points'] ?? 0).' bodů a '.$money($p['credit'] ?? null).' promo kreditu.', '/panel/nastaveni'),
            'referral.refused' => $this->internal($m, 'finance', 'Doporučení zamítnuto: '.($p['reason'] ?? ''), 'organizace '.($m->organization_id ?? '').' · '.($p['referred_organization_id'] ?? ''), '/sprava#/customers', 'info'),
            'loyalty.mission.completed' => $this->customer($m, 'account', 'Splněné mise za '.($p['month'] ?? ''), implode(', ', (array) ($p['missions'] ?? [])).(! empty($p['all']) ? ' · všechny mise měsíce, odznak je váš' : ''), '/panel/nastaveni'),
            'loyalty.streak.reached' => $this->both($m, 'finance', 'Věrnostní série dosažena: '.($org?->name ?? ''), (int) ($p['months'] ?? 0).' měsíců plateb včas · navrhovaná trvalá sleva '.($p['discount_pct'] ?? 0).' % čeká na schválení', (int) ($p['months'] ?? 0).' měsíců plateb včas', 'Děkujeme. Finance posoudí trvalou věrnostní slevu na vaše další objednávky.', '/sprava#/customers', '/panel/nastaveni', 'info', $email, 'loyalty-streak', ['mesice' => (string) (int) ($p['months'] ?? 0), 'url' => "{$portal}/panel/nastaveni"]),
            'loyalty.discount.granted' => $this->customer($m, 'account', 'Trvalá věrnostní sleva '.($p['percent'] ?? 0).' %', 'Platí na každou další objednávku.', '/panel/nastaveni', 'info'),
            'loyalty.discount.removed' => $this->customer($m, 'account', 'Věrnostní sleva ukončena', 'Nové objednávky jsou za ceníkové ceny.', '/panel/nastaveni', 'warn'),
            // operations (audit §5j-4, §5j-6, §5j-9)
            'rebalance.plan' => $this->internal($m, 'infra', 'Noční plán přerozdělení: '.(int) ($p['moves'] ?? 0).' přesunů ('.($p['basis'] ?? 'usage').')', 'horké uzly: '.implode(', ', (array) ($p['hot'] ?? [])).' · '.implode(' · ', (array) ($p['summary'] ?? [])), '/sprava#/fleet', (int) ($p['moves'] ?? 0) > 0 ? 'warn' : 'info'),
            'chargeback.cluster' => $this->internal($m, 'finance', 'Odchody zákazníků se hromadí: '.($p['label'] ?? ''), (int) ($p['count'] ?? 0).'× · téma '.($p['theme'] ?? '').' · incident '.($p['number'] ?? ''), '/sprava#/incidents', 'hot'),
            'tenant.sandbox' => $this->customer($m, 'account', ! empty($p['enabled']) ? 'Účet je v režimu sandbox' : 'Režim sandbox ukončen', ! empty($p['enabled']) ? 'Služby se zřizují v laboratorním prostředí; kredit '.$money(['minor' => (int) ($p['credit'] ?? 0), 'currency' => $org?->currency ?? 'CZK']).' je určen k testování.' : 'Nové objednávky jdou do produkce.', '/panel/nastaveni'),
            default => null,
        };
    }

    private function customer(OutboxMessage $m, string $kind, string $title, string $body, string $surface, string $severity = 'info', ?string $mailTo = null, ?string $template = null, array $vars = []): void
    {
        $organization = $m->organization_id !== null ? Organization::query()->find($m->organization_id) : null;
        $this->notifications->notify('customer', $kind, $title, $body, $surface, $m->organization_id, null, $m->aggregate_type, $m->aggregate_id, $m->name, $severity, $organization?->locale ?? 'cs'); // §5q-7
        if ($template !== null && $mailTo) {
            // Organization mails honour the owner's preferences; mandatory templates ignore them (NotificationService).
            $this->notifications->queueMail($template, $mailTo, $vars, $m->aggregate_type, $m->aggregate_id, $m->organization_id, $organization?->locale ?? 'cs', $organization?->owner_user_id);
        }
    }

    private function internal(OutboxMessage $m, string $kind, string $title, string $body, string $surface, string $severity = 'info'): void
    {
        $this->notifications->notify('internal', $kind, $title, $body, $surface, $m->organization_id, null, $m->aggregate_type, $m->aggregate_id, $m->name, $severity);
    }

    private function both(OutboxMessage $m, string $kind, string $internalTitle, string $internalBody, string $customerTitle, string $customerBody, string $internalSurface, string $customerSurface, string $severity = 'info', ?string $mailTo = null, ?string $template = null, array $vars = []): void
    {
        $this->internal($m, $kind, $internalTitle, $internalBody, $internalSurface, $severity);
        $this->customer($m, $kind, $customerTitle, $customerBody, $customerSurface, $severity, $mailTo, $template, $vars);
    }

    /** User-addressed security events: aggregate is the user, mail goes to that user. */
    private function user(OutboxMessage $m, string $kind, string $title, string $body, string $surface, string $severity, string $template, array $vars): void
    {
        $user = User::query()->find($m->aggregate_id);
        $this->notifications->notify('customer', $kind, $title, $body, $surface, $m->organization_id, $user?->id, 'user', $m->aggregate_id, $m->name, $severity, $user?->locale ?? 'cs'); // §5q-7
        if ($user !== null) {
            $this->notifications->queueMail($template, $user->email, $vars + ['jmeno' => $user->name], 'user', $user->id, $m->organization_id, $user->locale ?? 'cs', $user->id);
        }
    }

    private function mailOnly(OutboxMessage $m, string $to, string $template, array $vars, string $locale): void
    {
        if ($to !== '') {
            $this->notifications->queueMail($template, $to, $vars, $m->aggregate_type, $m->aggregate_id, $m->organization_id, $locale);
        }
    }

    private function access(array $p): string
    {
        $access = (array) ($p['access'] ?? []);
        $parts = [];
        foreach (['domain', 'ipv4', 'ipv6', 'address', 'namespace'] as $key) {
            if (! empty($access[$key])) {
                $parts[] = "{$key}: {$access[$key]}";
            }
        }

        return implode(' · ', $parts);
    }

    private static function internalKind(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'registrar.'), str_starts_with($event, 'domain.') => 'domain',
            str_starts_with($event, 'integration.'), str_starts_with($event, 'provisioning.'), str_starts_with($event, 'operation.'), str_starts_with($event, 'capacity.'), str_starts_with($event, 'ipam.') => 'infra',
            str_starts_with($event, 'security.'), str_starts_with($event, 'abuse.'), str_starts_with($event, 'compliance.') => 'security',
            str_starts_with($event, 'sla.'), str_starts_with($event, 'maintenance.') => 'infra',
            str_starts_with($event, 'node.'), str_starts_with($event, 'service.'), str_starts_with($event, 'platform.') => 'infra', // they used to land under finance
            default => 'finance',
        };
    }

    /** An ISO timestamp as the customer reads it (Prague time, `13. 9. 2026 17:00`); empty when unknown. */
    private static function termName(string $kind): string
    {
        return ['model' => 'model provize', 'rate_lock' => 'zámek sazby', 'payout_terms' => 'výplatní podmínky', 'whitelabel_scope' => 'rozsah white-labelu'][$kind] ?? $kind;
    }

    private static function termValue(string $kind, mixed $value): string
    {
        $v = (string) ($value ?? '');

        return match ($kind) {
            'model' => ['share' => 'podíl z objemu', 'oneoff' => 'jednorázově + bonus'][$v] ?? $v,
            'rate_lock' => is_numeric($v) ? $v.' měsíců' : $v,
            'payout_terms' => ['on_request' => 'na vyžádání', 'monthly' => 'měsíčně', 'quarterly' => 'čtvrtletně'][$v] ?? $v,
            'whitelabel_scope' => ['basic' => 'základní', 'full' => 'plný'][$v] ?? $v,
            default => $v,
        };
    }

    private static function when(mixed $iso): string
    {
        if (! is_string($iso) || $iso === '') {
            return '—';
        }
        try {
            return CarbonImmutable::parse($iso)->setTimezone((string) config('app.timezone', 'Europe/Prague'))->format('j. n. Y H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }

    private static function internalTitle(string $event, array $p): string
    {
        return match ($event) {
            'catalog.plan.version_published' => 'Nová verze tarifu '.($p['product'] ?? '').'/'.($p['plan'] ?? '').': v'.(int) ($p['version'] ?? 0).' v prodeji',
            'catalog.plan.version_activated' => 'Tarif '.($p['product'] ?? '').'/'.($p['plan'] ?? '').' se prodává ve verzi '.(int) ($p['version'] ?? 0).' (dříve '.(int) ($p['previous_version'] ?? 0).')',
            'platform.mail.failing' => 'Pošta platformy neodchází: '.(int) ($p['errors'] ?? 0).' chyb, '.(int) ($p['waiting'] ?? 0).' čeká'.((int) ($p['oldest_minutes'] ?? 0) > 0 ? ' až '.(int) $p['oldest_minutes'].' min' : '').' · upomínky se nevymáhají',
            'platform.mail.recovered' => 'Pošta platformy opět odchází'.((int) ($p['requeued'] ?? 0) > 0 ? ' · '.(int) $p['requeued'].' vzdaných zpráv zařazeno znovu' : ''),
            'registrar.credit.low' => 'Kredit registrátora je nízký',
            'integration.down' => 'Integrace nedostupná: '.($p['key'] ?? ''),
            'integration.maintenance.lifted' => 'Servisní režim ukončen, instance opět v provozu: '.($p['key'] ?? ''),
            'integration.maintenance.overdue' => 'Servisní režim vypršel, panel stále neodpovídá: '.($p['key'] ?? ''),
            'security.ssh_key.revocation.stuck' => 'Odvolání SSH klíče se nedaří dokončit, klíč může stále fungovat: '.($p['account'] ?? ''),
            'platform.load_shedding.started' => 'Přetížení: přehledy a statistiky jsou pozastavené, operace mají přednost',
            'platform.load_shedding.ended' => 'Přetížení pominulo: přehledy a statistiky opět běží',
            'platform.queue.stalled' => 'Fronta úloh stojí: worker se neozval'.(! empty($p['last_seen_at']) ? ' od '.substr((string) $p['last_seen_at'], 11, 5) : ' nikdy'),
            'platform.queue.backlog' => 'Fronta operací se hromadí: '.(int) ($p['stale'] ?? 0).' čeká déle než '.(int) ($p['age_minutes'] ?? 5).' min (práh '.(int) ($p['threshold'] ?? 0).')',
            'node.drained' => 'Uzel '.($p['name'] ?? '').' odstaven z umísťování'.(! empty($p['automatic']) ? ' (automaticky)' : ''),
            'node.resumed' => 'Uzel '.($p['name'] ?? '').' opět přijímá služby',
            'node.qualified' => 'Uzel '.($p['name'] ?? '').' je kvalifikovaný a v nabídce'.(empty($p['exception']) ? '' : ' (s výjimkou)'),
            'node.synthetic.leftover' => 'Na uzlu '.($p['name'] ?? '').' zůstal zkušební zdroj '.($p['leftover'] ?? ''),
            'service.relocated' => 'Virtuál služby '.($p['name'] ?? '').' běží na uzlu '.($p['to'] ?? '').' místo '.($p['from'] ?? '').'; vazba ho následuje',
            'capacity.unavailable' => 'Kapacita vyčerpána ('.($p['role'] ?? '').')',
            'service.backup.schedule.stalled' => 'Plánovaná záloha se opakovaně nespustila',
            'service.backup.schedule.paused' => 'Plánování záloh se zastavilo po opakovaném selhání',
            'service.database.import.failed' => 'Import databáze se nedokončil',
            'service.restore_test.failed' => 'Test obnovy ze zálohy neprošel',
            'ipam.exhausted' => 'Došly IP adresy '.($p['region'] ?? '').'/'.($p['purpose'] ?? ''),
            'ipam.rdns.unpublished' => 'Reverzní zónu pro adresu platforma nespravuje',
            'ipam.rdns.failed' => 'Reverzní záznam se nepodařilo publikovat',
            'ipam.threshold' => 'IP pool pod 10 %',
            'provisioning.drift.detected' => 'Drift: '.($p['field'] ?? '').' ('.($p['classification'] ?? '').')',
            'operation.failed' => 'Operace selhala: '.($p['kind'] ?? ''),
            'finance.reconciliation.mismatch' => 'Nesoulad v rekonciliaci plateb',
            'registrar.notification.dead' => 'Notifikace registrátora nešla zpracovat',
            'domain.reconcile.missing_remote' => 'Doména chybí u registrátora: '.($p['fqdn'] ?? ''),
            'domain.reconcile.unknown_remote' => 'Neznámá doména u registrátora: '.($p['fqdn'] ?? ''),
            'payment.orphan_callback' => 'Platba bez záznamu ('.($p['provider'] ?? '').')',
            'security.incident.opened' => 'Bezpečnostní incident otevřen',
            'abuse.case.opened' => 'Nové abuse hlášení',
            'compliance.timer.due' => 'Regulatorní lhůta: '.($p['timer'] ?? ''),
            'compliance.timer.missed' => 'ZMEŠKANÁ regulatorní lhůta: '.($p['timer'] ?? ''),
            'sla.burn_rate' => 'SLO burn rate '.($p['name'] ?? '').' ('.($p['short'] ?? '').'/'.($p['long'] ?? '').')',
            'sla.budget.exhausted' => 'Error budget vyčerpán: '.($p['name'] ?? '').' — change freeze',
            'maintenance.unapproved' => 'Údržba bez schválení: '.($p['number'] ?? ''),
            default => $event,
        };
    }

    private static function internalSurface(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'registrar.'), str_starts_with($event, 'domain.') => '/sprava/sluzby',
            str_starts_with($event, 'integration.'), str_starts_with($event, 'provisioning.'), str_starts_with($event, 'operation.'), str_starts_with($event, 'capacity.'), str_starts_with($event, 'ipam.') => '/sprava/uzly',
            str_starts_with($event, 'security.'), str_starts_with($event, 'abuse.'), str_starts_with($event, 'compliance.'), str_starts_with($event, 'sla.'), str_starts_with($event, 'maintenance.') => '/sprava/incidenty',
            str_starts_with($event, 'node.'), str_starts_with($event, 'platform.') => '/sprava/uzly',
            str_starts_with($event, 'service.') => '/sprava/sluzby',
            default => '/sprava/fakturace',
        };
    }
}
