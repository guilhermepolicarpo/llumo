<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private AcceptTeamInvitation $acceptTeamInvitation)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * A user registering through an invitation joins the inviting center right away.
     * Without one, the user is created without a center and picks one during onboarding.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['nullable', 'string'],
        ])->validate();

        $invitation = $this->invitation($input);

        return DB::transaction(function () use ($input, $invitation) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            if ($invitation) {
                $this->acceptTeamInvitation->handle($user, $invitation);
            }

            return $user;
        });
    }

    /**
     * Get the pending invitation the user is registering through, ensuring registration is allowed.
     *
     * @param  array<string, string>  $input
     */
    private function invitation(array $input): ?TeamInvitation
    {
        if (empty($input['invitation'])) {
            if (! User::canRegisterWithoutInvitation()) {
                throw ValidationException::withMessages([
                    'email' => [__('Registration is by invitation only. Ask an administrator of your Spiritist Center for an invitation.')],
                ]);
            }

            return null;
        }

        $invitation = TeamInvitation::query()
            ->where('code', $input['invitation'])
            ->pending()
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'email' => [__('This invitation is no longer valid.')],
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower($input['email'])) {
            throw ValidationException::withMessages([
                'email' => [__('This invitation was sent to a different email address.')],
            ]);
        }

        return $invitation;
    }
}
