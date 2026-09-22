<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Platform\Errors\DomainError;

/**
 * What a set of firewall rules does to the way into the server (H501).
 *
 * The firewall of a VPS is switched on with `policy_in: DROP`: whatever is not accepted is dropped. A rule set that
 * accepts only 80 and 443 therefore closes SSH, and one that accepts nothing inbound closes the server completely —
 * the customer finds out when their terminal stops answering. The console in the panel still works (it goes through
 * the hypervisor, not the network), so the way back exists; what was missing is being told before, not after.
 *
 * A change that would close remote administration, or every way in, is refused until the customer says they mean it
 * (`accept_lockout`). The message names the way back.
 */
final class FirewallPolicy
{
    /** Ports a remote administrator comes in through: SSH, and RDP for Windows servers. */
    public const ADMIN_PORTS = [22, 3389];

    private const NAMED = ['ssh' => 22, 'rdp' => 3389, 'ms-wbt-server' => 3389];

    /**
     * @param  list<array{action:string, type:string, proto:?string, dport:?string, source:?string, enable:bool}>  $rules
     * @return array{reachable:bool, admin:bool}
     */
    public static function assess(array $rules, bool $enabled): array
    {
        if (! $enabled) {
            return ['reachable' => true, 'admin' => true]; // a firewall that is off closes nothing
        }
        $accepts = array_values(array_filter($rules, fn (array $r) => ($r['enable'] ?? true) && ($r['type'] ?? 'in') === 'in' && strtoupper((string) ($r['action'] ?? '')) === 'ACCEPT'));
        $admin = false;
        foreach ($accepts as $rule) {
            $proto = strtolower((string) ($rule['proto'] ?? ''));
            if ($proto !== '' && $proto !== 'tcp') {
                continue;
            }
            foreach (self::ADMIN_PORTS as $port) {
                if (self::covers((string) ($rule['dport'] ?? ''), $port)) {
                    $admin = true;
                }
            }
        }

        return ['reachable' => $accepts !== [], 'admin' => $admin];
    }

    /** Whether a Proxmox port expression — empty, `22`, `20:25`, `22,80,443`, `ssh` — lets this port through. */
    public static function covers(string $dport, int $port): bool
    {
        $dport = strtolower(trim($dport));
        if ($dport === '') {
            return true; // no port given: every port
        }
        foreach (explode(',', $dport) as $part) {
            $part = trim($part);
            if (isset(self::NAMED[$part])) {
                if (self::NAMED[$part] === $port) {
                    return true;
                }

                continue;
            }
            if (preg_match('/^(\d{1,5})[:\-](\d{1,5})$/', $part, $m) === 1) {
                if ($port >= (int) $m[1] && $port <= (int) $m[2]) {
                    return true;
                }

                continue;
            }
            if (ctype_digit($part) && (int) $part === $port) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuse a rule set that would shut the customer out, unless they said they mean it.
     *
     * @param  list<array{action:string, type:string, proto:?string, dport:?string, source:?string, enable:bool}>  $rules
     *
     * @throws DomainError
     */
    public static function assertWayIn(string $action, array $rules, bool $enabled, bool $accepted): void
    {
        if ($accepted) {
            return;
        }
        $assessment = self::assess($rules, $enabled);
        if (! $assessment['reachable']) {
            throw new DomainError('firewall_closes_everything', "{$action}: se zapnutým firewallem a bez jediného povoleného příchozího pravidla bude server ze sítě úplně nedostupný. Pokud to tak chcete, potvrďte to (accept_lockout). Cesta zpět je vždy konzole v panelu služby.", 422, ['field' => 'rules', 'way_back' => 'console']);
        }
        if (! $assessment['admin']) {
            throw new DomainError('firewall_closes_admin', "{$action}: pravidla zavřou vzdálenou správu (SSH 22 ani RDP 3389 nejsou povolené). Pokud se k serveru připojujete jiným portem, potvrďte to (accept_lockout). Cesta zpět je vždy konzole v panelu služby.", 422, ['field' => 'rules', 'way_back' => 'console']);
        }
    }
}
