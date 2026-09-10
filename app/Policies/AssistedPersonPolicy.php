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
        return $this->belongsToPersonsTeam($user, $assistedPerson);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssistedPerson $assistedPerson): bool
    {
        return $this->belongsToPersonsTeam($user, $assistedPerson);
    }

    /**
     * Determine whether the user belongs to the assisted person's team.
     */
    private function belongsToPersonsTeam(User $user, AssistedPerson $assistedPerson): bool
    {
        return $user->belongsToTeam($assistedPerson->team);
    }
}
