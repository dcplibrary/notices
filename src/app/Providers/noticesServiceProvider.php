<?php

namespace Dcplibrary\notices\App\Providers;

use Illuminate\Support\ServiceProvider;

class noticesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register package services
        $this->app->singleton('notices', function ($app) {
            return new \Dcplibrary\notices\notices();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load package routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        
        // Load package views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'notices');
        
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/Migrations');
        
        // Load package config
        $this->mergeConfigFrom(__DIR__.'/../../config/notices.php', 'notices');
        
        // Register package commands
        if ($this->app->runningInConsole()) {
            // Publish package config
            $this->publishes([
                __DIR__.'/../../config/notices.php' => config_path('notices.php'),
            ], 'notices-config');
            
            // Publish package views
            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/notices'),
            ], 'notices-views');
        }
    }
}
