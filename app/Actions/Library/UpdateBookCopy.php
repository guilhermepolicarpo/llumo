<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Enums\BookCopyStatus;
use App\Models\BookCopy;
use Illuminate\Validation\ValidationException;

class UpdateBookCopy
{
    use NormalizesBlankStrings;

    /**
     * Update the copy's code, status, acquisition date and notes.
     *
     * A lent copy stays active: it must be returned before it can be marked as lost or withdrawn.
     *
     * @param  array{code: int|string, status: string, acquired_on?: ?string, notes?: ?string}  $attributes
     */
    public function handle(BookCopy $copy, array $attributes): BookCopy
    {
        $status = BookCopyStatus::from($attributes['status']);

        if ($status !== BookCopyStatus::Active && $copy->openLoan()->exists()) {
            throw ValidationException::withMessages([
                'copyStatus' => __('This copy is lent out. Register its return before changing its status.'),
            ]);
        }

        $copy->update([
            'code' => (int) $attributes['code'],
            'status' => $status,
            'acquired_on' => $this->blankToNull($attributes['acquired_on'] ?? null),
            'notes' => $this->blankToNull($attributes['notes'] ?? null),
        ]);

        return $copy;
    }
}
