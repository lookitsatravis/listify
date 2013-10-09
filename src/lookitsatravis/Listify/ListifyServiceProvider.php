<?php namespace lookitsatravis\Listify;

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
		$this->package('lookitsatravis/listify');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{	
		$this->registerListifyAttach();

		$this->commands('listify.attach');
	}

	/**
	 * Register the listify command with the container.
	 * 
	 * @return void
	 */
	protected function registerListifyAttach()
	{
		$this->app->bind('listify.attach', function($app) 
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