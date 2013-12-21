<?php

use Illuminate\Database\Migrations\Migration;

class CreateFooWithBelongstoScopeAsTable extends Migration {

    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {   
        Schema::create('foo_with_belongsto_scope_as', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->integer('position')->nullable();
            $table->integer('foo_with_belongsto_scope_b_id');
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
        Schema::drop('foo_with_belongsto_scope_as');
    }

}