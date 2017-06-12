<?php

namespace Lookitsatravis\Listify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AttachCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'listify:attach';

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

            if (!Schema::hasColumn($this->argument('table'), $this->argument('column'))) {
                $this->createMigration();
            } else {
                $this->error('Table already contains a column called '.$this->argument('column'));
            }
        } catch (Exception $e) {
            $this->error('No such table found in database: '.$this->argument('table'));
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['table', InputArgument::REQUIRED, 'The name of the database table the Listify field will be added to.'],
            ['column', InputArgument::OPTIONAL, 'The name of the column to be used by Listify.', 'position']
        ];
    }

    /**
     * Create a new migration.
     *
     * @return void
     */
    public function createMigration()
    {
        $targetTableClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->argument('table'))));
        $targetColumnClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->argument('column'))));
        $data = [
            'targetTableClassName' => $targetTableClassName,
            'targetColumnClassName' => $targetColumnClassName,
            'tableName' => strtolower($this->argument('table')),
            'columnName' => strtolower($this->argument('column')),
        ];

        $prefix = date('Y_m_d_His');
        $path = base_path().'/database/migrations';

        if (!is_dir($path)) {
            mkdir($path);
        }

        $fileName  = $path.'/'.$prefix.'_add_'.$data['columnName'].'_to_'.$data['tableName'].'_table.php';
        $data['className'] = 'Add'.$data['targetColumnClassName'].'To'.$data['targetTableClassName'].'Table';

        // Save the new migration to disk using the stapler migration view.
        $migration = View::make('listify::migration', $data)->render();
        File::put($fileName, $migration);

        // Dump the autoloader and print a created migration message to the console.
        $this->info("Created migration: $fileName");
    }
}
