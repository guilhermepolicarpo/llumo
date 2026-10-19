<?php

namespace App\Actions\Library;

use App\Concerns\ComparesNames;
use App\Data\BookLookup;
use App\Rules\Isbn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LookUpBookByIsbn
{
    use ComparesNames;

    /**
     * Words kept in lower case when a name that came in capitals is rewritten, as in "Francisco Cândido Xavier".
     *
     * @var list<string>
     */
    private const array LOWERCASE_NAME_PARTICLES = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du', 'van', 'von'];

    /**
     * Articles catalogs move to the end of a title to file it, as in "MENSAGEIROS (OS)" or "Mensageiros, Os".
     *
     * @var list<string>
     */
    private const array TITLE_ARTICLES = ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas'];

    /**
     * Words kept in lower case inside a title that came in capitals, as in "O Livro dos Espíritos".
     *
     * @var list<string>
     */
    private const array TITLE_LOWERCASE_WORDS = [
        ...self::TITLE_ARTICLES,
        'de', 'da', 'do', 'das', 'dos', 'e', 'ou', 'no', 'na', 'nos', 'nas', 'em', 'ao', 'aos', 'à', 'às',
        'com', 'por', 'para', 'pelo', 'pela', 'pelos', 'pelas',
    ];

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

        $cacheKey = 'book-lookup:v2:'.$isbn;

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

        $authorNames = $this->uniqueNames(collect($lookups)->flatMap(fn (BookLookup $lookup) => $lookup->authorNames)->all());
        $spiritKeys = collect($lookups)
            ->flatMap(fn (BookLookup $lookup) => $lookup->spiritualAuthorNames)
            ->map(fn (string $name) => $this->nameKey($this->authorName($name)))
            ->all();

        $publisherName = $first('publisherName');
        $collectionName = $first('collectionName');

        return new BookLookup(
            title: $this->displayTitle(collect($lookups)->pluck('title')->all()),
            subtitle: $first('subtitle'),
            authorNames: $authorNames,
            spiritualAuthorNames: array_values(array_filter($authorNames, fn (string $name) => in_array($this->nameKey($name), $spiritKeys, true))),
            publisherName: $publisherName === null ? null : $this->displayName($publisherName),
            publishedYear: $first('publishedYear'),
            pageCount: $first('pageCount'),
            editionNumber: $first('editionNumber'),
            collectionName: $collectionName === null ? null : Str::squish($collectionName),
            synopsis: $first('synopsis'),
            coverUrl: $first('coverUrl'),
        );
    }

    /**
     * Keep each author once, in the order first found, spelled with its accents when a catalog left them out.
     *
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private function uniqueNames(array $names): array
    {
        $unique = [];

        foreach (array_map(fn (string $name) => $this->authorName($name), $names) as $name) {
            $key = $this->nameKey($name);

            if (! isset($unique[$key]) || ($unique[$key] === Str::ascii($unique[$key]) && $name !== Str::ascii($name))) {
                $unique[$key] = $name;
            }
        }

        return array_values($unique);
    }

    /**
     * Choose the first catalog's title, with any article filed at the end brought back to the front. When it came
     * entirely in capitals, another catalog's spelling of the same title is preferred, or else it is rewritten.
     *
     * @param  list<string>  $titles
     */
    private function displayTitle(array $titles): string
    {
        $titles = array_map(fn (string $title) => $this->titleWithArticleInFront(Str::squish($title)), $titles);

        if ($titles[0] !== Str::upper($titles[0])) {
            return $titles[0];
        }

        $titleKey = fn (string $title): string => preg_replace('/[^a-z0-9]/', '', $this->nameKey($title));

        return collect($titles)->first(fn (string $title) => $title !== Str::upper($title) && $titleKey($title) === $titleKey($titles[0]))
            ?? $this->displayName($titles[0], self::TITLE_LOWERCASE_WORDS);
    }

    /**
     * Bring an article a catalog filed at the end of a title back to the front, as in "MENSAGEIROS (OS)" or
     * "Mensageiros, Os".
     */
    private function titleWithArticleInFront(string $title): string
    {
        if (preg_match('/^(.+?)(?|\s*\((\pL+)\)|,\s*(\pL+))$/u', $title, $matches) !== 1) {
            return $title;
        }

        $article = $matches[2];

        if (! in_array(Str::lower($article), self::TITLE_ARTICLES, true)) {
            return $title;
        }

        $article = $article === Str::upper($article) && $matches[1] === Str::upper($matches[1]) ? Str::upper($article) : Str::ucfirst(Str::lower($article));

        return $article.' '.$matches[1];
    }

    /**
     * Write an author's name the usual way, putting a name filed by surname, as in "XAVIER, FRANCISCO CANDIDO",
     * back in its usual order.
     */
    private function authorName(string $name): string
    {
        if (substr_count($name, ',') === 1) {
            [$surname, $givenNames] = array_map(trim(...), explode(',', $name));

            $name = $givenNames === '' ? $surname : $givenNames.' '.$surname;
        }

        return $this->displayName($name);
    }

    /**
     * Rewrite a name that came entirely in capitals, as the CBL often sends them, the way it is usually written,
     * keeping the given words in lower case after the first one.
     *
     * @param  list<string>  $lowercaseWords
     */
    private function displayName(string $name, array $lowercaseWords = self::LOWERCASE_NAME_PARTICLES): string
    {
        $name = Str::squish($name);

        if ($name !== Str::upper($name) || mb_strlen($name) <= 4) {
            return $name;
        }

        return collect(explode(' ', Str::lower($name)))
            ->map(fn (string $word, int $index) => $index > 0 && in_array($word, $lowercaseWords, true) ? $word : Str::ucfirst($word))
            ->implode(' ');
    }
}
