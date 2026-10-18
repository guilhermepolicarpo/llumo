<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBookAttributes;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateBook
{
    use NormalizesBookAttributes;

    /**
     * Update the book's details and authors, replacing or removing its cover.
     *
     * A new cover, if any, is already stored on the public disk: it is deleted again when the book cannot be
     * saved, and the previous cover is deleted once it has been replaced or removed.
     *
     * @param  array{title: string, subtitle?: ?string, isbn?: ?string, edition?: ?string, published_year?: int|string|null,
     *              page_count?: int|string|null, synopsis?: ?string, publisher_id?: int|string|null, book_category_id?: int|string|null,
     *              book_collection_id?: int|string|null, collection_volume?: int|string|null}  $attributes
     * @param  array{incarnate?: array<int, int|string>, spiritual?: array<int, int|string>}  $authorIds
     */
    public function handle(Book $book, array $attributes, array $authorIds, ?string $newCoverPath = null, bool $removeCover = false): Book
    {
        $previousCoverPath = $book->cover_path;
        $replacesCover = $newCoverPath !== null || $removeCover;

        try {
            $book = DB::transaction(function () use ($book, $attributes, $authorIds, $newCoverPath, $replacesCover) {
                $persisted = $this->attributesToPersist($attributes);

                if ($replacesCover) {
                    $persisted['cover_path'] = $newCoverPath;
                }

                $book->update($persisted);

                $this->syncAuthors($book, $authorIds);

                return $book;
            });
        } catch (Throwable $exception) {
            $this->deleteCover($newCoverPath);

            throw $exception;
        }

        if ($replacesCover) {
            $this->deleteCover($previousCoverPath);
        }

        return $book;
    }
}
