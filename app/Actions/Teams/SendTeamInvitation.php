<?php

namespace App\Actions\Teams;

use App\Models\TeamInvitation;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

class SendTeamInvitation
{
    /**
     * Give the invitation a fresh three-day expiration and email it to the invited address.
     */
    public function handle(TeamInvitation $invitation): void
    {
        $invitation->update(['expires_at' => now()->addDays(3)]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));
    }
}
