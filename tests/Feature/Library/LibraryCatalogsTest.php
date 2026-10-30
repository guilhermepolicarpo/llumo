<?php

use App\Enums\AuthorRole;
use App\Enums\LibraryCatalog;
use App\Enums\TeamRole;
use App\Models\Author;
use App\Models\Book;
use Livewire\Livewire;

test('admins can create, rename and delete entries of the library catalogs', function (LibraryCatalog $catalog) {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.index', ['libraryCatalog' => $catalog])
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
        ->assertForbidden();
});

test('an unknown catalog is not found', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $this->get("/{$team->slug}/settings/library/mentors")->assertNotFound();
});

test('the settings navigation groups the center, attendance and library pages', function () {
    actingAsTeamMember(TeamRole::Admin);

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Publisher])
        ->assertSeeInOrder([__('Center'), __('Center details'), __('Members'), __('Attendance'), __('Appointment types'), __('Library'), __('Loan rules')]);
});

test('the authors list shows how many books each author has', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $kardec = Author::factory()->for($team)->create(['name' => 'Allan Kardec']);
    Author::factory()->for($team)->create(['name' => 'Léon Denis']);
    Book::factory()->for($team)->count(2)->create()
        ->each(fn (Book $book) => $book->authors()->attach($kardec, ['role' => AuthorRole::Incarnate->value]));

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Author])
        ->assertSeeInOrder(['Allan Kardec', '2', 'Léon Denis', '0']);

    Livewire::test('pages::catalogs.index', ['libraryCatalog' => LibraryCatalog::Publisher])
        ->assertDontSeeHtml('data-test="catalog-entry-books-count"');
});
