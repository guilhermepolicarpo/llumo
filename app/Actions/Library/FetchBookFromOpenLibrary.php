<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Data\BookLookup;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchBookFromOpenLibrary
{
    use NormalizesBlankStrings;

    /**
     * Look up a book by its ISBN-13 on Open Library, which often lists the spiritual author next to the
     * medium and links to a cover, returning null when unavailable.
     *
     * The edition's own record is asked for alongside, as only it tells the edition and the series; the
     * authors come from the main answer, which finds them on the work when the edition has none.
     */
    public function handle(string $isbn): ?BookLookup
    {
        $url = rtrim((string) config('services.open_library.url'), '/').'/api/books';
        $query = fn (string $command): array => ['bibkeys' => 'ISBN:'.$isbn, 'jscmd' => $command, 'format' => 'json'];

        try {
            [$dataResponse, $detailsResponse] = Http::pool(fn (Pool $pool) => [
                $pool->timeout(8)->acceptJson()->get($url, $query('data')),
                $pool->timeout(8)->acceptJson()->get($url, $query('details')),
            ]);
        } catch (Throwable) {
            return null;
        }

        $json = fn (mixed $response, string $key): mixed => $response instanceof Response && $response->successful() ? $response->json($key) : null;

        $book = $json($dataResponse, 'ISBN:'.$isbn);

        if (! is_array($book)) {
            return null;
        }

        $details = $json($detailsResponse, 'ISBN:'.$isbn.'.details');
        $details = is_array($details) ? $details : [];

        $pageCount = $book['number_of_pages'] ?? null;

        return new BookLookup(
            title: $this->blankToNull($book['title'] ?? null),
            subtitle: $this->blankToNull($book['subtitle'] ?? null),
            authorNames: $this->names($book['authors'] ?? null),
            publisherName: $this->names($book['publishers'] ?? null)[0] ?? null,
            publishedYear: preg_match('/\b(\d{4})\b/', (string) ($book['publish_date'] ?? ''), $matches) === 1 ? (int) $matches[1] : null,
            pageCount: is_int($pageCount) && $pageCount > 0 ? $pageCount : null,
            editionNumber: preg_match('/^\D*([1-9]\d{0,2})(?!\d)/', (string) ($details['edition_name'] ?? ''), $matches) === 1 ? (int) $matches[1] : null,
            collectionName: $this->names($details['series'] ?? null)[0] ?? $this->seriesInNotes($details['notes'] ?? null),
            coverUrl: $this->blankToNull($book['cover']['large'] ?? $book['cover']['medium'] ?? null),
        );
    }

    /**
     * Pick the names out of a list of Open Library entries such as authors, publishers or series, given
     * either as plain names or as records with a name.
     *
     * @return list<string>
     */
    private function names(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $entry): ?string => $this->blankToNull(is_array($entry) ? ($entry['name'] ?? null) : $entry),
            $entries,
        )));
    }

    /**
     * Find the series in the notes of a record imported from Amazon, which keeps it in parentheses after the
     * title, as in "Source title: Nosso Lar (A Vida no Mundo Espiritual) (Portuguese Edition)", skipping the
     * parentheses that only tell the edition or the language.
     */
    private function seriesInNotes(mixed $notes): ?string
    {
        $notes = is_array($notes) ? ($notes['value'] ?? null) : $notes;

        if (! is_string($notes) || preg_match('/^Source title:.*?((?:\s*\([^()]+\))+)\s*$/m', $notes, $matches) !== 1) {
            return null;
        }

        preg_match_all('/\(([^()]+)\)/', $matches[1], $parentheses);

        foreach ($parentheses[1] as $parenthesis) {
            if (preg_match('/edition|edição|portugu|brasil/iu', $parenthesis) !== 1) {
                return $this->blankToNull($parenthesis);
            }
        }

        return null;
    }
}
