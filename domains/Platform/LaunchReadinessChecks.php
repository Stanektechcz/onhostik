<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Onhost\Domain\Catalog\SellSwitch;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;

/**
 * Doctor rows that make the fail-closed launch switches visible (phase W, W2). Both are standing WARN, never FAIL: a closed
 * switch is the owner's safe default, not a broken installation. Read-only, config only, no outbound call.
 *
 *  - the sell matrix (W3, config/onhost-sell.php): which product families are for sale NEW and which are off;
 *  - the Comgate notification allow-list (W1): empty means every notification is refused.
 */
final class LaunchReadinessChecks
{
    /** Row names: deploy-gate.php and the staging runbook's expected-nonok list (`area|check`) refer to them. */
    public const SELL_ROW = 'product families are for sale';

    public const COMGATE_ROW = 'Comgate notification allow-list is filled';

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    public function sellMatrix(): array
    {
        $selling = [];
        $off = [];
        foreach (array_keys((array) config('onhost-sell.families', [])) as $family) {
            SellSwitch::allows((string) $family) ? $selling[] = $family : $off[] = $family;
        }

        if ($selling === []) {
            return $this->row('sales', self::SELL_ROW, false, 'nothing is for sale (ONHOST_SELL_DEFAULT off) — every family is off: '.implode(', ', $off).'; set ONHOST_SELL_DEFAULT=true or a family ONHOST_SELL_<FAMILY>=true to open sales');
        }

        return $this->row('sales', self::SELL_ROW, true, 'for sale: '.implode(', ', $selling).($off === [] ? '' : '; off: '.implode(', ', $off)));
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    public function comgateAllowlist(): array
    {
        $count = count(ComgatePaymentProvider::callbackAllowlist());

        return $this->row('payments', self::COMGATE_ROW, $count > 0, $count > 0
            ? $count.' address entries; notifications from other addresses are refused'
            : 'every notification is refused; payments settle via return/sweep — add the addresses Comgate publishes to COMGATE_CALLBACK_IPS');
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    private function row(string $area, string $check, bool $ok, string $detail): array
    {
        return ['area' => $area, 'check' => $check, 'ok' => $ok, 'detail' => $detail, 'blocking' => false];
    }
}
