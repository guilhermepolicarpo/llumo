<?php

namespace App\Models;

use App\Enums\BookLoanStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The loan of one copy to an assisted person. Its status is not stored: it follows from its dates.
 *
 * @property int $id
 * @property int $team_id
 * @property int $book_copy_id
 * @property int $assisted_person_id
 * @property Carbon $loaned_on
 * @property Carbon $due_on
 * @property Carbon|null $returned_on
 * @property int $renewals_count
 * @property int|null $registered_by
 * @property int|null $returned_to
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BookLoanStatus $status
 * @property-read Team $team
 * @property-read BookCopy $copy
 * @property-read AssistedPerson $assistedPerson
 * @property-read User|null $registeredBy
 * @property-read User|null $returnedTo
 */
#[Fillable([
    'team_id',
    'book_copy_id',
    'assisted_person_id',
    'loaned_on',
    'due_on',
    'returned_on',
    'renewals_count',
    'registered_by',
    'returned_to',
    'notes',
])]
class BookLoan extends Model
{
    /** @use HasFactory<BookLoanFactory> */
    use HasFactory;

    /**
     * How many days before its due date an open loan counts as due soon.
     */
    public const int DUE_SOON_DAYS = 3;

    /**
     * Get the team this loan belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the lent copy, even when it has since been deleted.
     *
     * @return BelongsTo<BookCopy, $this>
     */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id')->withTrashed();
    }

    /**
     * Get the assisted person who borrowed the copy, even when they have since been deleted.
     *
     * @return BelongsTo<AssistedPerson, $this>
     */
    public function assistedPerson(): BelongsTo
    {
        return $this->belongsTo(AssistedPerson::class)->withTrashed();
    }

    /**
     * Get the user who registered the loan.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Get the user who received the copy back.
     *
     * @return BelongsTo<User, $this>
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Scope the query to loans whose copy has not been returned yet.
     *
     * @param  Builder<BookLoan>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('returned_on');
    }

    /**
     * Scope the query to open loans whose due date has passed.
     *
     * @param  Builder<BookLoan>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->open()->where('due_on', '<', today()->toDateString());
    }

    /**
     * Scope the query to loans whose copy has been returned.
     *
     * @param  Builder<BookLoan>  $query
     */
    #[Scope]
    protected function returned(Builder $query): void
    {
        $query->whereNotNull('returned_on');
    }

    /**
     * Get the status of the loan, derived from whether it was returned and from its due date.
     *
     * @return Attribute<BookLoanStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn (): BookLoanStatus => match (true) {
            $this->returned_on !== null => BookLoanStatus::Returned,
            $this->due_on->lt(today()) => BookLoanStatus::Overdue,
            default => BookLoanStatus::Open,
        });
    }

    /**
     * Get the due date a renewal gives the loan: one more loan period, counted from the due date or from today
     * when the loan is overdue.
     */
    public function dueOnAfterRenewal(int $loanPeriodDays): CarbonInterface
    {
        return $this->due_on->max(today())->addDays($loanPeriodDays);
    }

    /**
     * Describe where the loan stands against its due date, as the loan lists show it.
     */
    public function dueHint(): string
    {
        $daysUntilDue = $this->daysUntilDue();

        return match (true) {
            $this->returned_on !== null => __('Returned on :date', ['date' => $this->returned_on->format('d/m/Y')]),
            $daysUntilDue < 0 => trans_choice('Overdue by :count day|Overdue by :count days', -$daysUntilDue),
            $daysUntilDue === 0 => __('Due today'),
            default => trans_choice('Due in :count day|Due in :count days', $daysUntilDue),
        };
    }

    /**
     * Determine whether the loan is still open and due within the next few days, but not overdue yet.
     */
    public function isDueSoon(): bool
    {
        $daysUntilDue = $this->daysUntilDue();

        return $this->returned_on === null && $daysUntilDue >= 0 && $daysUntilDue <= self::DUE_SOON_DAYS;
    }

    /**
     * Count the days left until the loan is due: zero on the due date and negative once it is overdue.
     */
    private function daysUntilDue(): int
    {
        return (int) today()->diffInDays($this->due_on, false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loaned_on' => 'date',
            'due_on' => 'date',
            'returned_on' => 'date',
            'renewals_count' => 'integer',
        ];
    }
}
