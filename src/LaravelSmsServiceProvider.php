<?php

namespace Rayzenai\LaravelSms;

use Illuminate\Support\ServiceProvider;
use Rayzenai\LaravelSms\Services\SmsService;
use Filament\Panel;
use Filament\PanelProvider;

class LaravelSmsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-sms.php', 
            'laravel-sms'
        );

        // Bind provider interface
        $this->app->bind(\Rayzenai\LaravelSms\Providers\SmsProviderInterface::class, function ($app) {
            $providerClass = config('laravel-sms.default_provider', \Rayzenai\LaravelSms\Providers\HttpProvider::class);
            return $app->make($providerClass);
        });

        // Bind SmsService into the container
        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService($app->make(\Rayzenai\LaravelSms\Providers\SmsProviderInterface::class));
        });

        // Register facade accessor
        $this->app->alias(SmsService::class, 'sms');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        
        // Load migrations
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // Publishing config
        $this->publishes([
            __DIR__ . '/../config/laravel-sms.php' => config_path('laravel-sms.php'),
        ], 'laravel-sms-config');

        // Publishing migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laravel-sms-migrations');

        // Publishing routes
        $this->publishes([
            __DIR__ . '/../routes/api.php' => base_path('routes/laravel-sms-api.php'),
        ], 'laravel-sms-routes');

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Register any console commands here
            ]);
        }

        // Load Filament resources
        $this->loadFilamentResources();

        // Load view paths for Filament
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-sms');

        // Publishing views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-sms'),
        ], 'laravel-sms-views');

        // Publishing Filament resources
        $this->publishes([
            __DIR__ . '/Filament' => app_path('Filament/Resources/LaravelSms'),
        ], 'laravel-sms-filament');
    }

    /**
     * Load Filament resources.
     */
    protected function loadFilamentResources(): void
    {
        // Check if Filament is installed
        if (! class_exists('\Filament\FilamentServiceProvider')) {
            return;
        }

        // For Filament v3, resources are auto-discovered when published
        // Users should publish resources using:
        // php artisan vendor:publish --tag=laravel-sms-filament
        
        // Alternatively, if using within a Filament Panel Provider:
        // Add the following to your Panel configuration:
        // ->discoverResources(in: app_path('Filament/Resources/LaravelSms'), for: 'App\\Filament\\Resources\\LaravelSms')
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SmsService::class,
            'sms',
        ];
    }
}
