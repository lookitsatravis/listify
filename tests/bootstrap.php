<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

require_once __DIR__ . '/../vendor/autoload.php';

// Create database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
$capsule->setEventDispatcher(new Dispatcher);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Create DB schema
$capsule->schema()->dropIfExists('foos');
$capsule->schema()->dropIfExists('foo_with_string_scopes');
$capsule->schema()->dropIfExists('foo_with_belongsto_scope_as');
$capsule->schema()->dropIfExists('foo_with_belongsto_scope_bs');
$capsule->schema()->dropIfExists('foo_with_query_builder_scopes');

$capsule->schema()->create('foos', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->integer('position')->nullable();
    $table->timestamps();
});
$capsule->schema()->create('foo_with_string_scopes', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->integer('position')->nullable();
    $table->string('company')->default('companyA');
    $table->timestamps();
});
$capsule->schema()->create('foo_with_belongsto_scope_as', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->integer('position')->nullable();
    $table->integer('foo_with_belongsto_scope_b_id');
    $table->timestamps();
});
$capsule->schema()->create('foo_with_belongsto_scope_bs', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->timestamps();
});
$capsule->schema()->create('foo_with_query_builder_scopes', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->integer('position')->nullable();
    $table->string('company')->default('ACME');
    $table->integer('alt_id')->nullable();
    $table->timestamps();
});

