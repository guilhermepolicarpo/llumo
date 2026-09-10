<?php

namespace Database\Seeders;

use App\Models\AssistedPerson;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $team = $owner->currentTeam;

        $members = User::factory()->count(3)->create();

        $teams = collect([$team]);

        foreach ($members as $index => $member) {
            Membership::factory()->for($team)->for($member)->member()->create();

            $ownerMembership = Membership::factory()->for($member->currentTeam)->for($owner);

            $index < 2
                ? $ownerMembership->admin()->create()
                : $ownerMembership->member()->create();

            $teams->push($member->currentTeam);
        }

        $teams->each(function (Team $team) {
            AssistedPerson::factory()
                ->count(20)
                ->for($team)
                ->withAddress()
                ->withPhone()
                ->withBirthDate()
                ->create();
        });
    }
}
