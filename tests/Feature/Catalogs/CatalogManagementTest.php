<?php

use App\Enums\Catalog;
use App\Enums\TeamRole;
use App\Models\AppointmentType;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
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
        ->call('createEntry')
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
    [, $team] = actingAsTeamMember(TeamRole::Member);
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

test('members can view the catalogs but cannot change them', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $entry = Guidance::factory()->for($team)->create(['name' => 'Evangelho no lar']);

    $this->get(route('catalogs.index', ['current_team' => $team, 'catalog' => Catalog::Guidance]))
        ->assertOk()
        ->assertSee('Evangelho no lar')
        ->assertDontSee('data-test="catalog-entry-new-button"', false)
        ->assertDontSee('data-test="catalog-entry-actions-trigger"', false)
        ->assertDontSee('data-test="catalog-entry-edit-row"', false);

    $component = fn () => Livewire::test('pages::catalogs.index', ['catalog' => Catalog::Guidance]);

    $component()->call('createEntry')->assertForbidden();
    $component()->set('name', 'Nova')->call('saveEntry')->assertForbidden();
    $component()->call('editEntry', $entry->id)->assertForbidden();
    $component()->call('confirmDeleteEntry', $entry->id)->assertForbidden();

    expect($team->guidances()->pluck('name')->all())->toBe(['Evangelho no lar']);
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
        ->call('createEntry')
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
        ->call('createEntry')
        ->set('name', 'Fluídico excluído')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect($other->fresh()->name)->toBe('Fluídico B');
});

test('unknown catalogs are not found', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);

    $this->get("/{$team->slug}/catalogs/unknown")->assertNotFound();
});
