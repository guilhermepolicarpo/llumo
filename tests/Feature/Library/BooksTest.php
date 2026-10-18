<?php

use App\Enums\AuthorRole;
use App\Enums\TeamRole;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCollection;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(fn () => Http::preventStrayRequests());

/**
 * Fake the two catalogs answering for Nosso Lar, with the cover Open Library links to.
 */
function fakeNossoLarLookup(?string $coverImage = null): void
{
    Http::fake([
        'https://brasilapi.com.br/api/isbn/v1/9788573289442' => Http::response([
            'title' => 'Nosso Lar',
            'authors' => ['FRANCISCO CÂNDIDO XAVIER'],
            'publisher' => 'FEB',
            'year' => 2019,
            'cover_url' => null,
        ]),
        'https://openlibrary.org/api/books*' => Http::response(['ISBN:9788573289442' => [
            'title' => 'Nosso Lar',
            'authors' => [['name' => 'Francisco Cândido Xavier'], ['name' => 'André Luiz']],
            'cover' => ['large' => 'https://covers.openlibrary.org/b/id/10545010-L.jpg'],
        ]]),
        'https://covers.openlibrary.org/b/id/10545010-L.jpg' => Http::response($coverImage ?? fakeCoverImage(), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function fakeCoverImage(int $width = 300, int $height = 450): string
{
    $image = UploadedFile::fake()->image('cover.jpg', $width, $height);

    return $image->getContent();
}

test('members can register a book with incarnate and spiritual authors and its first copies', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $medium = Author::factory()->for($team)->create(['name' => 'Francisco Cândido Xavier']);
    $secondMedium = Author::factory()->for($team)->create(['name' => 'Waldo Vieira']);
    $spirit = Author::factory()->for($team)->create(['name' => 'André Luiz']);

    Livewire::test('pages::library.books.create')
        ->set('title', 'Evolução em Dois Mundos')
        ->set('isbn', '978-85-7328-944-2')
        ->set('incarnateAuthorIds', [(string) $medium->id, (string) $secondMedium->id])
        ->set('spiritualAuthorIds', [(string) $spirit->id])
        ->set('copiesCount', '2')
        ->call('createBook')
        ->assertHasNoErrors()
        ->assertRedirect();

    $book = $team->books()->sole();

    expect($book->isbn)->toBe('9788573289442')
        ->and($book->authorsWithRole(AuthorRole::Incarnate)->pluck('name')->all())->toBe(['Francisco Cândido Xavier', 'Waldo Vieira'])
        ->and($book->authorsWithRole(AuthorRole::Spiritual)->pluck('name')->all())->toBe(['André Luiz'])
        ->and($book->copies()->orderBy('code')->pluck('code')->all())->toBe([1, 2]);
});

test('the title is required and the isbn must be valid', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.books.create')
        ->set('isbn', '9788573289443')
        ->call('createBook')
        ->assertHasErrors(['title' => 'required', 'isbn']);
});

test('two books of the library cannot share an isbn, whatever way it is typed', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Book::factory()->for($team)->create(['isbn' => '9788573289442']);

    Livewire::test('pages::library.books.create')
        ->set('title', 'Nosso Lar')
        ->set('isbn', '85-7328-944-9')
        ->call('createBook')
        ->assertHasErrors('isbn');
});

test('authors of another team cannot be given to a book', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $foreignAuthor = Author::factory()->create();

    Livewire::test('pages::library.books.create')
        ->set('title', 'Nosso Lar')
        ->set('incarnateAuthorIds', [(string) $foreignAuthor->id])
        ->call('createBook')
        ->assertHasErrors('incarnateAuthorIds.0');

    expect($team->books()->exists())->toBeFalse();
});

test('looking up the isbn fills the form, selects known authors in their usual role and suggests the others', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    fakeNossoLarLookup();

    $medium = Author::factory()->for($team)->create(['name' => 'Francisco Candido Xavier']);
    $publisher = Publisher::factory()->for($team)->create(['name' => 'FEB']);

    Livewire::test('pages::library.books.create')
        ->set('isbn', '8573289449')
        ->call('lookUpIsbn')
        ->assertHasNoErrors()
        ->assertSet('isbn', '9788573289442')
        ->assertSet('title', 'Nosso Lar')
        ->assertSet('publishedYear', '2019')
        ->assertSet('incarnateAuthorIds', [(string) $medium->id])
        ->assertSet('spiritualAuthorIds', [])
        ->assertSet('suggestedAuthorNames', ['André Luiz'])
        ->assertSet('publisherId', (string) $publisher->id)
        ->assertSet('foundCoverUrl', 'https://covers.openlibrary.org/b/id/10545010-L.jpg')
        ->call('addSuggestedAuthor', 0, AuthorRole::Spiritual->value)
        ->assertSet('suggestedAuthorNames', []);

    $spirit = $team->authors()->where('name', 'André Luiz')->sole();

    expect($spirit)->not->toBeNull();
});

test('an author already used as a spirit is selected as a spiritual author by the lookup', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    fakeNossoLarLookup();

    $spirit = Author::factory()->for($team)->create(['name' => 'André Luiz']);
    Book::factory()->for($team)->create()->authors()->attach($spirit, ['role' => AuthorRole::Spiritual->value]);

    Livewire::test('pages::library.books.create')
        ->set('isbn', '9788573289442')
        ->call('lookUpIsbn')
        ->assertSet('spiritualAuthorIds', [(string) $spirit->id]);
});

test('the cover found by the lookup is downloaded and stored with the book', function () {
    Storage::fake('public');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    fakeNossoLarLookup();

    Livewire::test('pages::library.books.create')
        ->set('isbn', '9788573289442')
        ->call('lookUpIsbn')
        ->call('createBook')
        ->assertHasNoErrors();

    $book = $team->books()->sole();

    expect($book->cover_path)->toStartWith('book-covers/');
    Storage::disk('public')->assertExists($book->cover_path);
});

test('a found cover that is not an image is not stored, and the book is saved without one', function () {
    Storage::fake('public');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    fakeNossoLarLookup('<html>not an image</html>');

    Livewire::test('pages::library.books.create')
        ->set('isbn', '9788573289442')
        ->call('lookUpIsbn')
        ->call('createBook')
        ->assertHasNoErrors();

    expect($team->books()->sole()->cover_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a found cover larger than the allowed dimensions is not stored', function () {
    Storage::fake('public');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    fakeNossoLarLookup(fakeCoverImage(2100, 40));

    Livewire::test('pages::library.books.create')
        ->set('isbn', '9788573289442')
        ->call('lookUpIsbn')
        ->call('createBook');

    expect($team->books()->sole()->cover_path)->toBeNull();
});

test('the browser cannot choose the cover url the server downloads', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.books.create')
        ->set('foundCoverUrl', 'https://internal.example/secret.jpg');
})->throws(CannotUpdateLockedPropertyException::class);

test('an uploaded cover replaces the current one, which is deleted', function () {
    Storage::fake('public');
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    Storage::disk('public')->put('book-covers/old.jpg', 'old');
    $book = Book::factory()->for($team)->create(['cover_path' => 'book-covers/old.jpg']);

    Livewire::test('pages::library.books.edit', ['book' => $book])
        ->set('cover', UploadedFile::fake()->image('new.jpg', 300, 450))
        ->call('updateBook')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->cover_path)->not->toBe('book-covers/old.jpg');
    Storage::disk('public')->assertMissing('book-covers/old.jpg');
    Storage::disk('public')->assertExists($book->cover_path);
});

test('the current cover can be removed', function () {
    Storage::fake('public');
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    Storage::disk('public')->put('book-covers/old.jpg', 'old');
    $book = Book::factory()->for($team)->create(['cover_path' => 'book-covers/old.jpg']);

    Livewire::test('pages::library.books.edit', ['book' => $book])
        ->call('removeCoverImage')
        ->call('updateBook')
        ->assertHasNoErrors();

    expect($book->fresh()->cover_path)->toBeNull();
    Storage::disk('public')->assertMissing('book-covers/old.jpg');
});

test('an uploaded cover must be an image', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.books.create')
        ->set('title', 'Nosso Lar')
        ->set('cover', UploadedFile::fake()->create('cover.svg', 10, 'image/svg+xml'))
        ->call('createBook')
        ->assertHasErrors('cover');
});

test('editing a book keeps its authors in their roles and updates them', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $medium = Author::factory()->for($team)->create();
    $spirit = Author::factory()->for($team)->create();
    $book = Book::factory()->for($team)->create();
    $book->authors()->attach($medium, ['role' => AuthorRole::Incarnate->value]);

    Livewire::test('pages::library.books.edit', ['book' => $book])
        ->assertSet('incarnateAuthorIds', [(string) $medium->id])
        ->set('spiritualAuthorIds', [(string) $spirit->id])
        ->set('title', 'Novo título')
        ->call('updateBook')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->title)->toBe('Novo título')
        ->and($book->authorsWithRole(AuthorRole::Spiritual)->pluck('id')->all())->toBe([$spirit->id])
        ->and($book->authorsWithRole(AuthorRole::Incarnate)->pluck('id')->all())->toBe([$medium->id]);
});

test('members cannot edit or delete books', function () {
    [$user, $team] = actingAsTeamMember(TeamRole::Member);

    $book = Book::factory()->for($team)->create();

    $this->get(route('library.books.edit', ['current_team' => $team, 'book' => $book]))->assertForbidden();

    Livewire::test('pages::library.books.show', ['book' => $book])
        ->call('deleteBook')
        ->assertForbidden();
});

test('a book with a lent copy cannot be deleted', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $copy = BookCopy::factory()->for(Book::factory()->for($team))->create();
    BookLoan::factory()->for($copy, 'copy')->create();

    Livewire::test('pages::library.books.show', ['book' => $copy->book])
        ->call('deleteBook');

    expect($copy->book->fresh()->trashed())->toBeFalse();
});

test('deleting a book also deletes its copies and keeps their loans', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $copy = BookCopy::factory()->for(Book::factory()->for($team))->create();
    $loan = BookLoan::factory()->for($copy, 'copy')->returned()->create();

    Livewire::test('pages::library.books.show', ['book' => $copy->book])
        ->call('deleteBook')
        ->assertRedirectToRoute('library.books.index');

    expect($copy->fresh()->trashed())->toBeTrue()
        ->and($loan->fresh())->not->toBeNull();
});

test('non members cannot open the library', function (string $routeName) {
    $team = teamOwnedBy(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route($routeName, ['current_team' => $team]))
        ->assertForbidden();
})->with([
    'books' => 'library.books.index',
    'new book' => 'library.books.create',
    'loans' => 'library.loans.index',
    'new loan' => 'library.loans.create',
    'loan rules' => 'team-settings.loan-rules',
]);

test('a book cannot be opened under another team the user belongs to', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy($user);

    $book = Book::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('library.books.show', ['current_team' => $otherTeam, 'book' => $book]))
        ->assertNotFound();
});

test('the library pages render for members', function (string $routeName) {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $copy = BookCopy::factory()->for(Book::factory()->for($team)->withIsbn())->create();
    BookLoan::factory()->for($copy, 'copy')->overdue()->create();

    $this->get(route($routeName, ['current_team' => $team, 'book' => $copy->book]))->assertOk();
})->with([
    'books' => 'library.books.index',
    'new book' => 'library.books.create',
    'book' => 'library.books.show',
    'loans' => 'library.loans.index',
    'new loan' => 'library.loans.create',
]);

test('the books can be searched by title, author or isbn and filtered by availability', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $nossoLar = Book::factory()->for($team)->create(['title' => 'Nosso Lar', 'isbn' => '9788573289442']);
    $paulo = Book::factory()->for($team)->create(['title' => 'Paulo e Estêvão']);
    $paulo->authors()->attach(Author::factory()->for($team)->create(['name' => 'Emmanuel']), ['role' => AuthorRole::Spiritual->value]);
    BookCopy::factory()->for($nossoLar)->create();
    BookLoan::factory()->for(BookCopy::factory()->for($paulo), 'copy')->create();

    Livewire::test('pages::library.books.index')
        ->set('search', 'emmanuel')
        ->assertSee('Paulo e Estêvão')
        ->assertDontSee('Nosso Lar')
        ->set('search', '978-85-7328-944-2')
        ->assertSee('Nosso Lar')
        ->assertDontSee('Paulo e Estêvão')
        ->set('search', '')
        ->set('availableOnly', true)
        ->assertSee('Nosso Lar')
        ->assertDontSee('Paulo e Estêvão');
});

test('the books can be filtered by collection', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $collection = BookCollection::factory()->for($team)->create();
    Book::factory()->for($team)->create(['title' => 'Nosso Lar', 'book_collection_id' => $collection->id]);
    Book::factory()->for($team)->create(['title' => 'Paulo e Estêvão']);

    Livewire::test('pages::library.books.index')
        ->set('collectionId', (string) $collection->id)
        ->assertSee('Nosso Lar')
        ->assertDontSee('Paulo e Estêvão');
});

test('the book list shows 5 books per page by default and lets the member show more', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    Book::factory()->for($team)->count(6)->create();

    $component = Livewire::test('pages::library.books.index');

    expect($component->get('books')->perPage())->toBe(5)
        ->and($component->get('books')->count())->toBe(5);

    $component->set('perPage', 10);

    expect($component->get('books')->count())->toBe(6);
});
