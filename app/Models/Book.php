<?php

namespace App\Models;

use App\Enums\AuthorRole;
use App\Enums\BookCopyStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $publisher_id
 * @property int|null $book_category_id
 * @property int|null $book_collection_id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $isbn
 * @property int|null $collection_volume
 * @property string|null $edition
 * @property int|null $published_year
 * @property int|null $page_count
 * @property string|null $synopsis
 * @property string|null $cover_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $cover_url
 * @property-read string|null $formatted_isbn
 * @property-read Team $team
 * @property-read Publisher|null $publisher
 * @property-read BookCategory|null $category
 * @property-read BookCollection|null $collection
 * @property-read Collection<int, Author> $authors
 * @property-read Collection<int, BookCopy> $copies
 * @property-read Collection<int, BookLoan> $loans
 */
#[Fillable([
    'team_id',
    'publisher_id',
    'book_category_id',
    'book_collection_id',
    'title',
    'subtitle',
    'isbn',
    'collection_volume',
    'edition',
    'published_year',
    'page_count',
    'synopsis',
    'cover_path',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team this book belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the book's publisher, even when it has since been deleted.
     *
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class)->withTrashed();
    }

    /**
     * Get the book's category, even when it has since been deleted.
     *
     * @return BelongsTo<BookCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'book_category_id')->withTrashed();
    }

    /**
     * Get the collection the book belongs to, even when it has since been deleted.
     *
     * @return BelongsTo<BookCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookCollection::class, 'book_collection_id')->withTrashed();
    }

    /**
     * Get every author of the book in the order they were given, with their role on the pivot.
     *
     * @return BelongsToMany<Author, $this, Pivot, 'authorship'>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->as('authorship')
            ->withPivot('role', 'position')
            ->withTrashed()
            ->orderByPivot('position');
    }

    /**
     * Get the book's authors in the given role, from the loaded authors: the incarnate authors wrote the book
     * or psychographed it, the spiritual authors dictated it.
     *
     * @return Collection<int, Author>
     */
    public function authorsWithRole(AuthorRole $role): Collection
    {
        return $this->authors->where('authorship.role', $role->value)->values();
    }

    /**
     * Get the physical copies of the book.
     *
     * @return HasMany<BookCopy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    /**
     * Get the loans of any of the book's copies.
     *
     * @return HasManyThrough<BookLoan, BookCopy, $this>
     */
    public function loans(): HasManyThrough
    {
        return $this->hasManyThrough(BookLoan::class, BookCopy::class)->withTrashedParents();
    }

    /**
     * Scope the query to books with at least one copy that is in the collection and not lent out.
     *
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->whereHas('copies', fn (Builder $copies) => $copies->available());
    }

    /**
     * Load the number of copies in the collection and how many of them can be lent right now.
     *
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function withCopyCounts(Builder $query): void
    {
        $query->withCount([
            'copies as active_copies_count' => fn (Builder $copies) => $copies->where('status', BookCopyStatus::Active),
            'copies as available_copies_count' => fn (Builder $copies) => $copies->available(),
        ]);
    }

    /**
     * Get the publicly accessible URL for the book's cover.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->cover_path
            ? Storage::disk('public')->url($this->cover_path)
            : null);
    }

    /**
     * Get the book's ISBN-13 with its prefix set apart for display, e.g. 978-8573289442.
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedIsbn(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->isbn === null
            ? null
            : substr($this->isbn, 0, 3).'-'.substr($this->isbn, 3));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_volume' => 'integer',
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }
}
