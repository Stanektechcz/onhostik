<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Dns\Models\DnsTemplate;

/** Global ONhost zone templates (blueprint §48.1). Placeholders are filled from the hosting service or left out. */
final class DnsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'web_basic' => ['name' => ['cs' => 'Web (základní)', 'en' => 'Website (basic)'], 'records' => [
                ['name' => '@', 'type' => 'A', 'content' => '{ipv4}', 'ttl' => 3600],
                ['name' => '@', 'type' => 'AAAA', 'content' => '{ipv6}', 'ttl' => 3600],
                ['name' => 'www', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600],
                ['name' => '@', 'type' => 'CAA', 'content' => '0 issue "letsencrypt.org"', 'ttl' => 3600],
            ]],
            'web_mail' => ['name' => ['cs' => 'Web + e-mail ONhost', 'en' => 'Website + ONhost mail'], 'records' => [
                ['name' => '@', 'type' => 'A', 'content' => '{ipv4}', 'ttl' => 3600],
                ['name' => '@', 'type' => 'AAAA', 'content' => '{ipv6}', 'ttl' => 3600],
                ['name' => 'www', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600],
                ['name' => '@', 'type' => 'MX', 'content' => '{mail_host}.', 'ttl' => 3600, 'prio' => 10, 'protected' => true],
                ['name' => 'mail', 'type' => 'CNAME', 'content' => '{mail_host}.', 'ttl' => 3600],
                ['name' => 'autoconfig', 'type' => 'CNAME', 'content' => '{mail_host}.', 'ttl' => 3600],
                ['name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 mx include:{spf_include} -all', 'ttl' => 3600],
                ['name' => '_dmarc', 'type' => 'TXT', 'content' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@{domain}', 'ttl' => 3600],
                ['name' => '{dkim_selector}._domainkey', 'type' => 'TXT', 'content' => 'v=DKIM1; k=rsa; p={dkim}', 'ttl' => 3600],
                ['name' => '@', 'type' => 'CAA', 'content' => '0 issue "letsencrypt.org"', 'ttl' => 3600],
            ]],
            'parking' => ['name' => ['cs' => 'Parkování', 'en' => 'Parking'], 'records' => [
                ['name' => '@', 'type' => 'A', 'content' => '{parking_ipv4}', 'ttl' => 3600],
                ['name' => 'www', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 3600],
                ['name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 -all', 'ttl' => 3600],
                ['name' => '_dmarc', 'type' => 'TXT', 'content' => 'v=DMARC1; p=reject', 'ttl' => 3600],
            ]],
            'vps' => ['name' => ['cs' => 'VPS', 'en' => 'VPS'], 'records' => [
                ['name' => '@', 'type' => 'A', 'content' => '{ipv4}', 'ttl' => 600],
                ['name' => '@', 'type' => 'AAAA', 'content' => '{ipv6}', 'ttl' => 600],
                ['name' => 'www', 'type' => 'CNAME', 'content' => '{domain}.', 'ttl' => 600],
            ]],
            'external' => ['name' => ['cs' => 'Externí hosting (prázdná zóna)', 'en' => 'External hosting (empty zone)'], 'records' => []],
        ];
        foreach ($templates as $key => $t) {
            DnsTemplate::query()->updateOrCreate(['key' => $key, 'organization_id' => null], ['name' => $t['name'], 'records' => $t['records']]);
        }
    }
}
