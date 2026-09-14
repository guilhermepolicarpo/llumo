<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentType;
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
            $assistedPeople = AssistedPerson::factory()
                ->count(100)
                ->for($team)
                ->withAddress()
                ->withPhone()
                ->withBirthDate()
                ->create();

            $appointmentTypes = AppointmentType::factory()
                ->for($team)
                ->createMany([
                    ['name' => 'Atendimento fraterno'],
                    ['name' => 'Passe'],
                    ['name' => 'Fluidoterapia'],
                    ['name' => 'Evangelho no lar'],
                ])
                ->push(AppointmentType::factory()->for($team)->trashed()->create(['name' => 'Palestra pública']));

            Appointment::factory()
                ->count(150)
                ->for($team)
                ->state(fn () => [
                    'appointment_type_id' => $appointmentTypes->random()->id,
                    'assisted_person_id' => $assistedPeople->random()->id,
                    'notes' => fake()->optional(0.4)->sentence(),
                ])
                ->create();
        });
    }
}
