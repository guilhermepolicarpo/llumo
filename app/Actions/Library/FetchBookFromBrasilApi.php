<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Data\BookLookup;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchBookFromBrasilApi
{
    use NormalizesBlankStrings;

    /**
     * Look up a book by its ISBN-13 on BrasilAPI, which mostly answers from the Brazilian Book Chamber (CBL),
     * returning null when unavailable.
     */
    public function handle(string $isbn): ?BookLookup
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(rtrim((string) config('services.brasil_api.url'), '/').'/isbn/v1/'.$isbn);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        $authors = $response->json('authors');
        $authorNames = [];
        $spiritualAuthorNames = [];

        foreach (is_array($authors) ? array_filter(array_map(fn ($name) => $this->blankToNull($name), $authors)) : [] as $name) {
            $spiritName = $this->spiritName($name);
            $authorNames[] = $spiritName ?? $name;

            if ($spiritName !== null) {
                $spiritualAuthorNames[] = $spiritName;
            }
        }

        $publisher = $this->blankToNull($response->json('publisher'));
        $year = $response->json('year');
        $pageCount = $response->json('page_count');

        return new BookLookup(
            title: $this->blankToNull($response->json('title')),
            subtitle: $this->blankToNull($response->json('subtitle')),
            authorNames: $authorNames,
            spiritualAuthorNames: $spiritualAuthorNames,
            publisherName: $publisher === null || preg_match('/^n[ãa]o definid[oa]$/iu', $publisher) === 1 ? null : $publisher,
            publishedYear: is_int($year) ? $year : null,
            pageCount: is_int($pageCount) && $pageCount > 0 ? $pageCount : null,
            synopsis: $this->blankToNull($response->json('synopsis')),
            coverUrl: $this->blankToNull($response->json('cover_url')),
        );
    }

    /**
     * Get the name of an author the CBL marked as a spirit, as in "André Luiz (espírito)", without the mark,
     * or null when the author was not marked.
     */
    private function spiritName(string $name): ?string
    {
        return preg_match('/^(.+?)\s*\(esp[íi]rito\)$/iu', $name, $matches) === 1 ? $matches[1] : null;
    }
}
