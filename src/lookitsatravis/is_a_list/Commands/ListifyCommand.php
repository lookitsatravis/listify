<?php namespace lookitsatravis\is_a_list\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use DB, View, File;

class ListifyCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'listify';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate a migration for adding position to a database table.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		try {
			DB::table($this->argument('table'))->first();
			$this->createMigration();

		} catch(Exception $e) {
			$this->error("No such table found in database: " . $this->argument('table'));
		}
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('table', InputArgument::REQUIRED, 'The name of the database table the position field will be added to.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}

	/**
	 * Create a new migration.
	 *
	 * @return void
	 */
	public function createMigration()
	{
		$table_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->argument('table'))));
		$data = ['table' => $table_name];
		$prefix = date('Y_m_d_His');
		$path = app_path() . '/database/migrations';

		if (!is_dir($path)) mkdir($path);

		$fileName  = $path . '/' . $prefix . '_add_position_to_' . $data['table'] . '_table.php';
		$data['className'] = 'AddPositionTo' . $data['table'] . 'Table';

		// Save the new migration to disk using the stapler migration view.
		$migration = View::make('is_a_list::migration', $data)->render();
		File::put($fileName, $migration);
		
		// Dump the autoloader and print a created migration message to the console.
		$this->call('dump-autoload');
		$this->info("Created migration: $fileName");
	}

}