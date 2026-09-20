<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Wedos\WedosZoneDnsProvider;

function wedosZoneAdapter(): WedosZoneDnsProvider
{
    $_ENV['WEDOS_ZONE_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_ZONE_WAPI_PASSWORD'] = 'wapi-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'wedos-zone-main'], [
        'provider' => 'wedos_zone', 'name' => 'WEDOS DNS', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_ZONE', 'state' => 'active', 'capabilities' => ['dns' => true], 'adapter_version' => '1.0.0',
    ]);

    return app(ProviderRegistry::class)->forInstance($instance);
}

/**
 * A hosted zone at WEDOS: rows are staged one by one and published by `dns-domain-commit`. `$state['fail']` names a command and
 * which of its calls answers 4205 (the registry is temporarily unavailable) — once.
 *
 * @param  array{rows:list<array<string,mixed>>, commands?:list<string>, sent?:list<array<string,mixed>>, fail?:array{command:string, nth:int}|null}  $state
 */
function wedosZoneFake(array &$state): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$state) {
        $payload = json_decode((string) $request['request'], true)['request'];
        $command = (string) $payload['command'];
        $data = (array) ($payload['data'] ?? []);
        $state['commands'][] = $command;
        $state['sent'][] = ['command' => $command] + $data;
        $envelope = fn (array $overrides = []) => Http::response(['response' => array_merge(['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'], 'svTRID' => 'sv-'.uniqid(), 'command' => $command, 'data' => []], $overrides)]);
        $fail = $state['fail'] ?? null;
        if ($fail !== null && $fail['command'] === $command && count(array_keys($state['commands'], $command, true)) === $fail['nth']) {
            $state['fail'] = null;

            return $envelope(['code' => 4205, 'result' => 'Registry temporarily unavailable']);
        }
        switch ($command) {
            case 'dns-rows-list':
                return $envelope(['data' => ['row' => array_values($state['rows'])]]);
            case 'dns-row-add':
                $state['rows'][] = ['ID' => (string) (500 + count($state['commands'])), 'name' => (string) $data['name'], 'ttl' => (int) $data['ttl'], 'rdtype' => strtoupper((string) $data['type']), 'rdata' => (string) $data['rdata']];

                return $envelope();
            case 'dns-row-update':
                $state['rows'] = array_map(fn (array $r) => (string) $r['ID'] === (string) $data['row_id'] ? array_merge($r, ['ttl' => (int) $data['ttl'], 'rdata' => (string) $data['rdata']]) : $r, $state['rows']);

                return $envelope();
            case 'dns-row-delete':
                $state['rows'] = array_values(array_filter($state['rows'], fn (array $r) => (string) $r['ID'] !== (string) $data['row_id']));

                return $envelope();
            default:
                return $envelope();
        }
    }]);
}

function wedosZoneRows(array $state): array
{
    $rows = array_map(fn (array $r) => ($r['name'] === '' ? '@' : $r['name']).' '.$r['rdtype'].' '.$r['rdata'].' '.$r['ttl'], $state['rows']);
    sort($rows);

    return $rows;
}

it('repeats a batch that failed half-way and arrives at the same zone: no row twice, and the commit is sent', function () {
    $state = ['rows' => [['ID' => '11', 'name' => '', 'ttl' => 1800, 'rdtype' => 'A', 'rdata' => '89.187.160.5']], 'fail' => ['command' => 'dns-row-add', 'nth' => 2]];
    wedosZoneFake($state);
    $batch = [
        ['op' => 'add', 'record' => ['name' => 'www', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null]],
        ['op' => 'add', 'record' => ['name' => 'mail', 'type' => 'A', 'content' => '89.187.160.6', 'ttl' => 3600, 'prio' => null]],
        ['op' => 'add', 'record' => ['name' => '@', 'type' => 'MX', 'content' => 'mail.priklad.cz', 'ttl' => 3600, 'prio' => 10]],
    ];

    // the second row is refused: the first one is staged at WEDOS, nothing is published
    expect(fn () => wedosZoneAdapter()->applyChanges('priklad.cz', $batch))->toThrow(ProviderException::class);
    expect($state['commands'])->not->toContain('dns-domain-commit')->and(count($state['rows']))->toBe(2);

    // the platform repeats the whole batch (the changes stayed pending): `www` used to be added a second time
    $result = wedosZoneAdapter()->applyChanges('priklad.cz', $batch);

    expect(wedosZoneRows($state))->toBe(['@ A 89.187.160.5 1800', '@ MX 10 mail.priklad.cz 3600', 'mail A 89.187.160.6 3600', 'www A 89.187.160.5 3600']);
    expect($result->data['committed'])->toBeTrue()->and($result->data['staged'])->toBe(2);
    expect(array_count_values($state['commands'])['dns-domain-commit'])->toBe(1);
});

it('publishes what an earlier attempt staged even when nothing is left to stage', function () {
    // everything was staged, and the commit itself failed
    $state = ['rows' => [], 'fail' => ['command' => 'dns-domain-commit', 'nth' => 1]];
    wedosZoneFake($state);
    $batch = [['op' => 'add', 'record' => ['name' => 'www', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null]]];
    expect(fn () => wedosZoneAdapter()->applyChanges('priklad.cz', $batch))->toThrow(ProviderException::class);

    // the repeat finds the row in place: it is not added again (it used to be), and the commit is sent all the same — a repeat
    // with nothing left to stage must not answer "nothing to do", or the staged row would never be published
    $result = wedosZoneAdapter()->applyChanges('priklad.cz', $batch);

    expect($result->data)->toMatchArray(['staged' => 0, 'committed' => true]);
    expect(array_count_values($state['commands']))->toMatchArray(['dns-row-add' => 1, 'dns-domain-commit' => 2]);
    expect(wedosZoneRows($state))->toBe(['www A 89.187.160.5 3600']);
});

it('renames a record as a row removed and a row added — `dns-row-update` takes no name', function () {
    $state = ['rows' => [['ID' => '21', 'name' => 'stary', 'ttl' => 3600, 'rdtype' => 'A', 'rdata' => '89.187.160.5'], ['ID' => '22', 'name' => 'api', 'ttl' => 3600, 'rdtype' => 'A', 'rdata' => '89.187.160.7']]];
    wedosZoneFake($state);

    wedosZoneAdapter()->applyChanges('priklad.cz', [
        // renamed: it used to be a `dns-row-update` of row 21 — WEDOS kept serving `stary`, the platform held `novy`
        ['op' => 'update', 'previous' => ['name' => 'stary', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null], 'record' => ['name' => 'novy', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null]],
        // the same name and type: the row is updated in place
        ['op' => 'update', 'previous' => ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.7', 'ttl' => 3600, 'prio' => null], 'record' => ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.8', 'ttl' => 600, 'prio' => null]],
    ]);

    expect(wedosZoneRows($state))->toBe(['api A 89.187.160.8 600', 'novy A 89.187.160.5 3600']);
    $sent = collect($state['sent']);
    expect($sent->where('command', 'dns-row-delete')->pluck('row_id')->all())->toBe(['21']);
    expect($sent->where('command', 'dns-row-update')->pluck('row_id')->all())->toBe(['22']);

    // repeated as a whole (say the commit had failed): nothing is left to do, nothing is doubled
    wedosZoneAdapter()->applyChanges('priklad.cz', [
        ['op' => 'update', 'previous' => ['name' => 'stary', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null], 'record' => ['name' => 'novy', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600, 'prio' => null]],
        ['op' => 'update', 'previous' => ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.7', 'ttl' => 3600, 'prio' => null], 'record' => ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.8', 'ttl' => 600, 'prio' => null]],
    ]);
    expect(wedosZoneRows($state))->toBe(['api A 89.187.160.8 600', 'novy A 89.187.160.5 3600']);
});

it('tells two MX rows of the same host apart by their priority', function () {
    $state = ['rows' => [['ID' => '31', 'name' => '', 'ttl' => 3600, 'rdtype' => 'MX', 'rdata' => '10 mail.priklad.cz'], ['ID' => '32', 'name' => '', 'ttl' => 3600, 'rdtype' => 'MX', 'rdata' => '20 mail.priklad.cz']]];
    wedosZoneFake($state);

    wedosZoneAdapter()->applyChanges('priklad.cz', [['op' => 'delete', 'record' => ['name' => '@', 'type' => 'MX', 'content' => 'mail.priklad.cz', 'ttl' => 3600, 'prio' => 20]]]);

    expect(wedosZoneRows($state))->toBe(['@ MX 10 mail.priklad.cz 3600']); // it used to remove the first row it met — the one with priority 10
});
