<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueTeamInvitation implements ValidationRule
{
    public function __construct(protected Team $team)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower($value);

        $isMember = $this->team->members()
            ->where('email', $email)
            ->exists();

        if ($isMember) {
            $fail(__('This user is already a member of the Spiritist Center.'));

            return;
        }

        $hasPendingInvitation = $this->team->invitations()
            ->where('email', $email)
            ->pending()
            ->exists();

        if ($hasPendingInvitation) {
            $fail(__('An invitation has already been sent to this email address.'));
        }
    }
}
