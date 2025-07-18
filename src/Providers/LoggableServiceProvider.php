<?php

namespace Devdabour\LaravelLoggable\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\CauserResolver;
use Illuminate\Database\Eloquent\Model;

class LoggableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/loggable.php', 'loggable'
        );

        // Override the CauserResolver to handle null users gracefully
        $this->app->extend(CauserResolver::class, function ($resolver, $app) {
            return new class($app['config'], $app['auth']) extends CauserResolver {
                /**
                 * Override the resolve method to handle null users gracefully
                 */
                public function resolve(Model | int | string | null $subject = null): ?Model
                {
                    try {
                        // If we're in a test environment or no user is authenticated, return null
                        if (app()->environment('testing') || auth()->guest()) {
                            return auth()->user(); // Will be null if guest
                        }

                        return parent::resolve($subject);
                    } catch (\Exception $e) {
                        // If anything goes wrong, return null instead of crashing
                        return null;
                    }
                }
            };
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish our config
            $this->publishes([
                __DIR__ . '/../../config/loggable.php' => config_path('loggable.php'),
            ], 'loggable-config');

            // Publish Spatie's activity log config
            $this->publishes([
                __DIR__.'/../../vendor/spatie/laravel-activitylog/config/activitylog.php' =>
                config_path('activitylog.php'),
            ], 'activitylog-config');

            // Publish Spatie's activity log migrations
            $this->publishes([
                __DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub' =>
                database_path('migrations/' . date('Y_m_d_His') . '_create_activity_log_table.php'),
            ], 'activitylog-migrations');
        }
    }
}
