<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * Invited people who already have an account are sent to log in, everyone else to register.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        [$instruction, $actionText, $actionRoute] = $this->invitedEmailHasAccount()
            ? [__('Log in and visit your dashboard to accept or decline this invitation.'), __('Log in'), 'login']
            : [__('Create your account to join the Spiritist Center.'), __('Register'), 'register'];

        return (new MailMessage)
            ->subject(__("You've been invited to join :teamName", ['teamName' => $team->name]))
            ->line(__(':inviterName has invited you to join the :teamName Spiritist Center.', [
                'inviterName' => $inviter->name,
                'teamName' => $team->name,
            ]))
            ->line($instruction)
            ->action($actionText, route($actionRoute, ['invitation' => $this->invitation->code]));
    }

    /**
     * Determine whether a user is already registered with the invited email address.
     */
    private function invitedEmailHasAccount(): bool
    {
        return User::query()
            ->where('email', $this->invitation->email)
            ->exists();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
