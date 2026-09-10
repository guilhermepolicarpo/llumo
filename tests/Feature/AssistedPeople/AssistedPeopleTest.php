<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('members can create an assisted person with only a name', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->call('createAssistedPerson')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('assisted-people.index');

    $this->assertDatabaseHas('assisted_people', [
        'team_id' => $team->id,
        'name' => 'Maria Silva',
        'phone' => null,
        'email' => null,
    ]);

    expect($team->assistedPeople()->first()->birth_date)->toBeNull();
});

test('members can create an assisted person with the full profile', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('birthDate', '1990-05-20')
        ->set('phone', '(11) 98765-4321')
        ->set('email', 'maria@example.com')
        ->set('postalCode', '01310-100')
        ->set('street', 'Avenida Paulista')
        ->set('number', '1578')
        ->set('complement', 'Sala 12')
        ->set('district', 'Bela Vista')
        ->set('city', 'São Paulo')
        ->set('state', 'SP')
        ->call('createAssistedPerson')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assisted_people', [
        'team_id' => $team->id,
        'name' => 'Maria Silva',
        'phone' => '11987654321',
        'email' => 'maria@example.com',
        'postal_code' => '01310100',
        'street' => 'Avenida Paulista',
        'number' => '1578',
        'complement' => 'Sala 12',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ]);

    expect($team->assistedPeople()->first()->birth_date->toDateString())->toBe('1990-05-20');
});

test('the name is required', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', '')
        ->call('createAssistedPerson')
        ->assertHasErrors(['name' => 'required']);
});

test('the phone must be a valid brazilian phone number', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('phone', '123')
        ->call('createAssistedPerson')
        ->assertHasErrors('phone');
});

test('the email must be a valid email address', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('email', 'not-an-email')
        ->call('createAssistedPerson')
        ->assertHasErrors('email');
});

test('the postal code must be a valid brazilian postal code', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('postalCode', '123')
        ->call('createAssistedPerson')
        ->assertHasErrors('postalCode');
});

test('the birth date cannot be in the future', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('birthDate', now()->addDay()->toDateString())
        ->call('createAssistedPerson')
        ->assertHasErrors('birthDate');
});

test('blank optional fields pass validation', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('name', 'Maria Silva')
        ->set('birthDate', '')
        ->set('phone', '')
        ->set('email', '')
        ->set('postalCode', '')
        ->call('createAssistedPerson')
        ->assertHasNoErrors();
});

test('non members cannot view or create assisted people', function (string $routeName) {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = teamOwnedBy($owner);

    $this->actingAs($outsider)
        ->get(route($routeName, ['current_team' => $team->slug]))
        ->assertForbidden();
})->with([
    'index' => 'assisted-people.index',
    'create' => 'assisted-people.create',
]);

test('the index lists the team assisted people ordered by name', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->assistedPeople()->create(['name' => 'Zeca']);
    $team->assistedPeople()->create(['name' => 'Ana']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.index')
        ->assertSeeInOrder(['Ana', 'Zeca']);
});

test('the postal code lookup fills the blank address fields', function () {
    Http::fake([
        'brasilapi.com.br/api/cep/v2/01310100' => Http::response([
            'cep' => '01310100',
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.create')
        ->set('postalCode', '01310-100')
        ->assertSet('street', 'Avenida Paulista')
        ->assertSet('district', 'Bela Vista')
        ->assertSet('city', 'São Paulo')
        ->assertSet('state', 'SP');
});
