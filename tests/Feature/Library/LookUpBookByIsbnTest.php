<?php

use App\Actions\Library\LookUpBookByIsbn;
use App\Data\BookLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

const BRASIL_API_NOSSO_LAR = 'https://brasilapi.com.br/api/isbn/v1/9788573289442';
const OPEN_LIBRARY_NOSSO_LAR = 'https://openlibrary.org/api/books?bibkeys=ISBN%3A9788573289442&jscmd=data&format=json';
const OPEN_LIBRARY_DETAILS_NOSSO_LAR = 'https://openlibrary.org/api/books?bibkeys=ISBN%3A9788573289442&jscmd=details&format=json';

/**
 * Fake what BrasilAPI and the two Open Library answers say about Nosso Lar; an empty Open Library answer
 * means it does not know the book.
 *
 * @param  array<string, mixed>  $brasilApi
 * @param  array<string, mixed>  $openLibrary
 * @param  array<string, mixed>  $openLibraryDetails
 */
function fakeNossoLarCatalogs(array $brasilApi, array $openLibrary = [], array $openLibraryDetails = []): void
{
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::response($brasilApi),
        OPEN_LIBRARY_NOSSO_LAR => Http::response($openLibrary === [] ? [] : ['ISBN:9788573289442' => $openLibrary]),
        OPEN_LIBRARY_DETAILS_NOSSO_LAR => Http::response($openLibraryDetails === [] ? [] : ['ISBN:9788573289442' => ['details' => $openLibraryDetails]]),
    ]);
}

test('the lookup completes brasil api with the spiritual author and the cover found on open library', function () {
    fakeNossoLarCatalogs([
        'title' => 'Nosso Lar',
        'authors' => ['FRANCISCO CÂNDIDO XAVIER'],
        'publisher' => 'FEB',
        'synopsis' => null,
        'year' => null,
        'page_count' => null,
        'cover_url' => null,
    ], [
        'title' => 'Nosso Lar (Open Library)',
        'authors' => [['name' => 'Francisco Cândido Xavier'], ['name' => 'André Luiz']],
        'publishers' => [['name' => 'FEB Publisher']],
        'publish_date' => 'Oct 15, 2019',
        'number_of_pages' => 320,
        'cover' => ['large' => 'https://covers.openlibrary.org/b/id/10545010-L.jpg'],
    ]);

    $lookup = app(LookUpBookByIsbn::class)->handle('85-7328-944-9');

    expect($lookup)
        ->title->toBe('Nosso Lar')
        ->authorNames->toBe(['Francisco Cândido Xavier', 'André Luiz'])
        ->publisherName->toBe('FEB')
        ->publishedYear->toBe(2019)
        ->pageCount->toBe(320)
        ->coverUrl->toBe('https://covers.openlibrary.org/b/id/10545010-L.jpg');
});

test('the lookup falls back to open library when brasil api does not know the isbn', function () {
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::response(['message' => 'ISBN não encontrado'], 404),
        OPEN_LIBRARY_NOSSO_LAR => Http::response(['ISBN:9788573289442' => ['title' => 'Nosso Lar', 'authors' => [['name' => 'André Luiz']]]]),
        OPEN_LIBRARY_DETAILS_NOSSO_LAR => Http::response([]),
    ]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))
        ->title->toBe('Nosso Lar')
        ->authorNames->toBe(['André Luiz']);
});

test('the lookup returns null when no catalog answers, and asks again next time', function () {
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::failedConnection(),
        OPEN_LIBRARY_NOSSO_LAR => Http::response([]),
        OPEN_LIBRARY_DETAILS_NOSSO_LAR => Http::response([], 500),
    ]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))->toBeNull();
    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))->toBeNull();

    Http::assertSentCount(6);
});

test('a found book is remembered so the catalogs are asked only once', function () {
    fakeNossoLarCatalogs(['title' => 'Nosso Lar']);

    app(LookUpBookByIsbn::class)->handle('9788573289442');
    $lookup = app(LookUpBookByIsbn::class)->handle('978-85-7328-944-2');

    expect($lookup->title)->toBe('Nosso Lar')
        ->and(Cache::get('book-lookup:v2:9788573289442'))->toBeInstanceOf(BookLookup::class);
    Http::assertSentCount(3);
});

test('an author the cbl marks as a spirit is kept once and reported as a spiritual author', function () {
    fakeNossoLarCatalogs(
        ['title' => 'Nosso Lar', 'authors' => ['André Luiz (espírito)', 'Francisco Cândido Xavier']],
        ['title' => 'Nosso Lar', 'authors' => [['name' => 'Francisco Cândido Xavier'], ['name' => 'André Luiz']]],
    );

    $lookup = app(LookUpBookByIsbn::class)->handle('9788573289442');

    expect($lookup)
        ->authorNames->toBe(['André Luiz', 'Francisco Cândido Xavier'])
        ->spiritualAuthorNames->toBe(['André Luiz']);
});

test('a name filed by surname in capitals is written the usual way and keeps the accents another catalog has', function () {
    fakeNossoLarCatalogs(
        ['title' => 'Nosso Lar', 'authors' => ['XAVIER, FRANCISCO CANDIDO']],
        ['title' => 'Nosso Lar', 'authors' => [['name' => 'Francisco Cândido Xavier']]],
    );

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442')->authorNames)->toBe(['Francisco Cândido Xavier']);
});

test('the title is written the usual way, with an article filed at the end brought to the front', function (string $brasilApiTitle, ?string $openLibraryTitle, string $expectedTitle) {
    fakeNossoLarCatalogs(['title' => $brasilApiTitle], $openLibraryTitle === null ? [] : ['title' => $openLibraryTitle]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442')->title)->toBe($expectedTitle);
})->with([
    'only in capitals' => ['MENSAGEIROS (OS)', null, 'Os Mensageiros'],
    'capitals and a usual spelling' => ['MENSAGEIROS (OS)', 'Mensageiros, Os', 'Os Mensageiros'],
    'capitals and a different title' => ['O LIVRO DOS ESPÍRITOS', 'O Livro Dos Espíritos (Em Portuguese do Brasil)', 'O Livro dos Espíritos'],
    'capitals with articles inside' => ['O EVANGELHO SEGUNDO O ESPIRITISMO', null, 'O Evangelho Segundo o Espiritismo'],
    'a word that is not an article' => ['Nosso Lar (Edição Especial)', null, 'Nosso Lar (Edição Especial)'],
]);

test('a publisher the cbl left undefined is taken from open library', function () {
    fakeNossoLarCatalogs(
        ['title' => 'Violetas na Janela', 'publisher' => 'Não definido'],
        ['title' => 'Violetas na Janela', 'publishers' => [['name' => 'Petit']]],
    );

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442')->publisherName)->toBe('Petit');
});

test('the edition number is read from the open library edition record, ignoring years', function (string $editionName, ?int $expectedNumber) {
    fakeNossoLarCatalogs(['title' => 'Nosso Lar'], ['title' => 'Nosso Lar'], ['edition_name' => $editionName]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442')->editionNumber)->toBe($expectedNumber);
})->with([
    'a numbered edition' => ['40 edition', 40],
    'a year' => ['2010 ed.', null],
]);

test('the series is read from the notes of the open library edition record, skipping edition and language', function (array|string $notes, ?string $expectedSeries) {
    fakeNossoLarCatalogs(['title' => 'Nosso Lar'], ['title' => 'Nosso Lar'], ['notes' => $notes]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442')->collectionName)->toBe($expectedSeries);
})->with([
    'a series' => [['type' => '/type/text', 'value' => 'Source title: Nosso Lar (A Vida no Mundo Espiritual) (Portuguese Edition)'], 'A Vida no Mundo Espiritual'],
    'only the language edition' => ['Source title: Nosso Lar (Portuguese Edition)', null],
    'only the language' => ['Source title: O Livro Dos Espíritos (Em Portuguese do Brasil)', null],
]);

test('an invalid isbn is not looked up', function () {
    expect(app(LookUpBookByIsbn::class)->handle('9788573289443'))->toBeNull();

    Http::assertNothingSent();
});
