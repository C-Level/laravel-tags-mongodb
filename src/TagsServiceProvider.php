<?php

namespace Clevel\Tags;

use Illuminate\Support\ServiceProvider;

class TagsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tags.php' => config_path('tags.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    { }
}
