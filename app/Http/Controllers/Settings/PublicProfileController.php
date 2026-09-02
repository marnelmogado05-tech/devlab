<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profiles\CreateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Editing your own public profile.
 *
 * Separate from Settings\ProfileController, which owns the ACCOUNT — name, email
 * and deletion. This owns the public identity: handle, bio, visibility, and the
 * preferences the "I'm Bored" recommender reads.
 */
class PublicProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, CreateProfile $createProfile): RedirectResponse
    {
        // Idempotent, and covers a user who predates profiles and has not been
        // backfilled.
        $profile = $createProfile->handle($request->user());

        $profile->update([
            'username' => $request->string('username')->value(),
            'display_name' => $request->input('display_name'),
            'bio' => $request->input('bio'),
            'location' => $request->input('location'),
            'website' => $request->input('website'),
            'github_handle' => $request->input('github_handle'),
            'is_public' => $request->boolean('is_public'),
            'preferences' => array_filter([
                'difficulty' => $request->input('preferred_difficulty'),
                'technologies' => $request->technologies(),
            ], fn ($value) => $value !== null && $value !== []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
