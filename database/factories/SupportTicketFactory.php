<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'customer_id'   => Customer::factory(),
            'subject'       => $this->faker->sentence(5),
            'status'        => TicketStatus::Open,
            'priority'      => TicketPriority::Normal,
            'department'    => null,
            'last_reply_at' => now(),
            'closed_at'     => null,
            'sla_deadline'  => null,
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status'    => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function answered(): static
    {
        return $this->state(['status' => TicketStatus::Answered]);
    }
}
