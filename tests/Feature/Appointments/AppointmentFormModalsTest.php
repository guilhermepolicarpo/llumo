<?php

use App\Models\AppointmentType;
use App\Models\User;
use Livewire\Livewire;

test('members can create an appointment type from the modal', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.create-appointment-type-modal')
        ->set('name', 'Atendimento fraterno')
        ->call('createAppointmentType')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertDispatched('appointment-type-created', appointmentTypeId: $team->appointmentTypes()->sole()->id);

    expect($team->appointmentTypes()->sole()->name)->toBe('Atendimento fraterno');
});

test('appointment type names are unique per team', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    AppointmentType::factory()->for(teamOwnedBy(User::factory()->create()))->create(['name' => 'Evangelhoterapia']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.create-appointment-type-modal')
        ->set('name', ' Passe ')
        ->call('createAppointmentType')
        ->assertHasErrors(['name' => 'unique'])
        ->assertNotDispatched('appointment-type-created')
        ->set('name', 'Evangelhoterapia')
        ->call('createAppointmentType')
        ->assertHasNoErrors();

    expect($team->appointmentTypes()->count())->toBe(2);
});

test('members can quickly create an assisted person from the modal', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.quick-create-assisted-person-modal')
        ->dispatch('open-quick-create-assisted-person', name: ' Maria Silva ')
        ->assertSet('name', 'Maria Silva')
        ->set('phone', '(11) 98765-4321')
        ->set('birthDate', '1990-05-20')
        ->call('createAssistedPerson')
        ->assertHasNoErrors()
        ->assertDispatched('assisted-person-created', assistedPersonId: $team->assistedPeople()->sole()->id);

    $assistedPerson = $team->assistedPeople()->sole();

    expect($assistedPerson->name)->toBe('Maria Silva')
        ->and($assistedPerson->phone)->toBe('11987654321')
        ->and($assistedPerson->birth_date->toDateString())->toBe('1990-05-20');
});

test('the quick-create assisted person modal requires a name', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.quick-create-assisted-person-modal')
        ->call('createAssistedPerson')
        ->assertHasErrors(['name' => 'required'])
        ->assertNotDispatched('assisted-person-created');

    expect($team->assistedPeople()->count())->toBe(0);
});

test('an appointment type can be created to be attended with a record', function (bool $requiresRecord) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.create-appointment-type-modal')
        ->set('name', 'Tratamento')
        ->set('requiresRecord', $requiresRecord)
        ->call('createAppointmentType')
        ->assertHasNoErrors()
        ->assertSet('requiresRecord', false);

    expect($team->appointmentTypes()->sole()->requires_record)->toBe($requiresRecord);
})->with([
    'with a record' => [true],
    'without a record' => [false],
]);
