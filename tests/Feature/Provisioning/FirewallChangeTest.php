<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\FirewallPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;

/*
 * The VPS firewall (Brain cards H501, H503). It was replaced by deleting every rule and then adding the new ones, with
 * the firewall already on under `policy_in: DROP` — a refused add left the server with half a rule set and its SSH
 * gone. Each rule was added without a position, which Proxmox puts at the top, so the list came out upside down. And
 * nothing told the customer that the rules they were about to save would close their own terminal.
 */

beforeEach(fn () => Http::preventStrayRequests());

/**
 * A Proxmox VM firewall that keeps its rules in order and honours `pos`, as pve-firewall does.
 *
 * @param  array{rules:list<array<string,mixed>>, options:array<string,mixed>, log:list<string>, refuse?:string}  $fw
 */
function vmFirewallFake(array &$fw): void
{
    Http::fake(function (Request $r) use (&$fw) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        if (! str_contains($path, '/firewall')) {
            return Http::response(['data' => null]);
        }
        if (str_ends_with($path, '/firewall/options')) {
            if ($r->method() === 'PUT') {
                $fw['options'] = array_replace($fw['options'], $r->data());
                $fw['log'][] = 'options enable='.($r->data()['enable'] ?? '?');
            }

            return Http::response(['data' => $r->method() === 'GET' ? $fw['options'] : null]);
        }
        if (str_ends_with($path, '/firewall/rules') && $r->method() === 'GET') {
            return Http::response(['data' => array_map(fn (array $rule, int $pos) => $rule + ['pos' => $pos], $fw['rules'], array_keys($fw['rules']))]);
        }
        if (str_ends_with($path, '/firewall/rules') && $r->method() === 'POST') {
            $rule = $r->data();
            if (isset($fw['refuse']) && ($rule['comment'] ?? '') === $fw['refuse']) {
                return Http::response(['errors' => ['dport' => 'invalid port expression']], 400);
            }
            // no position: Proxmox puts the rule at the TOP. Whether create honours `pos` at all is not settled, so the fake
            // can do either (`ignore_pos`) — the adapter must come out right both ways
            $pos = ! ($fw['ignore_pos'] ?? false) && isset($rule['pos']) ? (int) $rule['pos'] : 0;
            unset($rule['pos']);
            array_splice($fw['rules'], $pos, 0, [$rule]);
            $fw['log'][] = 'add '.($rule['comment'] ?? '').' @'.$pos.' ('.count($fw['rules']).' rules)';

            return Http::response(['data' => null]);
        }
        if (preg_match('~/firewall/rules/(\d+)$~', $path, $m) === 1 && $r->method() === 'DELETE') {
            $removed = $fw['rules'][(int) $m[1]]['comment'] ?? '?';
            array_splice($fw['rules'], (int) $m[1], 1);
            $fw['log'][] = 'delete '.$removed.' ('.count($fw['rules']).' rules)';

            return Http::response(['data' => null]);
        }

        return Http::response(['data' => null]);
    });
}

function firewallAdapter(): object
{
    return app(ProviderRegistry::class)->forInstance(pveLab());
}

function fwRule(string $comment, string $action, ?string $dport = null, ?string $source = null): array
{
    return ['action' => $action, 'type' => 'in', 'proto' => 'tcp', 'dport' => $dport, 'source' => $source, 'enable' => true, 'comment' => $comment];
}

it('keeps the customer\'s rules in their order, and never passes through a moment with fewer rules than before', function (bool $ignorePos) {
    $fw = ['rules' => [['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => '22', 'enable' => 1, 'comment' => 'old-ssh'], ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => '80', 'enable' => 1, 'comment' => 'old-web']],
        'options' => ['enable' => 1, 'policy_in' => 'DROP', 'policy_out' => 'ACCEPT'], 'log' => [], 'ignore_pos' => $ignorePos];
    vmFirewallFake($fw);
    $vm = new ResourceRef('qemu', '1042', 'prg1-n2');

    // "accept my office on 22, drop everyone else on 22" — upside down it would lock the office out too
    firewallAdapter()->applyFirewall($vm, [fwRule('office', 'ACCEPT', '22', '198.51.100.7'), fwRule('others', 'DROP', '22'), fwRule('web', 'ACCEPT', '80,443')], true);

    expect(array_column($fw['rules'], 'comment'))->toBe(['office', 'others', 'web']);
    // make before break: the three new rules went in on top while the two old ones still stood, then the old ones went
    expect($fw['log'])->toBe(['add web @0 (3 rules)', 'add others @0 (4 rules)', 'add office @0 (5 rules)', 'delete old-web (4 rules)', 'delete old-ssh (3 rules)', 'options enable=1']);
})->with(['the panel honours pos' => false, 'the panel ignores pos' => true]);

it('puts the previous policy back as a whole when the panel refuses part of the new one', function () {
    $fw = ['rules' => [['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => '22', 'enable' => 1, 'comment' => 'old-ssh']],
        'options' => ['enable' => 0, 'policy_in' => 'ACCEPT', 'policy_out' => 'ACCEPT'], 'log' => [], 'refuse' => 'broken'];
    vmFirewallFake($fw);
    $vm = new ResourceRef('qemu', '1042', 'prg1-n2');

    expect(fn () => firewallAdapter()->applyFirewall($vm, [fwRule('web', 'ACCEPT', '80'), fwRule('broken', 'ACCEPT', '99999')], true))
        ->toThrow(ProviderException::class, 'the previous rules were put back');

    // it used to be: every old rule deleted, `web` added, `broken` refused — a firewall under DROP with SSH gone
    expect(array_column($fw['rules'], 'comment'))->toBe(['old-ssh'])
        ->and($fw['options'])->toMatchArray(['enable' => 0, 'policy_in' => 'ACCEPT']); // and the firewall was never switched on
});

it('reads which ports a rule lets through', function () {
    expect(FirewallPolicy::covers('', 22))->toBeTrue() // no port given: every port
        ->and(FirewallPolicy::covers('22', 22))->toBeTrue()
        ->and(FirewallPolicy::covers('20:25', 22))->toBeTrue()
        ->and(FirewallPolicy::covers('80,443,22', 22))->toBeTrue()
        ->and(FirewallPolicy::covers('ssh', 22))->toBeTrue()
        ->and(FirewallPolicy::covers('8022', 22))->toBeFalse()
        ->and(FirewallPolicy::covers('80,443', 22))->toBeFalse();
});

it('tells the customer before the rules close their own way in, and names the way back', function () {
    [$user, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $vps = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-fw.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => []]);
    ProviderBinding::query()->create(['service_id' => $vps->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "fw:{$vps->id}", 'adapter_version' => '1.0.0']);
    $fw = ['rules' => [], 'options' => ['enable' => 0], 'log' => []];
    vmFirewallFake($fw);
    $this->actingAs($user, 'sanctum');
    $apply = fn (array $params, string $key) => $this->postJson("/v1/services/{$vps->id}/actions", ['action' => 'firewall.apply', 'params' => $params], ['Idempotency-Key' => $key]);

    // only the web ports: the firewall switches on with DROP, and SSH is gone
    $apply(['enabled' => true, 'rules' => [fwRule('web', 'ACCEPT', '80,443')]], 'fw-1')
        ->assertUnprocessable()->assertJsonPath('error', 'firewall_closes_admin')->assertJsonPath('way_back', 'console');
    // nothing accepted inbound at all: the server is off the network
    $apply(['enabled' => true, 'rules' => [fwRule('block', 'DROP', '25')]], 'fw-2')->assertUnprocessable()->assertJsonPath('error', 'firewall_closes_everything');

    // said and meant: accepted
    $apply(['enabled' => true, 'rules' => [fwRule('web', 'ACCEPT', '80,443')], 'accept_lockout' => true], 'fw-3')->assertAccepted();
    // SSH kept open, or the firewall off: nothing to confirm
    $apply(['enabled' => true, 'rules' => [fwRule('ssh', 'ACCEPT', '22', '198.51.100.7'), fwRule('web', 'ACCEPT', '80,443')]], 'fw-4')->assertAccepted();
    $apply(['enabled' => false, 'rules' => []], 'fw-5')->assertAccepted();
});
