<?php namespace lookitsatravis\is_a_list;

use Illuminate\Support\ServiceProvider;
use Codesleeve\Stapler\File\UploadedFile;

class ListServiceProvider extends ServiceProvider {

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
		$this->package('lookitsatravis/is_a_list');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{	
		$this->registerListify();

		$this->commands('listify');
	}

	/**
	 * Register the listify command with the container.
	 * 
	 * @return void
	 */
	protected function registerListify()
	{
		$this->app->bind('listify', function($app) 
		{
			return new Commands\ListifyCommand;
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