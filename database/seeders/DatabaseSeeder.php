<?php

namespace Database\Seeders;

use App\Actions\Appointments\SaveAppointmentRecord;
use App\Enums\AppointmentMode;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Membership;
use App\Models\Mentor;
use App\Models\PassType;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The spiritual mentors who guide the attendances.
     *
     * @var array<int, string>
     */
    private const array MENTORS = [
        'Dr. Bezerra de Menezes',
        'Irmã Scheilla',
        'Joana de Ângelis',
        'André Luiz',
        'Emmanuel',
        'Dr. Augusto Silveira',
        'Irmão Francisco',
        'Irmã Clara',
        'Pai Benedito',
        'Dr. Frederico',
    ];

    /**
     * The fluidic remedies that can be prescribed.
     *
     * @var array<int, string>
     */
    private const array FLUIDIC_REMEDIES = [
        'Dor de Cabeça',
        'Enxaqueca',
        'Serotonina',
        'Coluna',
        'Vitaminas',
        'Vitamina A',
        'Vitamina B',
        'Vitamina C',
        'Vitamina D',
        'Calmante',
        'Ansiedade',
        'Insônia',
        'Imunidade',
        'Circulação',
        'Pressão Alta',
        'Coração',
        'Estômago',
        'Fígado',
        'Rins',
        'Pulmão',
        'Sinusite',
        'Alergia',
        'Artrose',
        'Anti-inflamatório',
        'Cicatrizante',
    ];

    /**
     * The guidances given to assisted people, each with the details the medium may add to it.
     *
     * @var array<string, array<int, string>>
     */
    private const array GUIDANCES = [
        'Fazer o tratamento do copo com água' => ['Por 7 dias', 'Por 21 dias', 'Todas as noites antes de dormir'],
        'Fazer o culto do evangelho no lar' => ['Semanalmente', 'Às quartas-feiras', 'Com toda a família'],
        'Fazer leitura do evangelho e outras obras espíritas' => ['O Evangelho Segundo o Espiritismo', 'O Livro dos Espíritos', 'Nosso Lar'],
    ];

    /**
     * The fluid instructions written on the record.
     *
     * @var array<int, string>
     */
    private const array FLUID_INSTRUCTIONS = [
        'Tomar um copo de água fluidificada em jejum, pela manhã.',
        'Tomar meio copo de água fluidificada três vezes ao dia.',
        'Tomar um copo de água fluidificada antes de dormir, fazendo uma prece.',
    ];

    /**
     * The body sites where infiltrations are applied.
     *
     * @var array<int, string>
     */
    private const array INFILTRATION_SITES = [
        'Região lombar',
        'Ombro esquerdo',
        'Ombro direito',
        'Joelho direito',
        'Joelho esquerdo',
        'Região cervical',
        'Abdômen',
    ];

    /**
     * How many healing treatments are booked on each day, by days from today, so the return occupancy shows
     * a day at the limit, one past it, and one with places left.
     *
     * @var array<int, int>
     */
    private const array HEALING_TREATMENTS_BY_DAYS_AHEAD = [
        0 => 20,
        7 => 25,
        15 => 15,
    ];

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

        $teams->filter()->each(fn (Team $team) => $this->seedTeam($team));
    }

    /**
     * Seed the catalogs, assisted people, and appointments of a team.
     */
    private function seedTeam(Team $team): void
    {
        $assistedPeople = AssistedPerson::factory()
            ->count(20)
            ->for($team)
            ->withAddress()
            ->withPhone()
            ->withBirthDate()
            ->create();

        $healingTreatment = AppointmentType::factory()->for($team)->withRecord()->withDailyLimit(20)->create(['name' => 'Tratamento de Cura']);
        $spiritualIntervention = AppointmentType::factory()->for($team)->withRecord()->create(['name' => 'Intervenção Espiritual']);
        $hydrotherapy = AppointmentType::factory()->for($team)->create(['name' => 'Hidroterapia']);
        $infiltrationRemoval = AppointmentType::factory()->for($team)->create(['name' => 'Retirada de Infiltração']);
        AppointmentType::factory()->for($team)->trashed()->create(['name' => 'Palestra pública']);

        PassType::factory()->for($team)->createMany([
            ['name' => 'Passe com 3 médiuns'],
            ['name' => 'Passe com todos os médiuns'],
            ['name' => 'Hidroterapia'],
        ]);

        Mentor::factory()->for($team)->createMany(array_map(fn (string $name): array => ['name' => $name], self::MENTORS));
        FluidicRemedy::factory()->for($team)->createMany(array_map(fn (string $name): array => ['name' => $name], self::FLUIDIC_REMEDIES));
        Guidance::factory()->for($team)->createMany(array_map(fn (string $name): array => ['name' => $name], array_keys(self::GUIDANCES)));

        $scheduledAppointment = fn () => [
            'appointment_type_id' => fake()->randomElement([$healingTreatment, $healingTreatment, $spiritualIntervention, $hydrotherapy])->id,
            'assisted_person_id' => $assistedPeople->random()->id,
            'notes' => fake()->optional(0.3)->randomElement([
                'Primeira vez no centro.',
                'Vem acompanhada de um familiar.',
                'Pediu para ser atendida no início da noite.',
                'Encaminhada pelo atendimento fraterno.',
            ]),
            'scheduled_on' => today()->toDateString(),
        ];

        $attendantIds = $team->members()->pluck('users.id');
        $attendant = fn () => ['attendant_id' => $attendantIds->random()];

        Appointment::factory()->count(12)->for($team)->state($scheduledAppointment)->create();
        Appointment::factory()->count(4)->for($team)->state($scheduledAppointment)->waiting()->create();
        Appointment::factory()->count(2)->for($team)->state($scheduledAppointment)->inProgress()->state($attendant)->create();
        Appointment::factory()->count(3)->for($team)->remote()->state($scheduledAppointment)->state(fn () => [
            'scheduled_on' => today()->subDays(fake()->numberBetween(1, 3))->toDateString(),
        ])->create();

        $attendedOn = $this->attendedOn(...);

        $completedToday = Appointment::factory()->count(3)->for($team)->state($scheduledAppointment)->completed()->state($attendant)->create();

        $completedEarlier = Appointment::factory()->count(25)->for($team)->state($scheduledAppointment)->completed()->state($attendant)->state(
            fn (array $attributes): array => $attendedOn(today()->subDays(fake()->numberBetween(1, 45)), $attributes['mode']),
        )->create();

        $weekAgo = today()->subDays(7);
        $healingTreatmentWeekAgo = fn (): array => ['appointment_type_id' => $healingTreatment->id, 'scheduled_on' => $weekAgo->toDateString()];

        $completedWeekAgo = Appointment::factory()->count(14)->for($team)->state($scheduledAppointment)->completed()->state($attendant)->state(
            fn (array $attributes): array => ['appointment_type_id' => $healingTreatment->id] + $attendedOn($weekAgo, $attributes['mode']),
        )->create();
        Appointment::factory()->count(3)->for($team)->state($scheduledAppointment)->noShow()->state($healingTreatmentWeekAgo)->create();
        Appointment::factory()->for($team)->state($scheduledAppointment)->canceled()->state($healingTreatmentWeekAgo)->create();
        Appointment::factory()->count(2)->for($team)->state($scheduledAppointment)->state($healingTreatmentWeekAgo)->create();

        Appointment::factory()->count(3)->for($team)->state($scheduledAppointment)->noShow()->state(fn () => [
            'scheduled_on' => today()->subDays(fake()->numberBetween(1, 30))->toDateString(),
        ])->create();

        Appointment::factory()->count(2)->for($team)->state($scheduledAppointment)->canceled()->create();

        $completedToday->concat($completedEarlier)->concat($completedWeekAgo)
            ->load('appointmentType')
            ->filter(fn (Appointment $appointment): bool => $appointment->usesRecord())
            ->each(fn (Appointment $appointment) => app(SaveAppointmentRecord::class)->handle(
                $appointment,
                $this->recordAttributes($team, $appointment, $infiltrationRemoval),
            ));

        $this->fillHealingTreatmentDays($team, $healingTreatment, $assistedPeople);
    }

    /**
     * Get the times of an appointment attended in the evening of the given day.
     *
     * @return array{scheduled_on: string, received_at: ?CarbonInterface, started_at: CarbonInterface, finished_at: CarbonInterface}
     */
    private function attendedOn(CarbonInterface $day, AppointmentMode $mode): array
    {
        $receivedAt = $day->setTime(19, 0)->addMinutes(fake()->numberBetween(0, 60));

        return [
            'scheduled_on' => $receivedAt->toDateString(),
            'received_at' => $mode === AppointmentMode::Remote ? null : $receivedAt,
            'started_at' => $receivedAt->addMinutes(fake()->numberBetween(10, 40)),
            'finished_at' => $receivedAt->addMinutes(fake()->numberBetween(50, 80)),
        ];
    }

    /**
     * Book healing treatments until each day of HEALING_TREATMENTS_BY_DAYS_AHEAD has its number of them,
     * counting the ones already seeded, like the returns scheduled by the records.
     *
     * @param  Collection<int, AssistedPerson>  $assistedPeople
     */
    private function fillHealingTreatmentDays(Team $team, AppointmentType $healingTreatment, Collection $assistedPeople): void
    {
        foreach (self::HEALING_TREATMENTS_BY_DAYS_AHEAD as $daysAhead => $total) {
            $day = today()->addDays($daysAhead);

            $booked = $team->appointments()
                ->where('appointment_type_id', $healingTreatment->id)
                ->scheduledOn($day)
                ->takingPlace()
                ->count();

            Appointment::factory()->count(max(0, $total - $booked))->for($team)->state(fn () => [
                'appointment_type_id' => $healingTreatment->id,
                'assisted_person_id' => $assistedPeople->random()->id,
                'scheduled_on' => $day->toDateString(),
            ])->create();
        }
    }

    /**
     * Build the record a medium would fill while attending the appointment.
     *
     * @return array<string, mixed>
     */
    private function recordAttributes(Team $team, Appointment $appointment, AppointmentType $infiltrationRemoval): array
    {
        /** @var Collection<int, Guidance> $guidances */
        $guidances = $team->guidances()->get()->random(fake()->numberBetween(1, 3));
        $passTypes = $team->passTypes()->get()->random(fake()->numberBetween(1, 2));

        $hasInfiltration = fake()->boolean(30);
        $removalPlace = $hasInfiltration ? fake()->randomElement(InfiltrationRemovalPlace::cases()) : null;
        $returnOn = $appointment->scheduled_on->addWeeks(fake()->randomElement([2, 3, 4]))->max(today()->addDay());

        return [
            'mentor_id' => $team->mentors()->inRandomOrder()->value('id'),
            'fluidic_remedy_ids' => $team->fluidicRemedies()->inRandomOrder()->limit(fake()->numberBetween(1, 4))->pluck('id')->all(),
            'fluid_instructions' => fake()->optional(0.7)->randomElement(self::FLUID_INSTRUCTIONS),
            'guidances' => $guidances->map(fn (Guidance $guidance): array => [
                'guidance_id' => $guidance->id,
                'detail' => fake()->optional(0.5)->randomElement(self::GUIDANCES[$guidance->name]),
            ])->values()->all(),
            'pass_prescriptions' => $passTypes->map(fn (PassType $passType): array => [
                'pass_type_id' => $passType->id,
                'quantity' => fake()->randomElement([3, 5, 7, 10]),
                'mode' => ($passType->name === 'Hidroterapia' ? AppointmentMode::InPerson : fake()->randomElement(AppointmentMode::cases()))->value,
            ])->values()->all(),
            'infiltration_site' => $hasInfiltration ? fake()->randomElement(self::INFILTRATION_SITES) : null,
            'infiltration_remove_on' => $hasInfiltration ? $appointment->scheduled_on->addDays(fake()->numberBetween(7, 15))->max(today()->addDay())->toDateString() : null,
            'infiltration_removal_place' => $removalPlace?->value,
            'removal_appointment_type_id' => $removalPlace?->schedulesRemoval() ? $infiltrationRemoval->id : null,
            'return_on' => fake()->boolean(60) ? $returnOn->toDateString() : null,
            'schedules_return' => true,
            'return_appointment_type_id' => $appointment->appointment_type_id,
            'observations' => fake()->optional(0.4)->randomElement([
                'Relatou melhora das dores desde o último atendimento.',
                'Apresentou sono agitado e muita ansiedade durante a semana.',
                'Recomendado manter o tratamento com regularidade.',
                'Assistida emocionada, relatou momento familiar difícil.',
            ]),
        ];
    }
}
