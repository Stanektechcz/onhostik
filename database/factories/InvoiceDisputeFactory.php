<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Models\InvoiceDispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDispute>
 */
class InvoiceDisputeFactory extends Factory
{
    protected $model = InvoiceDispute::class;

    public function definition(): array
    {
        return [
            'invoice_id'  => Invoice::factory(),
            'customer_id' => Customer::factory(),
            'reason'      => $this->faker->sentence(10),
            'status'      => 'open',
            'admin_note'  => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }
}
