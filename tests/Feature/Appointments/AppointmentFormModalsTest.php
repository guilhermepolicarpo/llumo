<?php

use App\Enums\BrazilianState;
use App\Enums\TeamRole;
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

test('members can create an assisted person with the full profile from the modal', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Livewire::test('appointments.create-assisted-person-modal')
        ->dispatch('open-create-assisted-person', name: ' Maria Silva ')
        ->assertSet('name', 'Maria Silva')
        ->set('email', 'maria@example.com')
        ->set('city', 'São Paulo')
        ->set('state', 'SP')
        ->call('createAssistedPerson')
        ->assertHasNoErrors()
        ->assertDispatched('assisted-person-created', assistedPersonId: $team->assistedPeople()->sole()->id);

    expect($team->assistedPeople()->sole())
        ->name->toBe('Maria Silva')
        ->email->toBe('maria@example.com')
        ->city->toBe('São Paulo')
        ->state->toBe(BrazilianState::SaoPaulo);
});

test('reopening the assisted person modal clears the previous form', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('appointments.create-assisted-person-modal')
        ->dispatch('open-create-assisted-person', name: 'Maria Silva')
        ->set('email', 'maria@example.com')
        ->set('city', 'São Paulo')
        ->dispatch('open-create-assisted-person', name: 'João Souza')
        ->assertSet('name', 'João Souza')
        ->assertSet('email', '')
        ->assertSet('city', '');
});

test('the assisted person modal requires a name', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Livewire::test('appointments.create-assisted-person-modal')
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

test('an appointment type can be created with a daily limit, or without one', function (string $dailyLimit, ?int $expected) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.create-appointment-type-modal')
        ->set('name', 'Tratamento de Cura')
        ->set('dailyLimit', $dailyLimit)
        ->call('createAppointmentType')
        ->assertHasNoErrors()
        ->assertSet('dailyLimit', '');

    expect($team->appointmentTypes()->sole()->daily_limit)->toBe($expected);
})->with([
    'with a limit' => ['20', 20],
    'without a limit' => ['', null],
]);

test('the daily limit of an appointment type must be a positive number', function (string $dailyLimit) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.create-appointment-type-modal')
        ->set('name', 'Tratamento de Cura')
        ->set('dailyLimit', $dailyLimit)
        ->call('createAppointmentType')
        ->assertHasErrors('dailyLimit');

    expect($team->appointmentTypes()->exists())->toBeFalse();
})->with(['zero' => ['0'], 'text' => ['vinte'], 'too large' => ['1000']]);
