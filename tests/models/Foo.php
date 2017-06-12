<?php

use Illuminate\Database\Eloquent\Model as Eloquent;
use Lookitsatravis\Listify\Listify;

class Foo extends Eloquent
{
    use Listify;

    /**
     * The fillable array lets laravel know which fields are fillable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * The rules array lets us know how to to validate this model.
     *
     * @var array
     */
    public $rules = [
        'name' => 'required',
    ];
}
