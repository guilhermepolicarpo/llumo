<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     *
     * Without multiple centers enabled, only the first center of the
     * installation can be created.
     */
    public function create(User $user): bool
    {
        return config('llumo.allow_center_creation') || ! Team::withTrashed()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team) && ! $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update the given member's role in the team.
     */
    public function updateMember(User $user, Team $team, User $member): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateMember)
            && $this->canManageMember($user, $team, $member);
    }

    /**
     * Determine whether the user can remove the given member from the team.
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::RemoveMember)
            && $this->canManageMember($user, $team, $member);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can transfer the team's ownership to the given member.
     */
    public function transferOwnership(User $user, Team $team, User $member): bool
    {
        return $user->ownsTeam($team) && ! $user->is($member) && $member->belongsToTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * An installation that serves a single center cannot delete it: only the
     * first center can be created there, so it would be left without one.
     */
    public function delete(User $user, Team $team): bool
    {
        return config('llumo.allow_center_creation') && $user->hasTeamPermission($team, TeamPermission::DeleteTeam);
    }

    /**
     * Determine whether the given member can be managed by the user: nobody manages the owner or themselves.
     */
    private function canManageMember(User $user, Team $team, User $member): bool
    {
        return ! $user->is($member) && ! $member->ownsTeam($team);
    }
}
