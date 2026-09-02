<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->passContainerConnectionVariablesToServe();
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
