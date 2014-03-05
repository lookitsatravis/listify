<?php

use Illuminate\Database\Migrations\Migration;

class CreateFooWithBelongstoScopeBsTable extends Migration {

    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('foo_with_belongsto_scope_bs', function($table)
        {
            $table->increments('id');
            $table->string('name');
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
        Schema::drop('foo_with_belongsto_scope_bs');
    }

}