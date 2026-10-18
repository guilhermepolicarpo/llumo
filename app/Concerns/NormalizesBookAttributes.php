<?php

namespace App\Concerns;

use App\Enums\AuthorRole;
use App\Models\Book;
use App\Rules\Isbn;
use Illuminate\Support\Facades\Storage;

trait NormalizesBookAttributes
{
    use NormalizesBlankStrings;

    /**
     * Build the book attribute list ready for persistence.
     *
     * @param  array{title: string, subtitle?: ?string, isbn?: ?string, edition?: ?string, published_year?: int|string|null,
     *              page_count?: int|string|null, synopsis?: ?string, publisher_id?: int|string|null, book_category_id?: int|string|null,
     *              book_collection_id?: int|string|null, collection_volume?: int|string|null}  $attributes
     * @return array<string, mixed>
     */
    protected function attributesToPersist(array $attributes): array
    {
        $collectionId = $this->blankToNullInteger($attributes['book_collection_id'] ?? null);

        return [
            'title' => trim($attributes['title']),
            'subtitle' => $this->blankToNull($attributes['subtitle'] ?? null),
            'isbn' => Isbn::toIsbn13($attributes['isbn'] ?? null),
            'edition' => $this->blankToNull($attributes['edition'] ?? null),
            'published_year' => $this->blankToNullInteger($attributes['published_year'] ?? null),
            'page_count' => $this->blankToNullInteger($attributes['page_count'] ?? null),
            'synopsis' => $this->blankToNull($attributes['synopsis'] ?? null),
            'publisher_id' => $this->blankToNullInteger($attributes['publisher_id'] ?? null),
            'book_category_id' => $this->blankToNullInteger($attributes['book_category_id'] ?? null),
            'book_collection_id' => $collectionId,
            'collection_volume' => $collectionId === null ? null : $this->blankToNullInteger($attributes['collection_volume'] ?? null),
        ];
    }

    /**
     * Replace the book's authors, keeping the order they were given in within each role. An author has one
     * role per book, the first one given.
     *
     * @param  array{incarnate?: array<int, int|string>, spiritual?: array<int, int|string>}  $authorIds
     */
    protected function syncAuthors(Book $book, array $authorIds): void
    {
        $authorships = [];

        foreach ([AuthorRole::Incarnate, AuthorRole::Spiritual] as $role) {
            foreach ($authorIds[$role->value] ?? [] as $authorId) {
                $authorships[(int) $authorId] ??= ['role' => $role->value, 'position' => count($authorships)];
            }
        }

        $book->authors()->sync($authorships);
    }

    /**
     * Delete a stored cover from the public disk.
     */
    protected function deleteCover(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
