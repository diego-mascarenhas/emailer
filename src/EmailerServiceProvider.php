<?php

namespace idoneo\Emailer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use idoneo\Emailer\Console\Commands\SendPendingMessagesCommand;
use Illuminate\Support\Facades\Route;

class EmailerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * Professional email marketing package with maximum deliverability.
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('emailer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_emailer_message_types_table',
                'create_emailer_messages_table',
                'create_emailer_message_deliveries_table',
                'create_emailer_message_delivery_tracking_table',
                'create_emailer_message_delivery_links_table',
                'create_emailer_message_delivery_stats_table'
            ])
            ->hasCommands([
                SendPendingMessagesCommand::class,
            ]);
    }

    public function packageBooted()
    {
        // Register routes
        $this->registerRoutes();
    }

    /**
     * Register package routes
     */
    protected function registerRoutes(): void
    {
        $routeConfig = config('emailer.routes', []);

        Route::group([
            'prefix' => $routeConfig['prefix'] ?? 'emailer',
            'middleware' => $routeConfig['middleware'] ?? ['web'],
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register additional services
     */
    public function packageRegistered(): void
    {
        // Register any additional bindings if needed
        $this->app->bind('emailer', function () {
            return new Emailer();
        });
    }
}
