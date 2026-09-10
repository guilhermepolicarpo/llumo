<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => TeamRole::Member,
        ];
    }

    /**
     * Indicate that the member is the team owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => TeamRole::Owner,
        ]);
    }

    /**
     * Indicate that the member is a team admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => TeamRole::Admin,
        ]);
    }

    /**
     * Indicate that the member is a regular team member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => TeamRole::Member,
        ]);
    }
}
