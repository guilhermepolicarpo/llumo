<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Data\BookLookup;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchBookFromOpenLibrary
{
    use NormalizesBlankStrings;

    /**
     * Look up a book by its ISBN-13 on Open Library, which often lists the spiritual author next to the
     * medium and links to a cover, returning null when unavailable.
     */
    public function handle(string $isbn): ?BookLookup
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(rtrim((string) config('services.open_library.url'), '/').'/api/books', [
                    'bibkeys' => 'ISBN:'.$isbn,
                    'jscmd' => 'data',
                    'format' => 'json',
                ]);
        } catch (Throwable) {
            return null;
        }

        $book = $response->successful() ? $response->json('ISBN:'.$isbn) : null;

        if (! is_array($book)) {
            return null;
        }

        $pageCount = $book['number_of_pages'] ?? null;

        return new BookLookup(
            title: $this->blankToNull($book['title'] ?? null),
            subtitle: $this->blankToNull($book['subtitle'] ?? null),
            authorNames: $this->names($book['authors'] ?? null),
            publisherName: $this->names($book['publishers'] ?? null)[0] ?? null,
            publishedYear: preg_match('/\b(\d{4})\b/', (string) ($book['publish_date'] ?? ''), $matches) === 1 ? (int) $matches[1] : null,
            pageCount: is_int($pageCount) && $pageCount > 0 ? $pageCount : null,
            coverUrl: $this->blankToNull($book['cover']['large'] ?? $book['cover']['medium'] ?? null),
        );
    }

    /**
     * Pick the names out of a list of Open Library entries such as authors or publishers.
     *
     * @return list<string>
     */
    private function names(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $entry): ?string => is_array($entry) ? $this->blankToNull($entry['name'] ?? null) : null,
            $entries,
        )));
    }
}
