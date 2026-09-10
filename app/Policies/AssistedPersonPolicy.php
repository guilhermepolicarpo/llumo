<?php

namespace App\Policies;

use App\Models\AssistedPerson;
use App\Models\Team;
use App\Models\User;

class AssistedPersonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssistedPerson $assistedPerson): bool
    {
        return $user->belongsToTeam($assistedPerson->team);
    }
}
