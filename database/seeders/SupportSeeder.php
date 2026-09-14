<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\SupportQueue;

/** Support queues, skills, SLA policies and macros (blueprint §68.4–68.6). Idempotent. */
final class SupportSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            'standard' => ['name' => 'Standard', 'targets' => ['p1' => ['ack' => 15, 'first' => 30, 'next' => 120, 'resolve' => 480], 'p2' => ['ack' => 30, 'first' => 60, 'next' => 240, 'resolve' => 1440], 'p3' => ['ack' => 60, 'first' => 240, 'next' => 480, 'resolve' => 4320], 'p4' => ['ack' => 120, 'first' => 480, 'next' => 1440, 'resolve' => 10080]], 'business_hours_only' => false],
            'business' => ['name' => 'Business', 'targets' => ['p1' => ['ack' => 10, 'first' => 15, 'next' => 60, 'resolve' => 240], 'p2' => ['ack' => 15, 'first' => 30, 'next' => 120, 'resolve' => 720], 'p3' => ['ack' => 30, 'first' => 120, 'next' => 240, 'resolve' => 2880], 'p4' => ['ack' => 60, 'first' => 240, 'next' => 480, 'resolve' => 7200]], 'business_hours_only' => false],
            'ha' => ['name' => 'HA / Enterprise', 'targets' => ['p1' => ['ack' => 5, 'first' => 15, 'next' => 30, 'resolve' => 120], 'p2' => ['ack' => 10, 'first' => 30, 'next' => 60, 'resolve' => 480], 'p3' => ['ack' => 30, 'first' => 60, 'next' => 120, 'resolve' => 1440], 'p4' => ['ack' => 60, 'first' => 120, 'next' => 240, 'resolve' => 4320]], 'business_hours_only' => false],
        ];
        foreach ($policies as $key => $p) {
            SlaPolicy::query()->updateOrCreate(['key' => $key], ['name' => $p['name'], 'targets' => $p['targets'], 'business_hours_only' => $p['business_hours_only'], 'business_hours' => ['days' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '18:00', 'tz' => 'Europe/Prague']]);
        }
        $queues = [
            'l1' => ['name' => 'L1 — první linie', 'skills' => ['GENERAL', 'BILLING', 'ORDERS'], 'escalates_to' => 'l2'],
            'l2' => ['name' => 'L2 — technická podpora', 'skills' => ['WORDPRESS', 'ISPCONFIG', 'AAPANEL', 'MAIL_DELIVERABILITY', 'DNS', 'BACKUP'], 'escalates_to' => 'l3'],
            'l3' => ['name' => 'L3 — infrastruktura', 'skills' => ['PROXMOX', 'RKE2', 'NETWORK', 'STORAGE', 'DNSSEC', 'DOMAIN_WAPI'], 'escalates_to' => null],
            'billing' => ['name' => 'Fakturace', 'skills' => ['BILLING', 'TAX', 'GDPR'], 'escalates_to' => 'l2'],
            'domains' => ['name' => 'Domény a DNS', 'skills' => ['DOMAIN_WAPI', 'DNS', 'DNSSEC'], 'escalates_to' => 'l3'],
            'games' => ['name' => 'Herní servery', 'skills' => ['GAME_MINECRAFT', 'GAME_CS2', 'PTERODACTYL'], 'escalates_to' => 'l3'],
            'security' => ['name' => 'Bezpečnost a abuse', 'skills' => ['SECURITY', 'ABUSE', 'GDPR'], 'escalates_to' => null],
        ];
        foreach ($queues as $key => $q) {
            SupportQueue::query()->updateOrCreate(['key' => $key], ['name' => $q['name'], 'skills' => $q['skills'], 'escalates_to' => $q['escalates_to'], 'state' => 'active']);
        }
        $macros = [
            'ack-investigating' => ['name' => 'Prověřujeme', 'category' => 'general', 'body' => ['cs' => 'Děkujeme za hlášení, požadavek prověřujeme a ozveme se s výsledkem.', 'en' => 'Thank you for the report, we are looking into it and will get back with the result.'], 'actions' => []],
            'need-more-info' => ['name' => 'Potřebujeme doplnění', 'category' => 'general', 'body' => ['cs' => 'Abychom mohli pokračovat, pošlete nám prosím přesný čas, doménu/službu a případnou chybovou hlášku.', 'en' => 'To continue, please send the exact time, the domain/service and any error message.'], 'actions' => ['state' => 'WAITING_CUSTOMER']],
            'resolved-confirm' => ['name' => 'Vyřešeno — potvrzení', 'category' => 'general', 'body' => ['cs' => 'Problém je vyřešen. Pokud se cokoliv objeví znovu, stačí odpovědět na tento tiket a otevře se znovu.', 'en' => 'The issue is resolved. If anything comes back, just reply to this ticket and it reopens.'], 'actions' => ['state' => 'RESOLVED']],
            'billing-invoice-where' => ['name' => 'Kde najdu doklad', 'category' => 'billing', 'body' => ['cs' => 'Doklady (PDF i UBL) jsou v klientské sekci → Fakturace. Účtenka k dobití se vystavuje automaticky po přijetí platby.', 'en' => 'Documents (PDF and UBL) are in the customer panel → Billing. The receipt for a top-up is issued automatically once the payment arrives.'], 'actions' => []],
        ];
        foreach ($macros as $key => $m) {
            SupportMacro::query()->updateOrCreate(['key' => $key], ['name' => $m['name'], 'category' => $m['category'], 'body' => $m['body'], 'actions' => $m['actions']]);
        }
    }
}
