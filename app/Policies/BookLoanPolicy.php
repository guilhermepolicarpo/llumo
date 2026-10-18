<?php

namespace App\Policies;

use App\Models\BookLoan;
use App\Models\Team;
use App\Models\User;

/**
 * Any member may lend books, renew loans and receive copies back.
 */
class BookLoanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can lend a copy.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can extend the loan's due date.
     */
    public function renew(User $user, BookLoan $bookLoan): bool
    {
        return $bookLoan->returned_on === null && $user->belongsToTeam($bookLoan->team);
    }

    /**
     * Determine whether the user can register the copy as returned.
     */
    public function return(User $user, BookLoan $bookLoan): bool
    {
        return $bookLoan->returned_on === null && $user->belongsToTeam($bookLoan->team);
    }
}
