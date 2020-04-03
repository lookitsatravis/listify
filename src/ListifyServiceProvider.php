<?php

namespace Lookitsatravis\Listify;

use Illuminate\Support\ServiceProvider;
use Lookitsatravis\Listify\Console\Commands\AttachCommand;

class ListifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AttachCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
    }
}
