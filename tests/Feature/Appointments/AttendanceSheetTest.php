<?php

use App\Enums\AppointmentMode;
use App\Enums\TeamRole;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassPrescription;
use App\Models\PassType;
use App\Models\User;
use Database\Factories\AppointmentFactory;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function () {
    Pdf::fake();
});

test('members print the attendance sheet of a waiting appointment filled with the assisted person and the team catalogs', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user, ['name' => 'Missionários', 'legal_name' => 'Casa Espírita Missionários da Luz', 'phone' => '34997292235']);
    Guidance::factory()->for($team)->create(['name' => 'Culto no lar']);
    PassType::factory()->for($team)->create(['name' => 'Passe com 3 médiuns']);
    $appointment = Appointment::factory()->for($team)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Heloisa Garcia da Silva']),
    ]);

    $this->actingAs($user)
        ->get(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $appointment]));

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        expect($pdf->isInline())->toBeTrue()
            ->and($pdf->format)->toBe('a5')
            ->and($pdf->downloadName)->toBe('ficha-de-atendimento-heloisa-garcia-da-silva.pdf')
            ->and($pdf->getHtml())->toContain(
                'Heloisa Garcia da Silva',
                'Casa Espírita Missionários da Luz',
                '(34) 99729-2235',
                'Culto no lar',
                'Passe com 3 médiuns',
            );

        return true;
    });
});

test('the attendance sheet is printable only once the assisted person arrived for an appointment that uses a record', function (Closure $state, bool $usesRecord, bool $printable) {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $appointmentType = AppointmentType::factory()->for($team);
    $appointment = $state(Appointment::factory()->for($team))->create([
        'appointment_type_id' => $usesRecord ? $appointmentType->withRecord() : $appointmentType,
    ]);

    $response = $this->get(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $appointment]));

    $printable ? $response->assertOk() : $response->assertForbidden();
})->with([
    'waiting' => [fn (AppointmentFactory $factory) => $factory->waiting(), true, true],
    'in progress' => [fn (AppointmentFactory $factory) => $factory->inPerson()->inProgress(), true, true],
    'scheduled' => [fn (AppointmentFactory $factory) => $factory->inPerson(), true, false],
    'completed without a record' => [fn (AppointmentFactory $factory) => $factory->inPerson()->completed(), true, false],
    'completed with a record' => [fn (AppointmentFactory $factory) => $factory->inPerson()->completed()->has(AppointmentRecord::factory(), 'record'), true, true],
    'waiting without a record' => [fn (AppointmentFactory $factory) => $factory->waiting(), false, false],
]);

test('the attendance sheet of a completed appointment is printed filled in with its record', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    Guidance::factory()->for($team)->create(['name' => 'Culto no lar']);
    $appointment = Appointment::factory()->for($team)->inPerson()->completed()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $record = AppointmentRecord::factory()->for($appointment)->create([
        'mentor_id' => Mentor::factory()->for($team)->create(['name' => 'Irmã Clara']),
        'fluid_instructions' => 'Tomar em jejum',
        'return_on' => '2026-10-24',
        'observations' => 'Manter o tratamento com regularidade',
    ]);
    $record->fluidicRemedies()->attach(FluidicRemedy::factory()->for($team)->create(['name' => 'Serotonina']), ['position' => 0]);
    $record->guidances()->attach(Guidance::factory()->for($team)->create(['name' => 'Tratamento do copo']), ['detail' => 'Por 21 dias']);
    PassPrescription::factory()->for($record, 'appointmentRecord')->create([
        'pass_type_id' => PassType::factory()->for($team)->create(['name' => 'Hidroterapia']),
        'quantity' => 7,
        'mode' => AppointmentMode::InPerson,
    ]);

    $this->get(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $appointment]));

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        expect($pdf->getHtml())->toContain(
            'Culto no lar',
            'Tratamento do copo',
            'Por 21 dias',
            'Hidroterapia',
            '24/10/2026',
            'Manter o tratamento com regularidade',
            'Serotonina',
            'Tomar em jejum',
            'Irmã Clara',
        );

        return true;
    });
});

test('the fluidic remedies of a filled sheet split into two columns when they would not fit one', function (int $remedyCount, bool $twoColumns) {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $appointment = Appointment::factory()->for($team)->inPerson()->completed()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $record = AppointmentRecord::factory()->for($appointment)->create(['fluid_instructions' => null]);
    FluidicRemedy::factory()->for($team)->count($remedyCount)->create()
        ->each(fn (FluidicRemedy $remedy, int $position) => $record->fluidicRemedies()->attach($remedy, ['position' => $position]));

    $this->get(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $appointment]));

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => str_contains($pdf->getHtml(), 'class="back-columns"') === $twoColumns);
})->with([
    'fitting one column' => [20, false],
    'overflowing one column' => [21, true],
]);

test('the attendance sheet of another team appointment is not found', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $otherTeam = teamOwnedBy(User::factory()->create());
    $appointment = Appointment::factory()->for($otherTeam)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($otherTeam)->withRecord(),
    ]);

    $this->get(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertNotFound();
});

test('the batch prints the sheets of the day waiting appointments that use a record, in arrival order', function () {
    $this->travelTo('2026-09-23 19:30:00');
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $recordType = AppointmentType::factory()->for($team)->withRecord()->create();
    $arrivedLater = Appointment::factory()->for($team)->for($recordType)->waiting()->create(['received_at' => '2026-09-23 19:20:00']);
    $arrivedFirst = Appointment::factory()->for($team)->for($recordType)->waiting()->create(['received_at' => '2026-09-23 19:00:00']);
    Appointment::factory()->for($team)->for($recordType)->inPerson()->create(['scheduled_on' => '2026-09-23']);
    Appointment::factory()->for($team)->for($recordType)->inPerson()->inProgress()->create();
    Appointment::factory()->for($team)->waiting()->create();
    Appointment::factory()->for($team)->for($recordType)->waiting()->create(['scheduled_on' => '2026-09-22']);
    $otherTeam = teamOwnedBy(User::factory()->create());
    Appointment::factory()->for($otherTeam)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($otherTeam)->withRecord(),
    ]);

    $this->get(route('appointments.attendance-sheets', ['current_team' => $team]));

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($arrivedFirst, $arrivedLater) {
        expect($pdf->viewData['appointments']->modelKeys())->toBe([$arrivedFirst->id, $arrivedLater->id])
            ->and($pdf->downloadName)->toBe('fichas-de-atendimento-2026-09-23.pdf');

        return true;
    });
});

test('the batch prints the waiting appointments of the given day', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $appointment = Appointment::factory()->for($team)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'scheduled_on' => '2026-09-22',
    ]);

    $this->get(route('appointments.attendance-sheets', ['current_team' => $team, 'date' => '2026-09-22']));

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['appointments']->modelKeys() === [$appointment->id]);
});

test('the batch is not found when nobody is waiting', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    Appointment::factory()->for($team)->inPerson()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'scheduled_on' => today(),
    ]);

    $this->get(route('appointments.attendance-sheets', ['current_team' => $team]))
        ->assertNotFound();
});

test('the batch rejects a malformed date', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);

    $this->get(route('appointments.attendance-sheets', ['current_team' => $team, 'date' => '22/09/2026']))
        ->assertSessionHasErrors('date');
});

test('the batch of a team the user does not belong to is forbidden', function () {
    actingAsTeamMember(TeamRole::Owner);
    $otherTeam = teamOwnedBy(User::factory()->create());

    $this->get(route('appointments.attendance-sheets', ['current_team' => $otherTeam]))
        ->assertForbidden();
});

test('the appointments list offers to print the sheet of waiting appointments only', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $recordType = AppointmentType::factory()->for($team)->withRecord()->create();
    $scheduled = Appointment::factory()->for($team)->for($recordType)->inPerson()->create(['scheduled_on' => today()]);

    Livewire::test('pages::appointments.index')
        ->assertDontSeeHtml('data-test="appointment-print-sheet-menu-item"')
        ->assertDontSeeHtml('data-test="appointments-print-sheets-button"');

    $scheduled->update(['status' => 'waiting', 'received_at' => now()]);
    Appointment::factory()->for($team)->for($recordType)->waiting()->create();

    $component = Livewire::test('pages::appointments.index')
        ->assertSeeHtml('data-test="appointment-print-sheet-menu-item"')
        ->assertSeeHtml(route('appointments.attendance-sheet', ['current_team' => $team, 'appointment' => $scheduled]))
        ->assertSeeHtml(route('appointments.attendance-sheets', ['current_team' => $team, 'date' => today()->toDateString()]));

    expect($component->html())->toMatch('/data-test="appointments-print-sheets-count">\s*2\s*</');
});
