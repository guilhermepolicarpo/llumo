<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBookAttributes;
use App\Models\Book;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateBook
{
    use NormalizesBookAttributes;

    public function __construct(private AddBookCopies $addBookCopies)
    {
        //
    }

    /**
     * Register a book in the team's library with its authors and its first copies.
     *
     * The cover, if any, is already stored on the public disk; it is deleted again when the book cannot be saved.
     *
     * @param  array{title: string, subtitle?: ?string, isbn?: ?string, edition?: ?string, published_year?: int|string|null,
     *              page_count?: int|string|null, synopsis?: ?string, publisher_id?: int|string|null, book_category_id?: int|string|null,
     *              book_collection_id?: int|string|null, collection_volume?: int|string|null}  $attributes
     * @param  array{incarnate?: array<int, int|string>, spiritual?: array<int, int|string>}  $authorIds
     */
    public function handle(Team $team, array $attributes, array $authorIds, int $copiesCount = 1, ?string $coverPath = null): Book
    {
        try {
            return DB::transaction(function () use ($team, $attributes, $authorIds, $copiesCount, $coverPath) {
                $book = $team->books()->create([
                    ...$this->attributesToPersist($attributes),
                    'cover_path' => $coverPath,
                ]);

                $this->syncAuthors($book, $authorIds);

                $this->addBookCopies->handle($book, $copiesCount);

                return $book;
            });
        } catch (Throwable $exception) {
            $this->deleteCover($coverPath);

            throw $exception;
        }
    }
}
