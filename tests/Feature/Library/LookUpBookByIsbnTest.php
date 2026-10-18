<?php

use App\Actions\Library\LookUpBookByIsbn;
use App\Data\BookLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

const BRASIL_API_NOSSO_LAR = 'https://brasilapi.com.br/api/isbn/v1/9788573289442';
const OPEN_LIBRARY_NOSSO_LAR = 'https://openlibrary.org/api/books?bibkeys=ISBN%3A9788573289442&jscmd=data&format=json';

test('the lookup completes brasil api with the spiritual author and the cover found on open library', function () {
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::response([
            'title' => 'Nosso Lar',
            'authors' => ['FRANCISCO CÂNDIDO XAVIER'],
            'publisher' => 'FEB',
            'synopsis' => null,
            'year' => null,
            'page_count' => null,
            'cover_url' => null,
        ]),
        OPEN_LIBRARY_NOSSO_LAR => Http::response(['ISBN:9788573289442' => [
            'title' => 'Nosso Lar (Open Library)',
            'authors' => [['name' => 'Francisco Cândido Xavier'], ['name' => 'André Luiz']],
            'publishers' => [['name' => 'FEB Publisher']],
            'publish_date' => 'Oct 15, 2019',
            'number_of_pages' => 320,
            'cover' => ['large' => 'https://covers.openlibrary.org/b/id/10545010-L.jpg'],
        ]]),
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
    ]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))
        ->title->toBe('Nosso Lar')
        ->authorNames->toBe(['André Luiz']);
});

test('the lookup returns null when no catalog answers, and asks again next time', function () {
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::failedConnection(),
        OPEN_LIBRARY_NOSSO_LAR => Http::response([]),
    ]);

    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))->toBeNull();
    expect(app(LookUpBookByIsbn::class)->handle('9788573289442'))->toBeNull();

    Http::assertSentCount(4);
});

test('a found book is remembered so the catalogs are asked only once', function () {
    Http::fake([
        BRASIL_API_NOSSO_LAR => Http::response(['title' => 'Nosso Lar']),
        OPEN_LIBRARY_NOSSO_LAR => Http::response([]),
    ]);

    app(LookUpBookByIsbn::class)->handle('9788573289442');
    $lookup = app(LookUpBookByIsbn::class)->handle('978-85-7328-944-2');

    expect($lookup->title)->toBe('Nosso Lar')
        ->and(Cache::get('book-lookup:9788573289442'))->toBeInstanceOf(BookLookup::class);
    Http::assertSentCount(2);
});

test('an invalid isbn is not looked up', function () {
    expect(app(LookUpBookByIsbn::class)->handle('9788573289443'))->toBeNull();

    Http::assertNothingSent();
});
