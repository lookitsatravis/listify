<?php

namespace Lookitsatravis\Listify\Console\Commands;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

class AttachCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a migration for adding position to a database table.';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'listify:attach
                        {table : The name of the database table the Listify field will be added to.}
                        {column=position : The name of the column to be used by Listify.}';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->shouldCreateMigration()) {
                return $this->createMigration();
            }

            $this->error('Table already contains a column called '.$this->argument('column'));
        } catch (Exception $e) {
            $this->error('No such table found in database: '.$this->argument('table'));
        }
    }

    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function stubPath()
    {
        return __DIR__.'/stubs';
    }

    /**
     * Create a new migration.
     *
     * @return void
     */
    protected function createMigration(): void
    {
        $fileName = $this->getMigrationFileName(
            $className = $this->getClassName()
        );

        $file = str_replace('DummyClass', $className, $this->getStub());
        $file = str_replace('DummyTable', $this->getTableName(), $file);
        $file = str_replace('DummyColumn', $this->getColumnName(), $file);

        $this->files->put($fileName, $file);

        $this->info("Migration created: {$fileName}");
    }

    /**
     * Get the migration class name to generate.
     *
     * @return string
     */
    protected function getClassName()
    {
        $table = $this->getTableName();
        $column = $this->getColumnName();

        return Str::studly("add_{$column}_to_{$table}");
    }

    /**
     * Get the database table column's name.
     *
     * @return void
     */
    protected function getColumnName()
    {
        return Str::snake($this->argument('column'));
    }

    /**
     * Get the migration file name.
     *
     * @param string $className
     * @return string
     */
    protected function getMigrationFileName($className)
    {
        $now = date('Y_m_d_His');

        $file = $now.'_'.Str::snake($className);

        $path = $this->laravel->databasePath('migrations');

        return "{$path}/{$file}.php";
    }

    /**
     * Get the migration file stub.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->files->get($this->stubPath().'/migration.stub');
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    protected function getTableName()
    {
        return Str::snake($this->argument('table'));
    }

    /**
     * Determine if a new migration should be generated.
     *
     * @return bool
     */
    protected function shouldCreateMigration()
    {
        return ! Schema::hasColumn($this->getTableName(), $this->getColumnName());
    }
}
