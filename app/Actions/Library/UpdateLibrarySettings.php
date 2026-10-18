<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Team;

class UpdateLibrarySettings
{
    use NormalizesBlankStrings;

    /**
     * Update how long the team lends books for, how often a loan may be renewed and how many books one
     * assisted person may have at once. A blank limit means there is none.
     *
     * @param  array{loan_period_days: int|string, loan_max_renewals?: int|string|null, loan_max_active_per_person?: int|string|null}  $attributes
     */
    public function handle(Team $team, array $attributes): Team
    {
        $team->update([
            'loan_period_days' => (int) $attributes['loan_period_days'],
            'loan_max_renewals' => $this->blankToNullInteger($attributes['loan_max_renewals'] ?? null),
            'loan_max_active_per_person' => $this->blankToNullInteger($attributes['loan_max_active_per_person'] ?? null),
        ]);

        return $team;
    }
}
