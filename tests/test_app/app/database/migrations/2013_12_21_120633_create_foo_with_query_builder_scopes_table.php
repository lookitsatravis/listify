<?php

use Illuminate\Database\Migrations\Migration;

class CreateFooWithQueryBuilderScopesTable extends Migration {

    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {   
        Schema::create('foo_with_query_builder_scopes', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->integer('position')->nullable();
            $table->string('company')->default('ACME');
            $table->integer('alt_id')->nullable();
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
        Schema::drop('foo_with_query_builder_scopes');
    }

}