<?php

use App\Enums\LibraryCatalog;
use App\Enums\TeamRole;
use App\Models\Author;
use Livewire\Livewire;

test('admins can create, rename and delete entries of the library catalogs', function (LibraryCatalog $catalog) {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.index', ['libraryCatalog' => $catalog])
        ->call('createEntry')
        ->set('name', '  FEB  ')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $entry = $team->{$catalog->relationName()}()->sole();

    expect($entry->name)->toBe('FEB');

    $component->call('editEntry', $entry->id)
        ->set('name', 'IDE')
        ->call('saveEntry')
        ->call('confirmDeleteEntry', $entry->id)
        ->call('deleteEntry');

    expect($entry->fresh())
        ->name->toBe('IDE')
        ->trashed()->toBeTrue();
})->with(LibraryCatalog::cases());

test('an entry name must be unique within the catalog', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    Author::factory()->for($team)->create(['name' => 'Emmanuel']);

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Author])
        ->call('createEntry')
        ->set('name', 'Emmanuel')
        ->call('saveEntry')
        ->assertHasErrors('name');
});

test('members can create authors from the book form but cannot manage the catalog', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.books.create')
        ->call('createCatalogEntry', LibraryCatalog::Author->value, 'Emmanuel', 'spiritual')
        ->assertSet('spiritualAuthorIds', [(string) $team->authors()->sole()->id]);

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Author])
        ->call('createEntry')
        ->assertForbidden();
});

test('an unknown catalog is not found', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $this->get("/{$team->slug}/library/catalogs/mentors")->assertNotFound();
});

test('the library catalogs list the library navigation instead of the appointment catalogs', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Publisher])
        ->assertSee(__('Loan rules'))
        ->assertDontSee(__('Appointment types'));
});
