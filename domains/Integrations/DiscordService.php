<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\CustomerActionParams;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Throwable;

/**
 * Discord as a remote control for services: a customer links their Discord account to their organization with a
 * one-time code, then drives services with `/onhost …` slash commands (status, backup, restart, deploy, ask the AI)
 * and confirms proposed actions with buttons. Every command runs as the linked user through the same ServiceService
 * path as the panel (permissions, plan limits, audit, operations). Interactions are verified with the application's
 * Ed25519 public key; replies are ephemeral (only the caller sees them).
 */
final class DiscordService
{
    public const COMMAND_NAME = 'onhost';

    /** Actions a Discord button may confirm (never terminate/restore, never a step-up action such as staging.push: those need the panel and a step-up). */
    public const BUTTON_ACTIONS = ['power', 'backup', 'deploy.run', 'deploy.rollback', 'staging.refresh', 'wp.update', 'wp.cache', 'cdn.purge', 'cron.run', 'ssl.issue', 'https.force', 'monitoring.set'];

    public function __construct(
        private readonly ServiceService $services, private readonly ServiceFeatures $features, private readonly Authorizer $authorizer, private readonly AssistantService $assistant,
        private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox, private readonly SecretStore $secrets, private readonly CacheRepository $cache, private readonly UptimeMonitor $monitor,
    ) {}

    public function configured(): bool
    {
        return (string) config('onhost.discord.public_key', '') !== '' && (string) config('onhost.discord.application_id', '') !== '';
    }

    public function inviteUrl(): ?string
    {
        $app = (string) config('onhost.discord.application_id', '');

        return $app === '' ? null : 'https://discord.com/oauth2/authorize?client_id='.rawurlencode($app).'&scope=applications.commands';
    }

    /** @return array<string,mixed> */
    public function status(Organization $organization): array
    {
        $links = DiscordLink::query()->where('organization_id', $organization->id)->where('state', 'linked')->orderBy('linked_at')->get();

        return [
            'configured' => $this->configured(),
            'invite_url' => $this->inviteUrl(),
            'links' => $links->map(fn (DiscordLink $l) => ['id' => $l->id, 'user_id' => $l->user_id, 'discord_username' => $l->discord_username, 'discord_user_id' => $l->discord_user_id, 'guild_id' => $l->discord_guild_id, 'linked_at' => $l->linked_at?->toIso8601String(), 'last_used_at' => $l->last_used_at?->toIso8601String(), 'commands' => $l->commands])->all(),
            'commands' => ['/onhost link <kód>', '/onhost services', '/onhost status <služba>', '/onhost backup <služba>', '/onhost restart <služba>', '/onhost deploy <služba>', '/onhost ask <otázka>', '/onhost unlink'],
        ];
    }

    /**
     * A code the customer types into `/onhost link`; valid 15 minutes, bound to the signed-in user.
     *
     * @return array{link_id:string, code:string, expires_at:string}
     */
    public function createLinkCode(Organization $organization, User $user, CommandContext $context): array
    {
        if (! $this->configured()) {
            throw new DomainError('discord_unavailable', 'Discord is not configured on the platform yet.', 503);
        }
        DiscordLink::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->where('state', 'pending')->delete();
        $code = strtoupper(Str::random(4).'-'.Str::random(4));
        $link = DiscordLink::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'code' => $code, 'code_expires_at' => now()->addMinutes(15), 'state' => 'pending', 'locale' => (string) ($user->locale ?? 'cs')]);
        $this->audit->record($context->withScope($organization->id), 'integration.discord.link_code', 'succeeded', [], 'discord_link', $link->id);

        return ['link_id' => $link->id, 'code' => $code, 'expires_at' => $link->code_expires_at->toIso8601String()];
    }

    public function unlink(Organization $organization, string $linkId, CommandContext $context): void
    {
        $link = DiscordLink::query()->where('organization_id', $organization->id)->find($linkId);
        if ($link === null) {
            throw DomainError::notFound('discord link');
        }
        $link->forceFill(['state' => 'revoked'])->save();
        $this->audit->record($context->withScope($organization->id), 'integration.discord.unlink', 'succeeded', ['discord_user_id' => $link->discord_user_id], 'discord_link', $link->id);
    }

    public function verifySignature(string $signatureHex, string $timestamp, string $body): bool
    {
        $publicKey = (string) config('onhost.discord.public_key', '');
        if ($publicKey === '' || ! ctype_xdigit($signatureHex) || strlen($signatureHex) !== 128 || ! ctype_xdigit($publicKey) || strlen($publicKey) !== 64) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached(hex2bin($signatureHex), $timestamp.$body, hex2bin($publicKey));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * One interaction → one reply (Discord's response object).
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function handleInteraction(array $payload): array
    {
        $type = (int) ($payload['type'] ?? 0);
        if ($type === 1) {
            return ['type' => 1]; // PING → PONG
        }
        $discordUser = (array) ($payload['member']['user'] ?? $payload['user'] ?? []);
        $uid = (string) ($discordUser['id'] ?? '');
        $username = (string) ($discordUser['username'] ?? '');
        $guild = isset($payload['guild_id']) ? (string) $payload['guild_id'] : null;
        $data = (array) ($payload['data'] ?? []);
        if ($uid === '') {
            return $this->reply('Nepodařilo se zjistit, kdo příkaz poslal.');
        }
        try {
            if ($type === 3) {
                return $this->component($uid, (string) ($data['custom_id'] ?? ''));
            }
            if ($type !== 2 || (string) ($data['name'] ?? '') !== self::COMMAND_NAME) {
                return $this->reply('Neznámý příkaz. Zkuste `/onhost services`.');
            }
            $sub = (string) ($data['options'][0]['name'] ?? 'help');
            $opts = [];
            foreach ((array) ($data['options'][0]['options'] ?? []) as $o) {
                $opts[(string) $o['name']] = $o['value'] ?? null;
            }
            if ($sub === 'link') {
                return $this->link($uid, $username, $guild, (string) ($opts['code'] ?? ''));
            }
            $link = DiscordLink::query()->where('discord_user_id', $uid)->where('state', 'linked')->orderByDesc('linked_at')->first();
            if ($link === null) {
                return $this->reply('Váš Discord účet zatím není propojený. V klientském panelu (Účet a správa → API klíče a webhooky → Discord) vygenerujte kód a zadejte `/onhost link KÓD`.');
            }
            if (! $this->rateOk($link)) {
                return $this->reply('Příliš mnoho příkazů za sebou, zkuste to za pár minut.');
            }
            $link->forceFill(['last_used_at' => now(), 'commands' => $link->commands + 1, 'discord_guild_id' => $guild ?? $link->discord_guild_id])->save();
            [$organization, $user] = $this->principal($link);
            $t = fn (string $cs, string $en) => $link->locale === 'en' ? $en : $cs;

            return match ($sub) {
                'unlink' => (function () use ($link, $organization, $user, $t) {
                    $this->unlink($organization, $link->id, $this->context($link, $user, 'unlink'));

                    return $this->reply($t('Účet odpojen. Znovu ho propojíte kódem z panelu.', 'Account unlinked. Link it again with a code from the panel.'));
                })(),
                'services' => $this->servicesReply($organization, $t),
                'status' => $this->statusReply($organization, $user, (string) ($opts['service'] ?? ''), $t),
                'backup' => $this->runReply($link, $organization, $user, (string) ($opts['service'] ?? ''), 'backup', ['kind' => 'manual'], $t),
                'restart' => $this->proposeReply($link, $organization, $user, (string) ($opts['service'] ?? ''), 'restart', $t),
                'deploy' => $this->proposeReply($link, $organization, $user, (string) ($opts['service'] ?? ''), 'deploy', $t),
                'ask' => $this->askReply($link, $organization, $user, (string) ($opts['question'] ?? ''), $t),
                default => $this->reply($t('Příkazy: `/onhost services`, `/onhost status <služba>`, `/onhost backup <služba>`, `/onhost restart <služba>`, `/onhost deploy <služba>`, `/onhost ask <otázka>`, `/onhost unlink`.', 'Commands: `/onhost services`, `/onhost status <service>`, `/onhost backup <service>`, `/onhost restart <service>`, `/onhost deploy <service>`, `/onhost ask <question>`, `/onhost unlink`.')),
            };
        } catch (DomainError $e) {
            return $this->reply('⚠️ '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->reply('⚠️ Příkaz se nepodařilo provést. Zkuste to v klientském panelu.');
        }
    }

    /** Register (or update) the `/onhost` command with Discord; needs the bot token. @return array<string,mixed> */
    public function registerCommands(): array
    {
        $token = $this->botToken();
        $app = (string) config('onhost.discord.application_id', '');
        if ($token === null || $app === '') {
            throw new DomainError('discord_unavailable', 'Set ONHOST_DISCORD_APPLICATION_ID and the bot token secret first.', 503);
        }
        $response = Http::withHeaders(['Authorization' => 'Bot '.$token])->timeout(20)->put(rtrim((string) config('onhost.discord.base_url'), '/').'/applications/'.$app.'/commands', [self::commandDefinition()]);
        if ($response->failed()) {
            throw new DomainError('discord_register_failed', 'Discord refused the command registration: HTTP '.$response->status().' '.mb_substr((string) $response->body(), 0, 200), 502);
        }

        return (array) $response->json();
    }

    /** @return array<string,mixed> the slash command as Discord expects it */
    public static function commandDefinition(): array
    {
        $service = ['type' => 3, 'name' => 'service', 'description' => 'Doména nebo název služby', 'required' => false];

        return ['name' => self::COMMAND_NAME, 'description' => 'ONhost: správa služeb z Discordu', 'type' => 1, 'options' => [
            ['type' => 1, 'name' => 'link', 'description' => 'Propojit Discord účet s klientským účtem', 'options' => [['type' => 3, 'name' => 'code', 'description' => 'Kód z klientského panelu', 'required' => true]]],
            ['type' => 1, 'name' => 'unlink', 'description' => 'Odpojit Discord účet'],
            ['type' => 1, 'name' => 'services', 'description' => 'Seznam služeb a jejich stav'],
            ['type' => 1, 'name' => 'status', 'description' => 'Stav služby, dostupnost a poslední operace', 'options' => [$service]],
            ['type' => 1, 'name' => 'backup', 'description' => 'Vytvořit zálohu služby', 'options' => [$service]],
            ['type' => 1, 'name' => 'restart', 'description' => 'Restartovat server (VPS, herní server)', 'options' => [$service]],
            ['type' => 1, 'name' => 'deploy', 'description' => 'Nasadit poslední verzi z připojeného repozitáře', 'options' => [$service]],
            ['type' => 1, 'name' => 'ask', 'description' => 'Zeptat se AI asistenta (může navrhnout akci k potvrzení)', 'options' => [['type' => 3, 'name' => 'question', 'description' => 'Otázka nebo pokyn', 'required' => true]]],
        ]];
    }

    // ── replies ──────────────────────────────────────────────────────────────────────────────────────────

    private function link(string $uid, string $username, ?string $guild, string $code): array
    {
        $code = strtoupper(trim($code));
        $link = $code === '' ? null : DiscordLink::query()->where('code', $code)->where('state', 'pending')->where('code_expires_at', '>', now())->first();
        if ($link === null) {
            return $this->reply('Kód je neplatný nebo vypršel. Vygenerujte nový v klientském panelu (API klíče a webhooky → Discord).');
        }
        DiscordLink::query()->where('discord_user_id', $uid)->where('state', 'linked')->update(['state' => 'revoked']);
        $link->forceFill(['discord_user_id' => $uid, 'discord_username' => $username, 'discord_guild_id' => $guild, 'state' => 'linked', 'linked_at' => now(), 'code' => null, 'code_expires_at' => null])->save();
        $organization = Organization::query()->find($link->organization_id);
        $this->outbox->publish(GenericEvent::of('integration.discord.linked', 'organization', $link->organization_id, ['discord_username' => $username, 'user_id' => $link->user_id], $link->organization_id));

        return $this->reply('✅ Propojeno s účtem '.($organization?->name ?? '').'. Zkuste `/onhost services`.');
    }

    private function servicesReply(Organization $organization, callable $t): array
    {
        $services = Service::query()->where('organization_id', $organization->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->orderBy('family')->orderBy('hostname')->get();
        if ($services->isEmpty()) {
            return $this->reply($t('Zatím nemáte žádnou službu. Objednat můžete v klientském panelu.', 'You have no service yet. Order one in the client panel.'));
        }
        $lines = $services->map(fn (Service $s) => '• **'.($s->hostname ?: $s->name).'** — '.$this->family($s->family, $t).' · '.ServiceStateMachine::machine()->label($s->state))->all();

        return $this->reply(implode("\n", $lines)."\n\n".$t('Podrobnosti: `/onhost status <doména>`', 'Details: `/onhost status <domain>`'));
    }

    private function statusReply(Organization $organization, User $user, string $query, callable $t): array
    {
        $service = $this->resolveService($organization, $query, $t);
        $lines = ['**'.($service->hostname ?: $service->name).'** — '.$this->family($service->family, $t).' · '.ServiceStateMachine::machine()->label($service->state).($service->activated_at ? ' · '.$t('od ', 'since ').$service->activated_at->format('j. n. Y') : '')];
        if (in_array($service->family, ['web', 'managed'], true)) {
            try {
                foreach ($this->monitor->status($service)['monitors'] as $m) {
                    $w = $m['windows']['24h'] ?? [];
                    $lines[] = ($m['state'] === 'up' ? '🟢' : ($m['state'] === 'down' ? '🔴' : '⚪')).' '.$m['url'].' · '.($w['uptime_pct'] ?? '—').' % / 24 h'.(isset($m['last_ms']) && $m['last_ms'] !== null ? ' · '.$m['last_ms'].' ms' : '');
                }
            } catch (Throwable) {
                // monitoring may be unavailable for the plan
            }
        }
        $ops = Operation::query()->where('service_id', $service->id)->orderByDesc('queued_at')->limit(3)->get();
        foreach ($ops as $op) {
            $lines[] = '• '.($op->desired['action'] ?? $op->kind).' — '.strtolower($op->state).($op->finished_at ? ' · '.$op->finished_at->format('j. n. H:i') : '');
        }
        $lines[] = $t('Panel: ', 'Panel: ').rtrim((string) config('onhost.portal_url'), '/').'/panel/sluzby';

        return $this->reply(implode("\n", $lines));
    }

    private function runReply(DiscordLink $link, Organization $organization, User $user, string $query, string $action, array $params, callable $t): array
    {
        $service = $this->resolveService($organization, $query, $t);
        $operation = $this->execute($link, $organization, $user, $service, $action, $params);

        return $this->reply('▶️ '.$t('Spuštěno: ', 'Started: ').$this->actionLabel($action, $t).' · '.($service->hostname ?: $service->name).' · '.$t('operace ', 'operation ').substr($operation->id, -6).$t('. Průběh sledujte v panelu nebo `/onhost status`.', '. Follow it in the panel or with `/onhost status`.'));
    }

    /** restart/deploy need a click: the reply carries a button that runs the action when pressed. */
    private function proposeReply(DiscordLink $link, Organization $organization, User $user, string $query, string $kind, callable $t): array
    {
        $service = $this->resolveService($organization, $query, $t);
        if ($kind === 'restart') {
            if (! in_array($service->family, ['cloud', 'game'], true)) {
                return $this->reply($t('Webhosting a e-mail se nerestartují; zkuste `/onhost backup` nebo `/onhost deploy`.', 'Web and mail hosting have no restart; try `/onhost backup` or `/onhost deploy`.'));
            }
            $proposal = ['service_id' => $service->id, 'action' => 'power', 'params' => ['power_action' => 'reboot'], 'label' => $t('Restartovat ', 'Restart ').($service->hostname ?: $service->name)];
        } else {
            if (empty($this->features->features($service)['deploy']['enabled'])) {
                return $this->reply($t('Git deploy není u této služby k dispozici.', 'Git deploy is not available for this service.'));
            }
            $proposal = ['service_id' => $service->id, 'action' => 'deploy.run', 'params' => [], 'label' => $t('Nasadit ', 'Deploy ').($service->hostname ?: $service->name)];
        }

        return $this->confirmReply($link, $t('Potvrďte: ', 'Confirm: ').$proposal['label'], [$proposal], $t);
    }

    private function askReply(DiscordLink $link, Organization $organization, User $user, string $question, callable $t): array
    {
        $question = trim($question);
        if ($question === '') {
            return $this->reply($t('Napište otázku: `/onhost ask Jak je na tom můj web?`', 'Ask something: `/onhost ask How is my site doing?`'));
        }
        $answer = $this->assistant->chat($question, $organization, $user, 'discord:'.$link->discord_user_id, $this->context($link, $user, 'ask'), $link->locale === 'en' ? 'en' : 'cs');
        $proposals = [];
        foreach ((array) ($answer['actions'] ?? []) as $a) {
            if (($a['kind'] ?? '') === 'service_action' && ! empty($a['service_id']) && in_array((string) ($a['action'] ?? ''), self::BUTTON_ACTIONS, true)) {
                $proposals[] = ['service_id' => (string) $a['service_id'], 'action' => (string) $a['action'], 'params' => (array) ($a['params'] ?? []), 'label' => (string) ($a['label'] ?? $a['action'])];
            } elseif (($a['kind'] ?? '') === 'restart' && ! empty($a['service_id'])) {
                $proposals[] = ['service_id' => (string) $a['service_id'], 'action' => 'power', 'params' => ['power_action' => (string) ($a['power_action'] ?? 'reboot')], 'label' => (string) $a['label']];
            }
        }
        $text = mb_substr((string) ($answer['text'] ?? ''), 0, 1800);
        if (! empty($answer['handoff']['number'])) {
            $text .= "\n".$t('Tiket: ', 'Ticket: ').$answer['handoff']['number'];
        }

        return $proposals === [] ? $this->reply($text) : $this->confirmReply($link, $text, array_slice($proposals, 0, 3), $t);
    }

    /** A button click: run the proposal it points at (once) and replace the message with the outcome. */
    private function component(string $uid, string $customId): array
    {
        if ($customId === 'cancel') {
            return ['type' => 7, 'data' => ['content' => 'Zrušeno.', 'components' => []]];
        }
        if (! str_starts_with($customId, 'act:')) {
            return $this->reply('Neznámé tlačítko.');
        }
        $key = 'discord:proposal:'.substr($customId, 4);
        $proposal = $this->cache->get($key);
        if (! is_array($proposal)) {
            return ['type' => 7, 'data' => ['content' => 'Návrh už neplatí (15 minut). Zadejte příkaz znovu.', 'components' => []]];
        }
        $link = DiscordLink::query()->find((string) ($proposal['link_id'] ?? ''));
        if ($link === null || $link->state !== 'linked' || $link->discord_user_id !== $uid) {
            return $this->reply('Tlačítko může použít jen ten, kdo příkaz zadal.');
        }
        $this->cache->forget($key);
        [$organization, $user] = $this->principal($link);
        $service = Service::query()->where('organization_id', $organization->id)->find((string) $proposal['service_id']);
        if ($service === null) {
            return ['type' => 7, 'data' => ['content' => 'Služba už neexistuje.', 'components' => []]];
        }
        $t = fn (string $cs, string $en) => $link->locale === 'en' ? $en : $cs;
        $operation = $this->execute($link, $organization, $user, $service, (string) $proposal['action'], (array) $proposal['params']);

        return ['type' => 7, 'data' => ['content' => '▶️ '.$t('Spuštěno: ', 'Started: ').$proposal['label'].' · '.$t('operace ', 'operation ').substr($operation->id, -6).$t('. Průběh v panelu nebo `/onhost status`.', '. Progress in the panel or `/onhost status`.'), 'components' => []]];
    }

    // ── execution ────────────────────────────────────────────────────────────────────────────────────────

    private function execute(DiscordLink $link, Organization $organization, User $user, Service $service, string $action, array $params): Operation
    {
        if (in_array($action, ['terminate', 'restore', 'rollback_snapshot', 'suspend', 'resume', 'resize'], true)) {
            throw new DomainError('discord_action_forbidden', 'This action needs the client panel and a second verification.', 403);
        }
        // the action's own permission, as the bus asks it (TASK-0029 D29.7): a button is no way around the map. An action nobody
        // mapped is refused like a forbidden one, and one that needs a fresh step-up cannot be confirmed by a click in a chat.
        $params = CustomerActionParams::filter($action, $params);
        try {
            $permission = ServiceActionCommand::permissionFor($action, $params);
        } catch (DomainError $e) {
            throw $e->error === 'service_action_unknown' ? new DomainError('discord_action_forbidden', 'This action is not available on Discord.', 403) : $e;
        }
        if (ServiceActionCommand::needsFreshStepUp($action)) {
            throw new DomainError('discord_action_forbidden', 'This action needs the client panel and a second verification.', 403);
        }
        if (! $this->authorizer->can($user, $permission, CommandScope::organization($organization->id))) {
            throw new DomainError('forbidden', 'Your account may not manage this service.', 403);
        }
        $context = $this->context($link, $user, $action);
        $operation = $this->services->requestAction($service, $action, $context, 'discord:'.$link->id.':'.$action.':'.Str::lower(Str::random(12)), $params, authorizedPermission: $permission); // the run re-checks the same permission (H315)
        $this->audit->record($context, 'integration.discord.command', 'succeeded', ['action' => $action, 'params' => array_keys($params), 'operation_id' => $operation->id], 'service', $service->id);

        return $operation;
    }

    private function confirmReply(DiscordLink $link, string $text, array $proposals, callable $t): array
    {
        $buttons = [];
        foreach ($proposals as $p) {
            $id = Str::lower(Str::random(16));
            $this->cache->put('discord:proposal:'.$id, $p + ['link_id' => $link->id], 900);
            $buttons[] = ['type' => 2, 'style' => 1, 'label' => mb_substr((string) $p['label'], 0, 80), 'custom_id' => 'act:'.$id];
        }
        $buttons[] = ['type' => 2, 'style' => 2, 'label' => $t('Zrušit', 'Cancel'), 'custom_id' => 'cancel'];

        return ['type' => 4, 'data' => ['content' => $text, 'flags' => 64, 'components' => [['type' => 1, 'components' => $buttons]]]];
    }

    private function reply(string $text): array
    {
        return ['type' => 4, 'data' => ['content' => mb_substr($text, 0, 1900), 'flags' => 64]];
    }

    /** @return array{0:Organization,1:User} */
    private function principal(DiscordLink $link): array
    {
        $organization = Organization::query()->find($link->organization_id);
        $user = User::query()->find($link->user_id);
        if ($organization === null || $user === null) {
            $link->forceFill(['state' => 'revoked'])->save();
            throw new DomainError('discord_link_stale', 'The linked account no longer exists; link again from the panel.', 410);
        }

        return [$organization, $user];
    }

    private function context(DiscordLink $link, User $user, string $what): CommandContext
    {
        return new CommandContext('user', $user->id, $link->organization_id, null, null, 'discord-bot', 'discord:'.$link->discord_user_id, 'discord /onhost '.$what);
    }

    private function resolveService(Organization $organization, string $query, callable $t): Service
    {
        $query = strtolower(trim($query));
        $services = Service::query()->where('organization_id', $organization->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get();
        if ($query === '') {
            if ($services->count() === 1) {
                return $services->first();
            }
            throw new DomainError('service_ambiguous', $t('Uveďte službu: `/onhost status <doména>`; seznam dá `/onhost services`.', 'Name the service: `/onhost status <domain>`; `/onhost services` lists them.'), 422);
        }
        $match = $services->first(fn (Service $s) => strtolower((string) $s->hostname) === $query || $s->id === $query)
            ?? $services->first(fn (Service $s) => str_contains(strtolower((string) $s->hostname), $query) || str_contains(strtolower((string) $s->name), $query) || str_contains(strtolower((string) $s->label), $query));
        if ($match === null) {
            throw new DomainError('service_not_found', $t("Službu „{$query}“ jsem nenašel; seznam dá `/onhost services`.", "No service matches “{$query}”; `/onhost services` lists them."), 404);
        }

        return $match;
    }

    private function rateOk(DiscordLink $link): bool
    {
        $key = 'discord:rate:'.$link->id;
        $count = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $count, 600);

        return $count <= 40;
    }

    private function botToken(): ?string
    {
        $ref = (string) config('onhost.discord.bot_token_ref', '');
        if ($ref === '') {
            return null;
        }
        try {
            $data = $this->secrets->read(SecretRef::parse($ref));
        } catch (Throwable) {
            return null;
        }
        $token = (string) ($data['token'] ?? ($data['bot_token'] ?? ''));

        return $token !== '' ? $token : null;
    }

    private function family(string $family, callable $t): string
    {
        return match ($family) {
            'web', 'managed' => $t('webhosting', 'web hosting'), 'cloud' => 'VPS', 'game' => $t('herní server', 'game server'), 'mail' => 'e-mail', 'data' => $t('databáze', 'database'), default => $family,
        };
    }

    private function actionLabel(string $action, callable $t): string
    {
        return match ($action) {
            'backup' => $t('záloha', 'backup'), 'power' => $t('restart', 'restart'), 'deploy.run' => 'deploy', 'staging.refresh' => $t('obnova stagingu', 'staging refresh'), 'wp.update' => $t('aktualizace WordPressu', 'WordPress update'), default => $action,
        };
    }
}
