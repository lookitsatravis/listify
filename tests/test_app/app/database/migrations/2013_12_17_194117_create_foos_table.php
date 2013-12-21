<?php

use Illuminate\Database\Migrations\Migration;

class CreateFoosTable extends Migration {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{	
		Schema::create('foos', function($table)
		{
			$table->increments('id');
			$table->string('name');
			$table->integer('position')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('foos');
	}

}