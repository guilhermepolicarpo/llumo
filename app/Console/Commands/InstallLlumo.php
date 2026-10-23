<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamProfileRules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('llumo:install')]
#[Description('Create the first user and the Spiritist Center of a new installation')]
class InstallLlumo extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(CreateTeam $createTeam): int
    {
        if (Team::withTrashed()->exists()) {
            $this->components->error(__('This installation already has a Spiritist Center.'));

            return self::FAILURE;
        }

        $input = [
            'team' => text(__('Spiritist Center name'), required: true),
            'name' => text(__('Your name'), required: true),
            'email' => text(__('Email address'), required: true),
            'password' => password(__('Password'), required: true),
            'password_confirmation' => password(__('Confirm password'), required: true),
        ];

        $validator = Validator::make($input, [
            'team' => TeamProfileRules::name(),
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $team = DB::transaction(function () use ($input, $createTeam) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->markEmailAsVerified();

            return $createTeam->handle($user, $input['team']);
        });

        $this->components->info(__('Spiritist Center ":name" created. Log in with :email.', ['name' => $team->name, 'email' => $input['email']]));

        return self::SUCCESS;
    }
}
