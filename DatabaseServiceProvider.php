<?php

namespace ArtflowStudio\LaravelDynamicDb;

use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config and files
        $this->publishes([
            __DIR__.'/../storage' => storage_path(),
        ], 'laravel-dynamic-db');

        // Add middleware to kernel
        app('router')->aliasMiddleware('database.switch', DatabaseMiddleware::class);
    }

    public function register()
    {
        // Register any service providers or bindings here
    }
}
