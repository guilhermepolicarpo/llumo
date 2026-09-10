<?php

use App\Models\AssistedPerson;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

test('the edit form is pre-filled with the assisted person data', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create([
        'name' => 'Maria Silva',
        'birth_date' => '1990-05-20',
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

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.edit', ['assistedPerson' => $assistedPerson])
        ->assertSet('name', 'Maria Silva')
        ->assertSet('birthDate', '1990-05-20')
        ->assertSet('phone', '(11) 98765-4321')
        ->assertSet('email', 'maria@example.com')
        ->assertSet('postalCode', '01310-100')
        ->assertSet('street', 'Avenida Paulista')
        ->assertSet('number', '1578')
        ->assertSet('complement', 'Sala 12')
        ->assertSet('district', 'Bela Vista')
        ->assertSet('city', 'São Paulo')
        ->assertSet('state', 'SP');
});

test('members can update an assisted person', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.edit', ['assistedPerson' => $assistedPerson])
        ->set('name', 'Maria Santos')
        ->set('phone', '(11) 98765-4321')
        ->set('email', 'maria.santos@example.com')
        ->call('updateAssistedPerson')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('assisted-people.index');

    $this->assertDatabaseHas('assisted_people', [
        'id' => $assistedPerson->id,
        'name' => 'Maria Santos',
        'phone' => '11987654321',
        'email' => 'maria.santos@example.com',
    ]);
});

test('clearing an optional field on update sets it to null', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva', 'phone' => '11987654321']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.edit', ['assistedPerson' => $assistedPerson])
        ->set('phone', '')
        ->call('updateAssistedPerson')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assisted_people', [
        'id' => $assistedPerson->id,
        'phone' => null,
    ]);
});

test('the name is required when updating an assisted person', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.edit', ['assistedPerson' => $assistedPerson])
        ->set('name', '')
        ->call('updateAssistedPerson')
        ->assertHasErrors(['name' => 'required']);
});

test('non members cannot view or update another team assisted person', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = teamOwnedBy($owner);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($outsider)
        ->get(route('assisted-people.edit', ['current_team' => $team->slug, 'assistedPerson' => $assistedPerson]))
        ->assertForbidden();
});

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

test('the index paginates the team assisted people 10 per page', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    AssistedPerson::factory()->for($team)->count(11)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::assisted-people.index');

    expect($component->get('assistedPeople')->total())->toBe(11)
        ->and($component->get('assistedPeople')->perPage())->toBe(10)
        ->and($component->get('assistedPeople')->count())->toBe(10);

    $component->call('nextPage')
        ->assertSet('paginators.page', 2);

    expect($component->get('assistedPeople')->count())->toBe(1);
});

test('the index shows the phone or the email, whichever is registered', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->assistedPeople()->create(['name' => 'Ana', 'phone' => '11987654321', 'email' => 'ana@example.com']);
    $team->assistedPeople()->create(['name' => 'Bruno', 'email' => 'bruno@example.com']);
    $team->assistedPeople()->create(['name' => 'Carla']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.index')
        ->assertSee('(11) 98765-4321')
        ->assertDontSee('ana@example.com')
        ->assertSee('bruno@example.com');
});

test('the index splits the address into a street line and a city line', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->assistedPeople()->create([
        'name' => 'Ana',
        'street' => 'Rua das Flores',
        'number' => '100',
        'district' => 'Centro',
        'city' => 'Uberlândia',
        'state' => 'MG',
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.index')
        ->assertSee('Rua das Flores, 100 - Centro')
        ->assertSee('Uberlândia - MG');
});

test('the index shows the age in years, or in months when under a year old', function (int $years, int $months, bool $expectYears) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->assistedPeople()->create([
        'name' => 'Ana',
        'birth_date' => today()->subYears($years)->subMonths($months)->toDateString(),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $expected = $expectYears
        ? trans_choice(':count year|:count years', $years)
        : trans_choice(':count month|:count months', $months);

    Livewire::test('pages::assisted-people.index')
        ->assertSee($expected)
        ->assertDontSee($expectYears
            ? trans_choice(':count month|:count months', $months)
            : trans_choice(':count year|:count years', $years));
})->with([
    'a single whole year, ignoring extra months' => [1, 2, true],
    'whole years, plural' => [5, 0, true],
    'a single month, under a year' => [0, 1, false],
    'months only, plural, under a year' => [0, 2, false],
]);

test('the index highlights the first row and mutes the rest', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->assistedPeople()->create(['name' => 'Ana']);
    $team->assistedPeople()->create(['name' => 'Bruno']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.index')
        ->assertSeeHtml('font-semibold text-zinc-900 dark:text-white">Ana')
        ->assertSeeHtml('text-zinc-600 dark:text-zinc-300">Bruno');
});

test('each row links to the assisted person edit page', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Ana']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.index')
        ->assertSeeHtml(route('assisted-people.edit', ['assistedPerson' => $assistedPerson]));
});

test('members can delete an assisted person', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.delete-assisted-person-modal')
        ->call('confirmDeleteAssistedPerson', $assistedPerson->id, $assistedPerson->name)
        ->call('deleteAssistedPerson')
        ->assertDispatched('assisted-person-deleted');

    $this->assertSoftDeleted('assisted_people', ['id' => $assistedPerson->id]);
});

test('deleting an assisted person removes it from the index', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.delete-assisted-person-modal')
        ->call('confirmDeleteAssistedPerson', $assistedPerson->id, $assistedPerson->name)
        ->call('deleteAssistedPerson');

    Livewire::test('pages::assisted-people.index')
        ->assertDontSee('Maria Silva');
});

test('members cannot delete another team assisted person', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = teamOwnedBy($owner);
    $outsiderTeam = teamOwnedBy($outsider);

    $assistedPerson = $team->assistedPeople()->create(['name' => 'Maria Silva']);

    $this->actingAs($outsider);
    $outsider->switchTeam($outsiderTeam);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::assisted-people.delete-assisted-person-modal')
        ->call('confirmDeleteAssistedPerson', $assistedPerson->id, $assistedPerson->name)
        ->call('deleteAssistedPerson');
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
