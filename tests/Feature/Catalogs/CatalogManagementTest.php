<?php

use App\Enums\Catalog;
use App\Enums\TeamRole;
use App\Models\AppointmentType;
use App\Models\FluidicRemedy;
use App\Models\Mentor;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('only owners and admins can manage the catalogs', function (TeamRole $role, bool $canManage, string $modelClass) {
    [$user, $team] = actingAsTeamMember($role);
    $entry = $modelClass::factory()->for($team)->create();

    expect(Gate::forUser($user)->allows('viewAny', [$modelClass, $team]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [$modelClass, $team]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage', [$modelClass, $team]))->toBe($canManage)
        ->and(Gate::forUser($user)->allows('update', $entry))->toBe($canManage)
        ->and(Gate::forUser($user)->allows('delete', $entry))->toBe($canManage);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
])->with([
    ...collect(Catalog::cases())->mapWithKeys(fn (Catalog $catalog) => [$catalog->value => $catalog->modelClass()])->all(),
    'appointment type' => AppointmentType::class,
]);

test('admins can create, rename and delete catalog entries', function (Catalog $catalog) {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.index', ['catalog' => $catalog])
        ->set('name', '  Primeira  ')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $entry = $team->{$catalog->relationName()}()->sole();

    expect($entry->name)->toBe('Primeira');

    $component
        ->call('editEntry', $entry->id)
        ->assertSet('name', 'Primeira')
        ->set('name', 'Renomeada')
        ->call('saveEntry')
        ->assertHasNoErrors()
        ->call('confirmDeleteEntry', $entry->id)
        ->call('deleteEntry');

    expect($entry->fresh()->name)->toBe('Renomeada');
    $this->assertSoftDeleted($entry);
})->with(Catalog::cases());

test('catalog pages list only the current team non-deleted entries filtered by name', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    Mentor::factory()->for($team)->create(['name' => 'Bezerra de Menezes']);
    Mentor::factory()->for($team)->create(['name' => 'Joana de Ângelis']);
    Mentor::factory()->for($team)->trashed()->create(['name' => 'Mentor excluído']);
    Mentor::factory()->for(teamOwnedBy(User::factory()->create()))->create(['name' => 'De outra casa']);

    Livewire::test('pages::catalogs.index', ['catalog' => Catalog::Mentor])
        ->assertSeeInOrder(['Bezerra de Menezes', 'Joana de Ângelis'])
        ->assertDontSee('Mentor excluído')
        ->assertDontSee('De outra casa')
        ->set('search', 'Joana')
        ->assertSee('Joana de Ângelis')
        ->assertDontSee('Bezerra de Menezes');
});

test('members cannot open the catalogs', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::catalogs.index', ['catalog' => Catalog::Guidance])->assertForbidden();
});

test('entries of another team cannot be edited or deleted', function () {
    actingAsTeamMember(TeamRole::Owner);
    $foreignEntry = PassType::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    Livewire::test('pages::catalogs.index', ['catalog' => Catalog::PassType])
        ->call('editEntry', $foreignEntry->id)
        ->assertNotFound();

    Livewire::test('pages::catalogs.index', ['catalog' => Catalog::PassType])
        ->call('confirmDeleteEntry', $foreignEntry->id)
        ->assertNotFound();

    $this->assertNotSoftDeleted($foreignEntry);
});

test('catalog entry names must be unique among the team active entries', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $existing = FluidicRemedy::factory()->for($team)->create(['name' => 'Fluídico A']);
    $other = FluidicRemedy::factory()->for($team)->create(['name' => 'Fluídico B']);
    FluidicRemedy::factory()->for($team)->trashed()->create(['name' => 'Fluídico excluído']);

    Livewire::test('pages::catalogs.index', ['catalog' => Catalog::FluidicRemedy])
        ->set('name', 'Fluídico A')
        ->call('saveEntry')
        ->assertHasErrors(['name' => 'unique'])
        ->call('editEntry', $other->id)
        ->set('name', 'Fluídico A')
        ->call('saveEntry')
        ->assertHasErrors(['name' => 'unique'])
        ->call('editEntry', $existing->id)
        ->call('saveEntry')
        ->assertHasNoErrors()
        ->set('name', 'Fluídico excluído')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect($other->fresh()->name)->toBe('Fluídico B');
});

test('catalog pages are addressed by their plural hyphenated slug', function (Catalog $catalog, string $slug) {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $this->get("/{$team->slug}/settings/catalogs/{$slug}")
        ->assertOk()
        ->assertSee($catalog->label());
})->with([
    'mentor' => [Catalog::Mentor, 'mentors'],
    'fluidic remedy' => [Catalog::FluidicRemedy, 'fluidic-remedies'],
    'guidance' => [Catalog::Guidance, 'guidances'],
    'pass type' => [Catalog::PassType, 'pass-types'],
]);

test('unknown catalogs are not found', function (string $slug) {
    [, $team] = actingAsTeamMember(TeamRole::Owner);

    $this->get("/{$team->slug}/settings/catalogs/{$slug}")->assertNotFound();
})->with(['unknown', 'fluidic_remedy']);

test('catalog pages show 5 entries per page by default and let the admin show more', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    Mentor::factory()->for($team)->count(6)->create();

    $component = Livewire::test('pages::catalogs.index', ['catalog' => Catalog::Mentor]);

    expect($component->get('entries')->perPage())->toBe(5)
        ->and($component->get('entries')->count())->toBe(5);

    $component->set('perPage', 10);

    expect($component->get('entries')->count())->toBe(6);
});
