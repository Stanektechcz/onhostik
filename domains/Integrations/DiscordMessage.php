<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations;

/**
 * Platform events rendered as Discord embeds for customer webhooks that point at discord.com. Titles are the same
 * ones the panel shows; colours follow severity. Unknown events still arrive, as their name with the payload keys.
 */
final class DiscordMessage
{
    private const GREEN = 0x2ECC71;

    private const RED = 0xE74C3C;

    private const AMBER = 0xF39C12;

    private const GREY = 0x95A5A6;

    private const BLUE = 0x3498DB;

    public static function isDiscordUrl(string $url): bool
    {
        return preg_match('#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9_-]+#', $url) === 1;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed> the JSON body Discord expects
     */
    public static function build(string $event, array $payload, ?string $createdAt, string $portal): array
    {
        $p = (array) ($payload['payload'] ?? $payload);
        [$title, $description, $color] = self::describe($event, $p);
        $fields = [];
        foreach (['number', 'domain', 'url', 'ref', 'sha', 'release', 'error', 'reason', 'total', 'amount', 'product_key', 'staging_service_id', 'minutes'] as $key) {
            if (isset($p[$key]) && $p[$key] !== '' && $p[$key] !== null && ! is_array($p[$key])) {
                $fields[] = ['name' => $key, 'value' => mb_substr((string) $p[$key], 0, 200), 'inline' => strlen((string) $p[$key]) < 40];
            }
        }
        if (isset($p['total']) && is_array($p['total']) && isset($p['total']['minor'], $p['total']['currency'])) {
            $fields[] = ['name' => 'total', 'value' => number_format(((int) $p['total']['minor']) / 100, 2, ',', ' ').' '.$p['total']['currency'], 'inline' => true];
        }

        return ['username' => 'ONhost', 'embeds' => [array_filter([
            'title' => mb_substr($title, 0, 250), 'description' => mb_substr($description, 0, 1500), 'color' => $color, 'url' => $portal.'/panel', 'fields' => array_slice($fields, 0, 8), 'timestamp' => $createdAt,
            'footer' => ['text' => 'ONhost · '.$event],
        ], fn ($v) => $v !== null && $v !== [] && $v !== '')]];
    }

    /** @return array{0:string,1:string,2:int} title, description and colour of an event (shared with the Slack and Teams formats) */
    public static function describe(string $event, array $p): array
    {
        $domain = (string) ($p['domain'] ?? $p['url'] ?? $p['hostname'] ?? '');

        return match (true) {
            $event === 'monitoring.down' => ['🔴 Web neodpovídá', ($p['url'] ?? '').' · '.($p['error'] ?? ''), self::RED],
            $event === 'monitoring.up' => ['🟢 Web opět běží', ($p['url'] ?? '').' · výpadek '.(int) ($p['minutes'] ?? 0).' min', self::GREEN],
            $event === 'deploy.started' => ['🚀 Deploy spuštěn', (string) ($p['ref'] ?? ''), self::BLUE],
            $event === 'deploy.succeeded' => ['✅ Deploy dokončen', ($p['ref'] ?? '').' · '.substr((string) ($p['sha'] ?? ''), 0, 7), self::GREEN],
            $event === 'deploy.failed' => ['❌ Deploy selhal', (string) ($p['error'] ?? ''), self::RED],
            str_starts_with($event, 'staging.') => ['🧪 Staging: '.substr($event, 8), $domain, $event === 'staging.failed' ? self::RED : self::BLUE],
            str_starts_with($event, 'import.') => ['📦 Import webu: '.substr($event, 7), (string) ($p['error'] ?? ''), $event === 'import.failed' ? self::RED : self::GREEN],
            str_starts_with($event, 'certificate.') => ['🔐 Certifikát: '.substr($event, 12), implode(', ', (array) ($p['domains'] ?? [])).' '.($p['error'] ?? ''), $event === 'certificate.failed' ? self::RED : self::GREEN],
            str_starts_with($event, 'cdn.') => ['🌐 CDN: '.substr($event, 4), $domain, self::BLUE],
            str_starts_with($event, 'backup.') => ['💾 Záloha: '.substr($event, 7), (string) ($p['reason'] ?? ''), self::GREY],
            $event === 'service.activated' => ['✅ Služba je aktivní', (string) ($p['product_key'] ?? ''), self::GREEN],
            $event === 'service.suspended' => ['⏸️ Služba pozastavena', (string) ($p['reason'] ?? ''), self::AMBER],
            str_starts_with($event, 'order.') => ['🧾 Objednávka '.($p['number'] ?? '').': '.substr($event, 6), '', self::BLUE],
            str_starts_with($event, 'invoice.') => ['💳 Doklad '.($p['number'] ?? '').': '.substr($event, 8), '', self::AMBER],
            str_starts_with($event, 'dunning.') => ['⚠️ Upomínka: '.substr($event, 8), '', self::AMBER],
            str_starts_with($event, 'ticket.') => ['💬 Tiket '.($p['number'] ?? '').': '.substr($event, 7), (string) ($p['subject'] ?? ''), self::BLUE],
            str_starts_with($event, 'registrar.connection.') => ['🔗 Registrátor: '.substr($event, 21), (string) ($p['label'] ?? '').(isset($p['error']) ? ' · '.$p['error'] : (isset($p['balance']) ? ' · kredit '.$p['balance'].' '.($p['currency'] ?? '') : '')), in_array($event, ['registrar.connection.sync_failed', 'registrar.connection.credit_low'], true) ? self::AMBER : self::GREEN],
            $event === 'domain.paired', $event === 'domain.unpaired' => ['🔗 Doména '.($p['fqdn'] ?? '').($event === 'domain.paired' ? ' spárována s webem' : ' odpojena od webu'), (string) ($p['hostname'] ?? ''), self::GREEN],
            $event === 'domain.external_expiry_notice' => ['⏰ Doména '.($p['fqdn'] ?? '').' expiruje za '.($p['days'] ?? '?').' dní', 'u registrátora '.($p['registrar'] ?? '').' (účet '.($p['account'] ?? '').')', self::AMBER],
            str_starts_with($event, 'domain.') => ['🌍 Doména '.($p['domain'] ?? $p['fqdn'] ?? '').': '.substr($event, 7), '', self::BLUE],
            str_starts_with($event, 'incident.'), str_starts_with($event, 'maintenance.') => ['🛠️ '.ucfirst(str_replace('.', ' ', $event)), (string) ($p['title'] ?? ''), self::AMBER],
            default => [$event, implode(', ', array_slice(array_keys($p), 0, 8)), self::GREY],
        };
    }
}
