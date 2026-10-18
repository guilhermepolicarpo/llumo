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
        $year = $response->json('year');
        $pageCount = $response->json('page_count');

        return new BookLookup(
            title: $this->blankToNull($response->json('title')),
            subtitle: $this->blankToNull($response->json('subtitle')),
            authorNames: is_array($authors) ? array_values(array_filter(array_map(fn ($name) => $this->blankToNull($name), $authors))) : [],
            publisherName: $this->blankToNull($response->json('publisher')),
            publishedYear: is_int($year) ? $year : null,
            pageCount: is_int($pageCount) && $pageCount > 0 ? $pageCount : null,
            synopsis: $this->blankToNull($response->json('synopsis')),
            coverUrl: $this->blankToNull($response->json('cover_url')),
        );
    }
}
