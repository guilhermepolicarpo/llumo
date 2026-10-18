<?php

namespace App\Models;

use App\Enums\BookCopyStatus;
use Database\Factories\BookCopyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A physical copy of a book, identified on its label by a code numbered in sequence across the team's library.
 *
 * @property int $id
 * @property int $team_id
 * @property int $book_id
 * @property int $code
 * @property BookCopyStatus $status
 * @property Carbon|null $acquired_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $formatted_code
 * @property-read Team $team
 * @property-read Book $book
 * @property-read BookLoan|null $openLoan
 * @property-read Collection<int, BookLoan> $loans
 */
#[Fillable([
    'team_id',
    'book_id',
    'code',
    'status',
    'acquired_on',
    'notes',
])]
class BookCopy extends Model
{
    /** @use HasFactory<BookCopyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * How many digits a copy code is padded to on its label and on screen.
     */
    public const int CODE_DIGITS = 4;

    /**
     * Get the team this copy belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the book this is a copy of, even when it has since been deleted.
     *
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /**
     * Get every loan of this copy.
     *
     * @return HasMany<BookLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    /**
     * Get the loan of this copy that has not been returned yet, if any.
     *
     * @return HasOne<BookLoan, $this>
     */
    public function openLoan(): HasOne
    {
        return $this->hasOne(BookLoan::class)->open();
    }

    /**
     * Determine whether the copy is in the collection and not lent out, so it can be lent now.
     */
    public function isAvailable(): bool
    {
        return $this->status === BookCopyStatus::Active && $this->openLoan === null;
    }

    /**
     * Scope the query to copies in the collection that are not lent out.
     *
     * @param  Builder<BookCopy>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', BookCopyStatus::Active)->whereDoesntHave('openLoan');
    }

    /**
     * Turn a typed copy code, with or without the leading zeros of its label, into the number it is stored
     * as, or null when it is not a copy code at all.
     */
    public static function parseCode(string $code): ?int
    {
        $digits = trim($code);

        return $digits !== '' && ctype_digit($digits) && (int) $digits > 0 ? (int) $digits : null;
    }

    /**
     * Get the copy code padded with zeros, as printed on its label.
     *
     * @return Attribute<string, never>
     */
    protected function formattedCode(): Attribute
    {
        return Attribute::make(get: fn (): string => str_pad((string) $this->code, self::CODE_DIGITS, '0', STR_PAD_LEFT));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'status' => BookCopyStatus::class,
            'acquired_on' => 'date',
        ];
    }
}
