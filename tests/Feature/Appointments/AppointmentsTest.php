<?php

use App\Enums\AppointmentMode;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('members can open the appointment pages', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->get(route('appointments.index', ['current_team' => $team]))->assertOk();
    $this->get(route('appointments.create', ['current_team' => $team]))->assertOk();
    $this->get(route('appointments.edit', ['current_team' => $team, 'appointment' => $appointment]))->assertOk();
});

test('members can schedule an appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointmentType = AppointmentType::factory()->for($team)->create();
    $assistedPerson = AssistedPerson::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->assertSet('scheduledOn', today()->toDateString())
        ->set('appointmentTypeId', (string) $appointmentType->id)
        ->set('mode', AppointmentMode::Remote->value)
        ->call('selectAssistedPerson', $assistedPerson->id)
        ->set('scheduledOn', '2026-10-01')
        ->set('notes', '  Trazer exames  ')
        ->call('createAppointment')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('appointments.index');

    $appointment = $team->appointments()->sole();

    expect($appointment->appointment_type_id)->toBe($appointmentType->id)
        ->and($appointment->assisted_person_id)->toBe($assistedPerson->id)
        ->and($appointment->mode)->toBe(AppointmentMode::Remote)
        ->and($appointment->scheduled_on->toDateString())->toBe('2026-10-01')
        ->and($appointment->notes)->toBe('Trazer exames');
});

test('the appointment fields are validated', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('mode', 'teleport')
        ->set('scheduledOn', '')
        ->call('createAppointment')
        ->assertHasErrors([
            'appointmentTypeId' => 'required',
            'assistedPersonId' => 'required',
            'mode',
            'scheduledOn' => 'required',
        ]);

    expect(Appointment::count())->toBe(0);
});

test('an appointment type or assisted person from another team is rejected', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy(User::factory()->create());

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('appointmentTypeId', (string) AppointmentType::factory()->for($otherTeam)->create()->id)
        ->set('assistedPersonId', AssistedPerson::factory()->for($otherTeam)->create()->id)
        ->call('createAppointment')
        ->assertHasErrors(['appointmentTypeId', 'assistedPersonId']);

    expect(Appointment::count())->toBe(0);
});

test('a deleted assisted person cannot be scheduled', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('appointmentTypeId', (string) AppointmentType::factory()->for($team)->create()->id)
        ->set('assistedPersonId', AssistedPerson::factory()->for($team)->trashed()->create()->id)
        ->call('createAppointment')
        ->assertHasErrors(['assistedPersonId']);
});

test('assisted person suggestions only list matching people from the current team', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy(User::factory()->create());

    AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);
    AssistedPerson::factory()->for($team)->create(['name' => 'João Souza']);
    AssistedPerson::factory()->for($otherTeam)->create(['name' => 'Maria Oliveira']);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::appointments.create')
        ->set('assistedPersonSearch', 'M');

    expect($component->get('assistedPersonSuggestions'))->toBeEmpty();

    $component->set('assistedPersonSearch', 'Mar')
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->assertDontSee('Maria Oliveira');
});

test('an assisted person from another team cannot be selected', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherPerson = AssistedPerson::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::appointments.create')
        ->call('selectAssistedPerson', $otherPerson->id);
});

test('the form selects records created from the quick-create modals', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointmentType = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    $assistedPerson = AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->dispatch('appointment-type-created', appointmentTypeId: $appointmentType->id)
        ->dispatch('assisted-person-created', assistedPersonId: $assistedPerson->id)
        ->assertSet('appointmentTypeId', (string) $appointmentType->id)
        ->assertSet('assistedPersonId', $assistedPerson->id)
        ->assertSee('Passe')
        ->assertSee('Maria Silva');
});

test('members can update an appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inPerson()->withNotes()->create();
    $newType = AppointmentType::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $appointment])
        ->assertSet('assistedPersonId', $appointment->assisted_person_id)
        ->assertSet('mode', AppointmentMode::InPerson->value)
        ->set('appointmentTypeId', (string) $newType->id)
        ->set('mode', AppointmentMode::Remote->value)
        ->set('scheduledOn', '2026-12-24')
        ->set('notes', '')
        ->call('updateAppointment')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('appointments.index');

    $appointment->refresh();

    expect($appointment->appointment_type_id)->toBe($newType->id)
        ->and($appointment->mode)->toBe(AppointmentMode::Remote)
        ->and($appointment->scheduled_on->toDateString())->toBe('2026-12-24')
        ->and($appointment->notes)->toBeNull();
});

test('members cannot edit another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $appointment])
        ->assertForbidden();
});

test('the index lists the current team appointments filtered by name and date', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
        'scheduled_on' => today(),
    ]);
    Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'João Souza']),
        'scheduled_on' => today()->addDay(),
    ]);
    $otherTeam = teamOwnedBy(User::factory()->create());
    Appointment::factory()->for($otherTeam)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($otherTeam)->create(['name' => 'Ana Oliveira']),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSet('date', today()->toDateString())
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->set('date', '')
        ->assertSee('Maria Silva')
        ->assertSee('João Souza')
        ->assertDontSee('Ana Oliveira')
        ->set('search', 'Maria')
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->set('search', '')
        ->set('date', today()->addDay()->toDateString())
        ->assertSee('João Souza')
        ->assertDontSee('Maria Silva');
});

test('deleting an appointment removes it from the index', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.delete-appointment-modal')
        ->call('confirmDeleteAppointment', $appointment->id, 'Maria Silva')
        ->call('deleteAppointment')
        ->assertDispatched('appointment-deleted');

    expect($appointment->fresh()->trashed())->toBeTrue();

    Livewire::test('pages::appointments.index')
        ->assertDontSee('Maria Silva');
});

test('members cannot delete another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('appointments.delete-appointment-modal')
        ->call('confirmDeleteAppointment', $appointment->id, 'Someone')
        ->call('deleteAppointment');
});
