<?php

namespace ArtflowStudio\LaravelDynamicDb;

use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Http/Controllers/Database' => app_path('Http/Controllers/Database'),
            __DIR__.'/Middleware' => app_path('Http/Middleware'),
            __DIR__.'/views' => resource_path('views/vendor/database-config'),
            __DIR__.'/storage' => storage_path(),
        ], 'laravel-database-config');
    }

    public function register()
    {
        // Register any service providers or bindings here
    }
}
