<?php

namespace App\Providers;

use App\Services\Challenge\BugHunter\BugHunterEvaluator;
use App\Services\Challenge\CursedCode\CursedCodeEvaluator;
use App\Services\Challenge\EvaluatorRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * One registry for the process. Experiences register themselves into it
         * at boot, so it has to be the same instance the submission path later
         * resolves — a fresh instance per resolution would know about nothing.
         */
        $this->app->singleton(EvaluatorRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->passContainerConnectionVariablesToServe();
        $this->configureRateLimiting();
        $this->registerExperienceEvaluators();
    }

    /**
     * Each experience registers its evaluator here, keyed by experience slug.
     *
     * Registration lives in the provider rather than inside the registry so that
     * adding an experience never means editing the registry itself.
     */
    protected function registerExperienceEvaluators(): void
    {
        $registry = $this->app->make(EvaluatorRegistry::class);

        $registry->register('cursed-code', CursedCodeEvaluator::class);
        $registry->register('bug-hunter', BugHunterEvaluator::class);
    }

    /**
     * Every expensive or abusable operation is limited (plan §41).
     *
     * Keyed by user, not by IP: opening attempts is an authenticated action, and
     * an IP key would throttle everyone behind one NAT together while doing
     * nothing about a single account driving a script.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('attempt-start', fn (Request $request) => Limit::perMinute(
            (int) config('devlab.rate_limits.attempt_start')
        )->by($request->user()?->id ?: $request->ip()));

        /*
         * "I'm Bored" is a GET that queries every published challenge, and it is
         * open to guests — so it is limited by IP as well as by account.
         */
        RateLimiter::for('bored', fn (Request $request) => Limit::perMinute(
            (int) config('devlab.rate_limits.bored')
        )->by($request->user()?->id ?: $request->ip()));

        /*
         * Reporting is cheap to do and expensive to moderate, so it is the
         * tightest limit of the three (§41).
         */
        RateLimiter::for('report', fn (Request $request) => Limit::perMinute(
            (int) config('devlab.rate_limits.report')
        )->by($request->user()?->id ?: $request->ip()));

        // Submissions run an evaluator, so they cost more than a page view and
        // are the natural target for a brute-force search of the answer space.
        RateLimiter::for('submission', fn (Request $request) => Limit::perMinute(
            (int) config('devlab.rate_limits.submission')
        )->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Let `php artisan serve` see the connection variables the container sets.
     *
     * `serve` spawns PHP's built-in server with a whitelisted environment, so
     * anything Docker Compose puts in the container environment reaches artisan
     * CLI processes but NOT an HTTP request. In the Compose setup that split is
     * silent and confusing: migrations connect to `postgres` and succeed, while
     * every request falls back to .env's host-side 127.0.0.1:5433 / :6380 and
     * dies with "Connection refused".
     */
    protected function passContainerConnectionVariablesToServe(): void
    {
        $variables = [
            'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD',
        ];

        foreach ($variables as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
