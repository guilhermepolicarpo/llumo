<?php

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassType;
use App\Models\User;
use Livewire\Livewire;

test('opening the record of a waiting appointment starts attending it', function () {
    $this->travelTo('2026-09-20 19:45:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->get(route('appointments.attend', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertOk()
        ->assertSee('data-test="appointment-record-form"', escape: false);

    expect($appointment->fresh())
        ->status->toBe(AppointmentStatus::InProgress)
        ->started_at->toDateTimeString()->toBe('2026-09-20 19:45:00')
        ->attendant_id->toBe($user->id);
});

test('members fill in the record of the appointment they attend', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->inPerson()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'attendant_id' => $user,
    ]);
    $mentor = Mentor::factory()->for($team)->create();
    $remedies = FluidicRemedy::factory()->for($team)->count(2)->create();
    $guidance = Guidance::factory()->for($team)->create();
    $passType = PassType::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) $mentor->id)
        ->set('fluidicRemedyIds', $remedies->map(fn (FluidicRemedy $remedy) => (string) $remedy->id)->all())
        ->set('fluidInstructions', ' 3x ao dia ')
        ->set('guidanceIds', [(string) $guidance->id])
        ->set("guidanceDetails.{$guidance->id}", '7 dias')
        ->call('addPassPrescription')
        ->set('passPrescriptions.0.pass_type_id', (string) $passType->id)
        ->set('passPrescriptions.0.quantity', 5)
        ->set('passPrescriptions.0.mode', AppointmentMode::Remote->value)
        ->set('infiltrationSite', 'Braço direito')
        ->assertSet('infiltrationRemoveOn', $appointment->scheduled_on->addDays(3)->toDateString())
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtHome->value)
        ->set('observations', 'Observação')
        ->call('save')
        ->assertHasNoErrors();

    $record = $appointment->record()->sole();

    expect($record->mentor_id)->toBe($mentor->id)
        ->and($record->fluid_instructions)->toBe('3x ao dia')
        ->and($record->fluidicRemedies->pluck('id')->sort()->values()->all())->toBe($remedies->pluck('id')->sort()->values()->all())
        ->and($record->guidances->sole()->pivot->detail)->toBe('7 dias')
        ->and($record->passPrescriptions->sole()->only(['pass_type_id', 'quantity', 'mode']))->toBe(['pass_type_id' => $passType->id, 'quantity' => 5, 'mode' => AppointmentMode::Remote])
        ->and($record->infiltration_site)->toBe('Braço direito')
        ->and($record->infiltration_remove_on->toDateString())->toBe($appointment->scheduled_on->addDays(3)->toDateString())
        ->and($record->infiltration_removal_place)->toBe(InfiltrationRemovalPlace::AtHome)
        ->and($record->observations)->toBe('Observação')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::InProgress);
});

test('completing the appointment saves the record and finishes it', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'attendant_id' => $user,
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) Mentor::factory()->for($team)->create()->id)
        ->set('observations', 'Tudo certo')
        ->call('complete')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('appointments.index');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->record->observations)->toBe('Tudo certo');
});

test('returning to the queue keeps the unsaved record as a draft', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('observations', 'Rascunho')
        ->call('returnToQueue')
        ->assertRedirectToRoute('appointments.index');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Waiting)
        ->and($appointment->record()->exists())->toBeFalse()
        ->and($appointment->recordDraft->data['observations'])->toBe('Rascunho');
});

test('the record fields are validated', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $otherTeam = teamOwnedBy(User::factory()->create());

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) Mentor::factory()->for($otherTeam)->create()->id)
        ->set('fluidicRemedyIds', [(string) FluidicRemedy::factory()->for($team)->trashed()->create()->id])
        ->call('addPassPrescription')
        ->set('passPrescriptions.0.pass_type_id', (string) PassType::factory()->for($team)->create()->id)
        ->set('passPrescriptions.0.quantity', 100)
        ->set('infiltrationSite', 'Braço')
        ->set('infiltrationRemoveOn', $appointment->scheduled_on->subDay()->toDateString())
        ->set('returnOn', '')
        ->set('schedulesReturn', true)
        ->call('save')
        ->assertHasErrors([
            'mentorId' => 'exists',
            'fluidicRemedyIds.0' => 'exists',
            'passPrescriptions.0.quantity' => 'between',
            'infiltrationRemoveOn' => 'after',
            'infiltrationRemovalPlace' => 'required_with',
            'returnOn' => 'required_if_accepted',
        ]);

    expect($appointment->record()->exists())->toBeFalse();
});

test('members cannot open the record of another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy(User::factory()->create());
    $appointment = Appointment::factory()->for($otherTeam)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($otherTeam)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Waiting);
});

test('appointment types without a record have no record screen', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->waiting()->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->get(route('appointments.attend', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Waiting);
});

test('completed records stay editable and the index opens them', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->completed()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    AppointmentRecord::factory()->for($appointment)->create(['observations' => 'Anotação', 'mentor_id' => Mentor::factory()->for($team)]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSeeHtml('data-test="appointment-record-link"');

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertSet('observations', 'Anotação')
        ->assertDontSeeHtml('data-test="appointment-record-complete-button"')
        ->set('observations', 'Corrigida')
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->record->fresh()->observations)->toBe('Corrigida')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

test('the index sends record appointments to the record screen instead of starting them inline', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    Appointment::factory()->for($team)->waiting()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSeeHtml('data-test="appointment-attend-button"')
        ->assertDontSeeHtml('data-test="appointment-action-start"');
});

test('the history lists only completed records of the same assisted person', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $recordType = AppointmentType::factory()->for($team)->withRecord()->create();
    $person = AssistedPerson::factory()->for($team)->create();
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => $recordType,
        'assisted_person_id' => $person,
    ]);
    $previousRecord = fn (array $attributes) => AppointmentRecord::factory()
        ->for(Appointment::factory()->for($team)->create(['appointment_type_id' => AppointmentType::factory()->for($team)->withRecord()] + $attributes))
        ->create();

    $previousRecord(['assisted_person_id' => $person, 'status' => AppointmentStatus::Completed, 'scheduled_on' => '2026-08-01']);
    $previousRecord(['assisted_person_id' => $person, 'status' => AppointmentStatus::Canceled, 'scheduled_on' => '2026-08-02']);
    $previousRecord(['status' => AppointmentStatus::Completed, 'scheduled_on' => '2026-08-03']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertSee('01/08/2026')
        ->assertDontSee('02/08/2026')
        ->assertDontSee('03/08/2026');
});

test('fluidic remedies keep the order they were picked in', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $apneia = FluidicRemedy::factory()->for($team)->create(['name' => 'Apneia']);
    $calmante = FluidicRemedy::factory()->for($team)->create(['name' => 'Calmante']);
    $vitamina = FluidicRemedy::factory()->for($team)->create(['name' => 'Vitamina D']);
    $pickedOrder = [(string) $vitamina->id, (string) $apneia->id, (string) $calmante->id];

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) Mentor::factory()->for($team)->create()->id)
        ->set('fluidicRemedyIds', $pickedOrder)
        ->assertSeeInOrder(['Vitamina D', 'Apneia', 'Calmante'])
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertSet('fluidicRemedyIds', $pickedOrder);
});

test('follow-up dates must come after the appointment day', function (string $field) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) Mentor::factory()->for($team)->create()->id)
        ->set('infiltrationSite', 'Braço')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtHome->value)
        ->set($field, $appointment->scheduled_on->toDateString())
        ->call('save')
        ->assertHasErrors([$field => 'after'])
        ->set($field, $appointment->scheduled_on->addDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors();
})->with(['infiltrationRemoveOn', 'returnOn']);

test('the mentor is required to save the record', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('observations', 'Sem mentor')
        ->call('complete')
        ->assertHasErrors(['mentorId' => 'required'])
        ->assertNoRedirect();

    expect($appointment->record()->exists())->toBeFalse()
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::InProgress);
});
