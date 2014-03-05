<?php

use Illuminate\Database\Migrations\Migration;

class CreateFooWithStringScopesTable extends Migration {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('foo_with_string_scopes', function($table)
		{
			$table->increments('id');
			$table->string('name');
			$table->integer('position')->nullable();
			$table->string('company')->default('companyA');
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
		Schema::drop('foo_with_string_scopes');
	}

}