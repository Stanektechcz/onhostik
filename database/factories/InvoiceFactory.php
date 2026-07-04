<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'customer_id'     => Customer::factory(),
            'order_id'        => null,
            'type'            => InvoiceType::Proforma,
            'series'          => InvoiceSeries::Czech->value,
            'number'          => 'CZ-TEST-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'status'          => InvoiceStatus::Sent,
            'vat_scenario'    => VatScenario::CzechB2C,
            'currency'        => Currency::CZK,
            'subtotal'        => Money::of(826, 'CZK'),
            'tax_amount'      => Money::of(173, 'CZK'),
            'total'           => Money::of(999, 'CZK'),
            'variable_symbol' => str_pad((string) ($seq * 7 + 1000000), 10, '0', STR_PAD_LEFT),
            'issue_date'      => now()->subDays(10)->toDateString(),
            'due_date'        => now()->addDays(0)->toDateString(),
            // snapshot fields (nullable — valid on proformas before payment)
            'snapshot_name'   => $this->faker->name(),
            'snapshot_street' => $this->faker->streetAddress(),
            'snapshot_city'   => 'Praha',
            'snapshot_zip'    => '11000',
            'snapshot_country_code' => 'CZ',
        ];
    }

    public function overdue(): static
    {
        return $this->state([
            'status'   => InvoiceStatus::Overdue,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }
}
