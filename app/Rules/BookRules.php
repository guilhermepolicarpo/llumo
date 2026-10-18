<?php

namespace App\Rules;

use App\Enums\LibraryCatalog;
use App\Models\Book;
use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BookRules
{
    /**
     * The most copies that can be registered at once while creating a book.
     */
    public const int MAX_COPIES_AT_ONCE = 50;

    /**
     * The largest width or height of a cover, in pixels, uploaded or downloaded.
     */
    public const int COVER_MAX_DIMENSION = 2000;

    /**
     * Get the validation rules used to validate a book of the given team, ignoring the book being edited.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team, ?Book $ignore = null): array
    {
        return [
            'isbn' => static::isbn($team, $ignore),
            'title' => static::title(),
            'subtitle' => static::subtitle(),
            'edition' => static::edition(),
            'publishedYear' => static::publishedYear(),
            'pageCount' => static::pageCount(),
            'synopsis' => static::synopsis(),
            'publisherId' => static::catalogEntry($team, LibraryCatalog::Publisher),
            'categoryId' => static::catalogEntry($team, LibraryCatalog::Category),
            'collectionId' => static::catalogEntry($team, LibraryCatalog::Collection),
            'collectionVolume' => static::collectionVolume(),
            'incarnateAuthorIds' => ['array'],
            'incarnateAuthorIds.*' => CatalogRules::entryId($team, LibraryCatalog::Author),
            'spiritualAuthorIds' => ['array'],
            'spiritualAuthorIds.*' => CatalogRules::entryId($team, LibraryCatalog::Author),
            'cover' => static::cover(),
        ];
    }

    /**
     * Get the validation rules used to validate a book's ISBN, which must be valid and not used by another of the team's books.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function isbn(Team $team, ?Book $ignore = null): array
    {
        return [
            'nullable',
            'string',
            new Isbn,
            function (string $attribute, mixed $value, Closure $fail) use ($team, $ignore): void {
                $isbn = Isbn::toIsbn13(is_string($value) ? $value : null);

                $taken = $isbn !== null && $team->books()
                    ->where('isbn', $isbn)
                    ->when($ignore, fn ($query, Book $book) => $query->whereKeyNot($book->getKey()))
                    ->exists();

                if ($taken) {
                    $fail(__('Another book in the library already has this ISBN.'));
                }
            },
        ];
    }

    /**
     * Get the validation rules used to validate a book's title.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function title(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a book's subtitle.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function subtitle(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a book's edition.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function edition(): array
    {
        return ['nullable', 'string', 'max:50'];
    }

    /**
     * Get the validation rules used to validate the year a book was published.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function publishedYear(): array
    {
        return ['nullable', 'integer', 'min:1000', 'max:'.(today()->year + 1)];
    }

    /**
     * Get the validation rules used to validate a book's number of pages.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function pageCount(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:65535'];
    }

    /**
     * Get the validation rules used to validate a book's synopsis.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function synopsis(): array
    {
        return ['nullable', 'string', 'max:5000'];
    }

    /**
     * Get the validation rules used to validate a book's volume number within its collection.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function collectionVolume(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:65535'];
    }

    /**
     * Get the validation rules used to validate an optional reference to one of the team's library catalog entries.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function catalogEntry(Team $team, LibraryCatalog $catalog): array
    {
        return ['nullable', ...CatalogRules::entryId($team, $catalog)];
    }

    /**
     * Get the validation rules used to validate an uploaded book cover.
     *
     * SVG is intentionally excluded: covers are served from the application's own
     * origin, where a crafted SVG would run as same-origin script.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function cover(): array
    {
        return ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width='.static::COVER_MAX_DIMENSION.',max_height='.static::COVER_MAX_DIMENSION];
    }

    /**
     * Get the validation rules used to validate how many copies of a new book are registered.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function copiesCount(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.static::MAX_COPIES_AT_ONCE];
    }
}
