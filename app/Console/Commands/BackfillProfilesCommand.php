<?php

namespace App\Console\Commands;

use App\Actions\Profiles\CreateProfile;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Give a public identity to users who registered before profiles existed.
 *
 * Registration creates one now, but every account made before this slice has
 * none — and `/profile/{username}` cannot resolve for a user without one.
 * Idempotent: a user who already has a profile is skipped.
 */
class BackfillProfilesCommand extends Command
{
    protected $signature = 'devlab:backfill-profiles';

    protected $description = 'Create a profile for any user that does not have one';

    public function handle(CreateProfile $createProfile): int
    {
        $created = 0;

        User::query()->doesntHave('profile')->chunkById(200, function ($users) use ($createProfile, &$created): void {
            foreach ($users as $user) {
                $createProfile->handle($user);
                $created++;
            }
        });

        $this->info("Created {$created} profile(s).");

        return self::SUCCESS;
    }
}
