<?php

namespace App\Actions\Fortify;

use App\Actions\Profiles\CreateProfile;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly CreateProfile $createProfile) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        /*
         * Every user gets a public identity immediately, so /profile/{username}
         * always resolves and the leaderboard has a real handle to show rather
         * than falling back to the account name. They can change it in settings.
         */
        $this->createProfile->handle($user);

        return $user;
    }
}
