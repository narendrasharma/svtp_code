<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
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
            'reference' => 'SUP-'.fake()->unique()->numerify('######'),
            'requester_user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'priority' => 'normal',
            'status' => 'open',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SupportTicket $ticket): void {
            if ($ticket->messages()->count() === 0) {
                $ticket->messages()->create([
                    'user_id' => $ticket->requester_user_id,
                    'body' => fake()->paragraph(),
                ]);
            }
        });
    }
}
