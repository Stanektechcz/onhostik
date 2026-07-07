<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Customer\Models\Customer;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Brick\Money\Money;

final class PromoCodeService
{
    /**
     * Validate a promo code for a customer and order total.
     *
     * @return array{valid: bool, reason: string|null, code: DiscountCode|null}
     */
    public function validate(string $code, int $customerId, int $orderHaler): array
    {
        $record = DiscountCode::where('code', strtoupper(trim($code)))->first();

        if (!$record) {
            return ['valid' => false, 'reason' => 'Kód neexistuje.', 'code' => null];
        }

        if (!$record->isValid()) {
            if (!$record->is_active) {
                return ['valid' => false, 'reason' => 'Kód je neaktivní.', 'code' => $record];
            }
            if ($record->expires_at?->isPast()) {
                return ['valid' => false, 'reason' => 'Kód vypršel.', 'code' => $record];
            }
            return ['valid' => false, 'reason' => 'Kód byl vyčerpán.', 'code' => $record];
        }

        if (!$record->isUsableByCustomer($customerId)) {
            return ['valid' => false, 'reason' => 'Kód jste již využili.', 'code' => $record];
        }

        if (!$record->isMinOrderMet($orderHaler)) {
            $minKc = number_format($record->min_order_haler / 100, 0, ',', ' ');
            return ['valid' => false, 'reason' => "Minimální hodnota objednávky je {$minKc} Kč.", 'code' => $record];
        }

        return ['valid' => true, 'reason' => null, 'code' => $record];
    }

    /**
     * Record usage of a promo code for a customer/order.
     * Also increments used_count and total_saved_haler on the code.
     */
    public function recordUsage(DiscountCode $code, int $customerId, ?int $orderId, int $savedHaler): DiscountCodeUsage
    {
        $usage = DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'customer_id'      => $customerId,
            'order_id'         => $orderId,
            'saved_haler'      => $savedHaler,
        ]);

        $code->increment('used_count');
        $code->increment('total_saved_haler', $savedHaler);

        return $usage;
    }

    /**
     * Stats for admin reporting: top codes by usage, expiring soon, exhausted.
     *
     * @return array{topCodes: \Illuminate\Database\Eloquent\Collection<int, DiscountCode>, expiringSoon: \Illuminate\Database\Eloquent\Collection<int, DiscountCode>, exhausted: \Illuminate\Database\Eloquent\Collection<int, DiscountCode>}
     */
    public function stats(): array
    {
        $topCodes = DiscountCode::orderByDesc('used_count')->limit(10)->get();

        $expiringSoon = DiscountCode::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->orderBy('expires_at')
            ->get();

        $exhausted = DiscountCode::where('is_active', true)
            ->whereNotNull('max_uses')
            ->whereRaw('used_count >= max_uses')
            ->orderByDesc('used_count')
            ->limit(10)
            ->get();

        return compact('topCodes', 'expiringSoon', 'exhausted');
    }

    /**
     * Per-code usage history for admin detail view.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DiscountCodeUsage>
     */
    public function usageHistory(DiscountCode $code): \Illuminate\Database\Eloquent\Collection
    {
        return $code->usages()
            ->with(['customer', 'order'])
            ->latest()
            ->limit(50)
            ->get();
    }
}
