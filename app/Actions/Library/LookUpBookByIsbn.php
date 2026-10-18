<?php

namespace App\Actions\Library;

use App\Data\BookLookup;
use App\Rules\Isbn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LookUpBookByIsbn
{
    /**
     * Words kept in lower case when a name that came in capitals is rewritten, as in "Francisco Cândido Xavier".
     *
     * @var list<string>
     */
    private const array LOWERCASE_NAME_PARTICLES = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du', 'van', 'von'];

    public function __construct(
        private FetchBookFromBrasilApi $fetchBookFromBrasilApi,
        private FetchBookFromOpenLibrary $fetchBookFromOpenLibrary,
    ) {}

    /**
     * Look up a book by its ISBN on the free public catalogs, returning null when none of them knows it.
     *
     * BrasilAPI is asked first, as it covers Brazilian publishers best; Open Library fills in whatever it left
     * out, such as the spiritual author or the cover. A book that was found is remembered for a week.
     */
    public function handle(string $isbn): ?BookLookup
    {
        $isbn = Isbn::toIsbn13($isbn);

        if ($isbn === null) {
            return null;
        }

        $cacheKey = 'book-lookup:'.$isbn;

        if (($cached = Cache::get($cacheKey)) instanceof BookLookup) {
            return $cached;
        }

        $lookup = $this->merge(array_filter([
            $this->fetchBookFromBrasilApi->handle($isbn),
            $this->fetchBookFromOpenLibrary->handle($isbn),
        ]));

        if ($lookup !== null) {
            Cache::put($cacheKey, $lookup, now()->addWeek());
        }

        return $lookup;
    }

    /**
     * Combine what each catalog found, the first one to know a field winning, and every author kept once.
     *
     * @param  array<int, BookLookup>  $lookups
     */
    private function merge(array $lookups): ?BookLookup
    {
        $lookups = array_values(array_filter($lookups, fn (BookLookup $lookup) => $lookup->title !== null));

        if ($lookups === []) {
            return null;
        }

        $first = fn (string $field): mixed => collect($lookups)->pluck($field)->first(fn ($value) => $value !== null);

        $authorNames = array_values(collect($lookups)
            ->flatMap(fn (BookLookup $lookup) => $lookup->authorNames)
            ->map(fn (string $name) => $this->displayName($name))
            ->unique(fn (string $name) => Str::lower(Str::ascii($name)))
            ->all());

        $publisherName = $first('publisherName');

        return new BookLookup(
            title: $first('title'),
            subtitle: $first('subtitle'),
            authorNames: $authorNames,
            publisherName: $publisherName === null ? null : $this->displayName($publisherName),
            publishedYear: $first('publishedYear'),
            pageCount: $first('pageCount'),
            synopsis: $first('synopsis'),
            coverUrl: $first('coverUrl'),
        );
    }

    /**
     * Rewrite a name that came entirely in capitals, as the CBL often sends them, the way it is usually written.
     */
    private function displayName(string $name): string
    {
        $name = Str::squish($name);

        if ($name !== Str::upper($name) || mb_strlen($name) <= 4) {
            return $name;
        }

        return collect(explode(' ', Str::lower($name)))
            ->map(fn (string $word, int $index) => $index > 0 && in_array($word, self::LOWERCASE_NAME_PARTICLES, true) ? $word : Str::ucfirst($word))
            ->implode(' ');
    }
}
