<?php

namespace App\Http\Middleware;

use App\Models\ChallengeReport;
use App\Models\Experience;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            /*
             * The report reason list, shared from config so the form, the
             * validator and the model cannot drift apart. Cheap and static.
             */
            'reportReasons' => ChallengeReport::reasons(),
            'reportReasonsNeedingDetails' => ChallengeReport::reasonsRequiringDetails(),

            /*
             * The experiences the sidebar lists, read rather than hardcoded.
             * Publishing an experience puts it in the navigation without a
             * frontend change, and un-publishing takes it out — which is what
             * makes this list safe to render for a guest.
             *
             * Three indexed columns for a handful of rows, so it is not worth a
             * cache: an invalidation bug here would show visitors a catalogue
             * that no longer exists, which costs more than the query saves.
             */
            'navExperiences' => Experience::query()
                ->published()
                ->inCatalogueOrder()
                ->get(['slug', 'name', 'icon']),
        ];
    }
}
