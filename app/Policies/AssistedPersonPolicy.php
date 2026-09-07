<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\AssistedPerson;
use App\Models\Team;
use App\Models\User;

class AssistedPersonPolicy
{
    /**
     * Determine whether the user can view any assisted people of the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewAssistedPerson);
    }

    /**
     * Determine whether the user can view the assisted person.
     */
    public function view(User $user, AssistedPerson $assistedPerson): bool
    {
        return $user->hasTeamPermission($assistedPerson->team, TeamPermission::ViewAssistedPerson);
    }

    /**
     * Determine whether the user can create assisted people for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateAssistedPerson);
    }

    /**
     * Determine whether the user can update the assisted person.
     */
    public function update(User $user, AssistedPerson $assistedPerson): bool
    {
        return $user->hasTeamPermission($assistedPerson->team, TeamPermission::UpdateAssistedPerson);
    }

    /**
     * Determine whether the user can delete assisted people of the team.
     */
    public function deleteAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::DeleteAssistedPerson);
    }

    /**
     * Determine whether the user can delete the assisted person.
     */
    public function delete(User $user, AssistedPerson $assistedPerson): bool
    {
        return $user->hasTeamPermission($assistedPerson->team, TeamPermission::DeleteAssistedPerson);
    }
}
