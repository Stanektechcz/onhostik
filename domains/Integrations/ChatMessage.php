<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations;

/**
 * Chat webhooks besides Discord: a Slack incoming webhook (hooks.slack.com) gets Block Kit, a Microsoft Teams incoming
 * webhook (webhook.office.com / *.logic.azure.com) gets a MessageCard. Both reuse the event wording of DiscordMessage,
 * so every chat tool tells the same story about the same event.
 */
final class ChatMessage
{
    public static function kind(string $url): ?string
    {
        return match (true) {
            DiscordMessage::isDiscordUrl($url) => 'discord',
            preg_match('#^https://hooks\.slack\.com/(?:services|workflows)/[A-Za-z0-9/_-]+#', $url) === 1 => 'slack',
            preg_match('#^https://[a-z0-9.-]+\.(?:webhook\.office\.com|logic\.azure\.com)/#i', $url) === 1 => 'teams',
            default => null,
        };
    }

    /** @return array<string,mixed> the JSON body the chat tool expects, or the signed platform envelope when the URL is a plain endpoint */
    public static function build(string $url, string $event, array $payload, ?string $createdAt, string $portal, array $envelope): array
    {
        return match (self::kind($url)) {
            'discord' => DiscordMessage::build($event, $payload, $createdAt, $portal),
            'slack' => self::slack($event, $payload, $createdAt, $portal),
            'teams' => self::teams($event, $payload, $createdAt, $portal),
            default => $envelope,
        };
    }

    /** @return array<string,mixed> */
    public static function slack(string $event, array $payload, ?string $createdAt, string $portal): array
    {
        $p = (array) ($payload['payload'] ?? $payload);
        [$title, $description] = DiscordMessage::describe($event, $p);
        $facts = self::facts($p);
        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr($title, 0, 150), 'emoji' => true]],
        ];
        if ($description !== '') {
            $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => mb_substr($description, 0, 2900)]];
        }
        if ($facts !== []) {
            $blocks[] = ['type' => 'section', 'fields' => array_map(fn ($f) => ['type' => 'mrkdwn', 'text' => "*{$f[0]}*\n{$f[1]}"], array_slice($facts, 0, 10))];
        }
        $blocks[] = ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => 'ONhost · '.$event.($createdAt ? ' · '.$createdAt : '').' · <'.$portal.'/panel|klientská sekce>']]];

        return ['text' => mb_substr($title.($description !== '' ? ' — '.$description : ''), 0, 300), 'blocks' => $blocks];
    }

    /** @return array<string,mixed> */
    public static function teams(string $event, array $payload, ?string $createdAt, string $portal): array
    {
        $p = (array) ($payload['payload'] ?? $payload);
        [$title, $description, $color] = DiscordMessage::describe($event, $p);
        $facts = self::facts($p);

        return [
            '@type' => 'MessageCard', '@context' => 'https://schema.org/extensions',
            'summary' => mb_substr($title, 0, 150), 'themeColor' => sprintf('%06X', $color), 'title' => mb_substr($title, 0, 150),
            'sections' => [array_filter([
                'activitySubtitle' => 'ONhost · '.$event.($createdAt ? ' · '.$createdAt : ''),
                'text' => $description !== '' ? mb_substr($description, 0, 2000) : null,
                'facts' => $facts === [] ? null : array_map(fn ($f) => ['name' => $f[0], 'value' => $f[1]], array_slice($facts, 0, 10)),
            ], fn ($v) => $v !== null)],
            'potentialAction' => [['@type' => 'OpenUri', 'name' => 'Otevřít klientskou sekci', 'targets' => [['os' => 'default', 'uri' => $portal.'/panel']]]],
        ];
    }

    /** @return list<array{0:string,1:string}> */
    private static function facts(array $p): array
    {
        $facts = [];
        foreach (['number', 'domain', 'url', 'ref', 'sha', 'release', 'error', 'reason', 'product_key', 'staging_service_id', 'minutes'] as $key) {
            if (isset($p[$key]) && $p[$key] !== '' && $p[$key] !== null && ! is_array($p[$key])) {
                $facts[] = [$key, mb_substr((string) $p[$key], 0, 200)];
            }
        }
        foreach (['total', 'amount'] as $key) {
            if (isset($p[$key]) && is_array($p[$key]) && isset($p[$key]['minor'], $p[$key]['currency'])) {
                $facts[] = [$key, number_format(((int) $p[$key]['minor']) / 100, 2, ',', ' ').' '.$p[$key]['currency']];
            }
        }

        return $facts;
    }
}
