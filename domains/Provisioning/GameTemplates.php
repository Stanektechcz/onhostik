<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\GameToolsProvider;
use Throwable;

/**
 * What a game template needs before a server can be created (audit §5s). The panel's egg declares variables with
 * validation rules; a required one without a default makes `servers.create` fail. The egg sync records those per
 * mapping and this class sorts them into three kinds:
 *
 *  - `generated` — passwords (RCON, admin): the platform creates a random value that satisfies the rule;
 *  - `input` — anything else the customer may edit (the CS2 Game Server Login Token): the order asks for it, the quote
 *    refuses a line without it;
 *  - `operator` — read-only variables (the Steam account DayZ downloads with): filled from the secret store
 *    (`onhost:game:operator-variable`, `db://game/operator-variables`); a template whose operator variables are not stored is not offered.
 *
 * The offer also hides templates no active panel has mapped, and the quote enforces the template's RAM floor.
 */
final class GameTemplates
{
    /** @var array<string,string>|null */
    private ?array $operatorValues = null;

    public function __construct(private readonly SecretStore $secrets, private readonly OutboxPublisher $outbox) {}

    /** @return list<array{env:string, rules:string, editable:bool, kind:string}> the union over every active panel mapping */
    public function requirements(string $key): array
    {
        $out = [];
        foreach ($this->mappings($key) as $mapping) {
            foreach ((array) ($mapping['required'] ?? []) as $row) {
                $env = (string) ($row['env'] ?? '');
                if ($env !== '' && ! isset($out[$env])) {
                    $out[$env] = ['env' => $env, 'rules' => (string) ($row['rules'] ?? ''), 'editable' => (bool) ($row['editable'] ?? true), 'kind' => self::kind($env, (bool) ($row['editable'] ?? true))];
                }
            }
        }

        return array_values($out);
    }

    /** @return list<array{env:string, rules:string}> what the customer types in when ordering */
    public function inputs(string $key): array
    {
        return array_values(array_map(fn ($r) => ['env' => $r['env'], 'rules' => $r['rules']], array_filter($this->requirements($key), fn ($r) => $r['kind'] === 'input')));
    }

    /**
     * The order form of a template's customer inputs (audit §5u-3): a label, a hint built from the rule (exact length,
     * allowed characters), the bounds and a browser pattern, and where to get the value (the Steam GSLT page).
     *
     * @return list<array{env:string, rules:string, label:string, hint:string, help_url:?string, min:?int, max:?int, pattern:?string}>
     */
    public function inputForms(string $key): array
    {
        return array_map(fn (array $i) => self::form($i['env'], $i['rules']), $this->inputs($key));
    }

    /** @return array{env:string, rules:string, label:string, hint:string, help_url:?string, min:?int, max:?int, pattern:?string} */
    public static function form(string $env, string $rules): array
    {
        $min = $max = null;
        $hints = [];
        if (preg_match('/(^|\|)size:(\d+)/', $rules, $m) === 1) {
            $min = $max = (int) $m[2];
            $hints[] = 'přesně '.$m[2].' znaků';
        }
        if (preg_match('/(^|\|)between:(\d+),(\d+)/', $rules, $m) === 1) {
            [$min, $max] = [(int) $m[2], (int) $m[3]];
            $hints[] = $m[2].'–'.$m[3].' znaků';
        }
        if (preg_match('/(^|\|)max:(\d+)/', $rules, $m) === 1) {
            $max = (int) $m[2];
            $hints[] = 'nejvýš '.$m[2].' znaků';
        }
        if (preg_match('/(^|\|)min:(\d+)/', $rules, $m) === 1) {
            $min = (int) $m[2];
            $hints[] = 'aspoň '.$m[2].' znaků';
        }
        $pattern = null;
        if (preg_match('/(^|\|)alpha_num(\||$)/', $rules) === 1) {
            $pattern = '[A-Za-z0-9]+';
            $hints[] = 'jen písmena a číslice';
        } elseif (preg_match('/(^|\|)alpha_dash(\||$)/', $rules) === 1) {
            $pattern = '[A-Za-z0-9_-]+';
            $hints[] = 'písmena, číslice, - a _';
        }
        $known = [
            'STEAM_GSLT' => ['Steam Game Server Login Token', 'https://steamcommunity.com/dev/managegameservers', 'Token vytvoříte na Steamu (App ID hry, pro CS2 730).'],
            'STEAM_ACC' => ['Steam Game Server Login Token', 'https://steamcommunity.com/dev/managegameservers', 'Token vytvoříte na Steamu.'],
        ];
        [$label, $url, $extra] = $known[$env] ?? [ucwords(strtolower(str_replace('_', ' ', $env))), null, null];

        return ['env' => $env, 'rules' => $rules, 'label' => $label, 'hint' => trim(($extra !== null ? $extra.' ' : '').($hints !== [] ? ucfirst(implode(', ', $hints)).'.' : '')), 'help_url' => $url, 'min' => $min, 'max' => $max, 'pattern' => $pattern];
    }

    /**
     * Operator-held variables stored longer than `rotation_days` (audit §5u-5): operations are reminded once a month.
     *
     * @return array{stored_at:?string, stale:bool, days:?int}
     */
    public function operatorRotation(): array
    {
        $ref = SecretRef::parse((string) config('onhost.game.operator_variables_ref', 'db://game/operator-variables'));
        $at = $ref->scheme === 'db' ? DB::table('secrets')->where('name', $ref->path)->value('rotated_at') : null;
        if ($at === null || $this->operatorValues() === []) {
            return ['stored_at' => null, 'stale' => false, 'days' => null];
        }
        $days = (int) CarbonImmutable::parse((string) $at)->diffInDays(now());
        $stale = $days >= max(1, (int) config('onhost.game.operator_rotation_days', 180));
        if ($stale && cache()->add('onhost:game:operator-rotation:'.now()->format('Y-m'), 1, 40 * 86400)) {
            $this->outbox->publish(GenericEvent::of('game.operator_variables.stale', 'secret', 'game/operator-variables', ['days' => $days, 'names' => array_keys($this->operatorValues())]));
        }

        return ['stored_at' => CarbonImmutable::parse((string) $at)->toIso8601String(), 'stale' => $stale, 'days' => $days];
    }

    /** @return array{available:bool, reason:?string} */
    public function availability(string $key): array
    {
        if (config("onhost.game.eggs.{$key}") === null) {
            return ['available' => false, 'reason' => 'unknown_template'];
        }
        $instances = $this->gameInstances();
        $anyMapped = $instances->contains(fn (ProviderInstance $i) => (array) $i->option('eggs', []) !== []);
        if ($anyMapped && $this->mappings($key) === []) {
            return ['available' => false, 'reason' => 'not_on_panel'];
        }
        $missing = array_values(array_filter($this->requirements($key), fn ($r) => $r['kind'] === 'operator' && ($this->operatorValues()[$r['env']] ?? '') === ''));
        if ($missing !== []) {
            return ['available' => false, 'reason' => 'operator_variables_missing'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * The quote's check of a game line: a known, available template; the plan's RAM at least the template's floor; every
     * customer input present and matching its rule.
     *
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $entitlements
     */
    public function assertOrderable(Product $product, array $config, array $entitlements): void
    {
        $eggs = array_values(array_map('strval', (array) data_get($product->meta, 'eggs', [])));
        $key = (string) ($config['egg'] ?? $config['image'] ?? ($eggs[0] ?? ''));
        if ($key === '') {
            return;
        }
        if ($eggs !== [] && ! in_array($key, $eggs, true)) {
            throw new DomainError('game_template_unknown', "The game template {$key} is not sold with this product.", 422, ['field' => 'items', 'template' => $key]);
        }
        $availability = $this->availability($key);
        if (! $availability['available']) {
            throw new DomainError('game_template_unavailable', "The game template {$key} cannot be ordered right now.", 422, ['field' => 'items', 'template' => $key, 'reason' => $availability['reason']]);
        }
        $floor = (int) config("onhost.game.eggs.{$key}.min_ram_mb", 0);
        $ram = (int) ($entitlements['ram_mb'] ?? 0);
        if ($floor > 0 && $ram > 0 && $ram < $floor) {
            throw new DomainError('game_template_ram_too_low', "The game template {$key} needs at least {$floor} MB RAM; pick a bigger plan.", 422, ['field' => 'items', 'template' => $key, 'min_ram_mb' => $floor, 'plan_ram_mb' => $ram]);
        }
        $environment = (array) ($config['environment'] ?? []);
        $missing = [];
        foreach ($this->inputs($key) as $input) {
            $value = trim((string) ($environment[$input['env']] ?? ''));
            if ($value === '' || ! self::satisfies($value, $input['rules'])) {
                $missing[] = $input;
            }
        }
        if ($missing !== []) {
            throw new DomainError('game_template_input_required', 'The game template needs: '.implode(', ', array_column($missing, 'env')).'.', 422, ['field' => 'items', 'template' => $key, 'inputs' => $missing]);
        }
    }

    /**
     * The environment a new server is created with: generated passwords and operator values fill what the order left
     * empty; what the customer typed always stays.
     *
     * @param  array<string,mixed>  $environment
     * @return array<string,mixed>
     */
    public function fill(string $key, array $environment): array
    {
        foreach ($this->requirements($key) as $r) {
            if (trim((string) ($environment[$r['env']] ?? '')) !== '') {
                continue;
            }
            if ($r['kind'] === 'generated') {
                $environment[$r['env']] = self::secretFor($r['rules']);
            } elseif ($r['kind'] === 'operator' && ($this->operatorValues()[$r['env']] ?? '') !== '') {
                $environment[$r['env']] = $this->operatorValues()[$r['env']];
            }
        }

        return $environment;
    }

    /**
     * The daily drift check (rule `game.templates.verify`): a mapped egg the panel no longer has loses its mapping and
     * operations hear about it; the requirements of the rest are read again.
     *
     * @return array{instance:string, checked:int, missing:list<string>, refreshed:int}
     */
    public function verify(ProviderInstance $instance, CommandContext $context): array
    {
        $adapter = app(ProviderRegistry::class)->forInstance($instance);
        $out = ['instance' => $instance->key, 'checked' => 0, 'missing' => [], 'refreshed' => 0];
        if (! $adapter instanceof GameToolsProvider) {
            return $out;
        }
        $eggs = collect($adapter->listEggs());
        $mapped = (array) $instance->option('eggs', []);
        foreach ($mapped as $key => $mapping) {
            $out['checked']++;
            $egg = $eggs->first(fn (array $e) => $e['nest_id'] === (int) ($mapping['nest'] ?? 0) && $e['id'] === (int) ($mapping['egg'] ?? 0));
            if ($egg === null) {
                unset($mapped[$key]);
                $out['missing'][] = (string) $key;
                $this->outbox->publish(GenericEvent::of('game.template.missing', 'provider_instance', $instance->id, ['instance' => $instance->key, 'template' => (string) $key, 'nest' => (int) ($mapping['nest'] ?? 0), 'egg' => (int) ($mapping['egg'] ?? 0), 'name' => (string) ($mapping['name'] ?? '')]));

                continue;
            }
            $required = self::readRequirements($adapter, (int) $mapping['nest'], (int) $mapping['egg']);
            if ($required !== null) {
                $mapped[$key]['required'] = $required;
                $out['refreshed']++;
            }
        }
        $instance->forceFill(['options' => array_merge((array) $instance->options, ['eggs' => $mapped])])->save();
        app(ProviderRegistry::class)->forget($instance);
        app(AuditRecorder::class)->record($context, 'provider.instance.eggs.verify', 'succeeded', ['key' => $instance->key, 'missing' => $out['missing']], 'provider_instance', $instance->id);

        return $out;
    }

    /**
     * The required variables without a default of one egg; null when the panel cannot say (older adapters, errors).
     *
     * @return list<array{env:string, rules:string, editable:bool}>|null
     */
    public static function readRequirements(object $adapter, int $nest, int $egg): ?array
    {
        if (! method_exists($adapter, 'eggDefinition')) {
            return null;
        }
        try {
            $definition = $adapter->eggDefinition($nest, $egg);
        } catch (Throwable) {
            return null;
        }
        $out = [];
        foreach ((array) ($definition['variables'] ?? []) as $env => $v) {
            $rules = (string) ($v['rules'] ?? '');
            if (preg_match('/(^|\|)required(\||$)/', $rules) === 1 && trim((string) ($v['default'] ?? '')) === '') {
                $out[] = ['env' => (string) $env, 'rules' => mb_substr($rules, 0, 190), 'editable' => (bool) ($v['user_editable'] ?? true)];
            }
        }

        return $out;
    }

    /** generated (a password-like name), operator (not editable by the customer), else input. */
    public static function kind(string $env, bool $editable): string
    {
        if (! $editable) {
            return 'operator';
        }

        return preg_match('/(PASS|PASSWORD|SECRET)$/i', $env) === 1 ? 'generated' : 'input';
    }

    /** A random value a Laravel rule string accepts: letters and digits, the length the rule allows (20 by default). */
    public static function secretFor(string $rules): string
    {
        $length = 20;
        if (preg_match('/(^|\|)size:(\d+)/', $rules, $m) === 1) {
            $length = (int) $m[2];
        } else {
            if (preg_match('/(^|\|)max:(\d+)/', $rules, $m) === 1) {
                $length = min($length, (int) $m[2]);
            }
            if (preg_match('/(^|\|)between:(\d+),(\d+)/', $rules, $m) === 1) {
                $length = max((int) $m[2], min($length, (int) $m[3]));
            }
            if (preg_match('/(^|\|)min:(\d+)/', $rules, $m) === 1) {
                $length = max($length, (int) $m[2]);
            }
        }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < max(1, $length); $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /** Whether a customer's value passes the parts of the rule the order can check without the panel. */
    public static function satisfies(string $value, string $rules): bool
    {
        foreach (explode('|', $rules) as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $ok = match ($name) {
                'alpha_num' => preg_match('/^[\pL\pM\pN]+$/u', $value) === 1,
                'alpha_dash' => preg_match('/^[\pL\pM\pN_-]+$/u', $value) === 1,
                'size' => mb_strlen($value) === (int) $arg,
                'max' => mb_strlen($value) <= (int) $arg,
                'min' => mb_strlen($value) >= (int) $arg,
                'between' => (function () use ($value, $arg) {
                    [$lo, $hi] = array_map('intval', explode(',', (string) $arg) + [0, PHP_INT_MAX]);

                    return mb_strlen($value) >= $lo && mb_strlen($value) <= $hi;
                })(),
                'not_in' => ! in_array($value, explode(',', (string) $arg), true),
                'regex' => @preg_match((string) $arg, $value) === 1,
                default => true,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * The operator-held variables (audit §5t-1): which names are stored (never the values) and which templates need
     * which names, so the console can show what blocks a template.
     *
     * @return array{stored:list<string>, needed:array<string,list<string>>}
     */
    public function operatorStatus(): array
    {
        $needed = [];
        foreach (array_keys((array) config('onhost.game.eggs', [])) as $key) {
            foreach ($this->requirements((string) $key) as $r) {
                if ($r['kind'] === 'operator') {
                    $needed[$r['env']][] = (string) $key;
                }
            }
        }

        return ['stored' => array_keys($this->operatorValues()), 'needed' => $needed, 'rotation' => $this->operatorRotation()]; // §5u-5
    }

    /** Stores (or with an empty value removes) one operator-held variable; the value is never returned or audited. @return array{stored:list<string>, needed:array<string,list<string>>} */
    public function setOperatorVariable(string $env, ?string $value, CommandContext $context): array
    {
        $env = strtoupper(trim($env));
        if (preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $env) !== 1) {
            throw new DomainError('game_operator_variable_invalid', 'The variable name must look like STEAM_USER.', 422, ['field' => 'env']);
        }
        $ref = SecretRef::parse((string) config('onhost.game.operator_variables_ref', 'db://game/operator-variables'));
        $current = $this->operatorValues();
        if ($value === null || $value === '') {
            unset($current[$env]);
        } else {
            $current[$env] = $value;
        }
        $this->secrets->write($ref, $current);
        $this->operatorValues = $current;
        app(AuditRecorder::class)->record($context, 'game.operator_variable.set', 'succeeded', ['env' => $env, 'removed' => $value === null || $value === ''], 'secret', 'game/operator-variables');

        return $this->operatorStatus();
    }

    /** The customer inputs of a template whose current value on the server fails its rule (audit §5t-3). @param list<array<string,mixed>> $variables the startup variables @return list<string> */
    public function attention(string $key, array $variables): array
    {
        $values = [];
        foreach ($variables as $v) {
            $values[(string) ($v['key'] ?? '')] = (string) ($v['value'] ?? '');
        }
        $out = [];
        foreach ($this->inputs($key) as $input) {
            $value = trim($values[$input['env']] ?? '');
            if ($value === '' || ! self::satisfies($value, $input['rules'])) {
                $out[] = $input['env'];
            }
        }

        return $out;
    }

    /**
     * The daily look at running servers (audit §5t-3): a server whose customer input no longer passes its rule (a token
     * removed or mistyped) tells its customer once a day which variable to fix in Startup.
     *
     * @return array{checked:int, attention:int}
     */
    public function auditServices(): array
    {
        $stats = ['checked' => 0, 'attention' => 0];
        $services = Service::query()->where('family', 'game')->whereIn('state', ['ACTIVE', 'DEGRADED'])->limit(500)->get();
        foreach ($services as $service) {
            $key = (string) data_get($service->desired_spec, 'egg', '');
            if ($key === '' || $this->inputs($key) === []) {
                continue;
            }
            $stats['checked']++;
            try {
                $startup = app(ServiceFeatures::class)->resources($service, 'startup', true);
            } catch (Throwable) {
                continue;
            }
            $missing = $this->attention($key, (array) ($startup['variables'] ?? data_get($startup, 'data.variables', [])));
            if ($missing === []) {
                continue;
            }
            $stats['attention']++;
            $cacheKey = 'onhost:game:attention:'.$service->id.':'.now()->toDateString();
            if (cache()->add($cacheKey, 1, 86400)) {
                $this->outbox->publish(GenericEvent::of('game.setup.attention', 'service', $service->id, ['label' => (string) ($service->label ?: $service->hostname), 'variables' => $missing, 'template' => $key], $service->organization_id));
            }
        }

        return $stats;
    }

    /** @return list<array<string,mixed>> the mappings of `$key` on active game panels */
    private function mappings(string $key): array
    {
        return $this->gameInstances()->map(fn (ProviderInstance $i) => $i->option("eggs.{$key}"))->filter(fn ($m) => is_array($m) && ! empty($m['egg']))->values()->all();
    }

    /** @return Collection<int, ProviderInstance> */
    private function gameInstances(): Collection
    {
        return ProviderInstance::query()->where('provider', 'pterodactyl')->where('state', 'active')->get();
    }

    /** @return array<string,string> operator-held values (a Steam account) from the secret store */
    private function operatorValues(): array
    {
        if ($this->operatorValues !== null) {
            return $this->operatorValues;
        }
        try {
            $values = $this->secrets->read(SecretRef::parse((string) config('onhost.game.operator_variables_ref', 'db://game/operator-variables')));
        } catch (Throwable) {
            $values = [];
        }

        $out = [];
        foreach ((array) $values as $k => $v) {
            if (is_scalar($v)) {
                $out[strtoupper((string) $k)] = (string) $v;
            }
        }

        return $this->operatorValues = $out;
    }
}
