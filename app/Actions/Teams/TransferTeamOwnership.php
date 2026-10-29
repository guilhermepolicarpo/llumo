<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferTeamOwnership
{
    /**
     * Make the given member the team's owner, turning the current owner into an admin.
     */
    public function handle(Team $team, User $currentOwner, User $newOwner): void
    {
        DB::transaction(function () use ($team, $currentOwner, $newOwner) {
            $team->memberships()
                ->where('user_id', $newOwner->id)
                ->update(['role' => TeamRole::Owner]);

            $team->memberships()
                ->where('user_id', $currentOwner->id)
                ->update(['role' => TeamRole::Admin]);
        });
    }
}
