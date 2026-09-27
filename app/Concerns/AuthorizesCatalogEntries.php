<?php

namespace App\Concerns;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization shared by the policies of a team's catalogs: any member may view them and create
 * entries inline while attending, but only members allowed to manage catalogs may edit or delete them.
 */
trait AuthorizesCatalogEntries
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
     * Determine whether the user can manage the team's catalog.
     */
    public function manage(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageCatalogs);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $entry): bool
    {
        return $this->manage($user, $entry->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $entry): bool
    {
        return $this->manage($user, $entry->team);
    }
}
