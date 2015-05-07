<?php namespace Lookitsatravis\Listify;

use Illuminate\Support\ServiceProvider;

class ListifyServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $viewPath = __DIR__.'/../../views';
        $this->loadViewsFrom($viewPath, 'listify');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {   
        $this->registerConsoleCommands();
    }

    /**
     * Register the package console commands.
     *
     * @return void
     */
    protected function registerConsoleCommands()
    {
        $this->registerListifyAttach();
        $this->commands([
            'listify.attach'
        ]);
    }

    /**
     * Register the listify command with the container.
     * 
     * @return void
     */
    protected function registerListifyAttach()
    {
        $this->app->bindShared('listify.attach', function($app)
        {
            return new Commands\AttachCommand;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

}