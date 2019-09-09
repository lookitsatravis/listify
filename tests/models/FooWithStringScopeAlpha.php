<?php

use Lookitsatravis\Listify\Listify;
use Illuminate\Database\Eloquent\Model as Eloquent;

class FooWithStringScopeAlpha extends Eloquent
{
    use Listify;

    protected $table = 'foo_with_string_scopes';

    /**
     * The fillable array lets laravel know which fields are fillable.
     *
     * @var array
     */
    protected $fillable = ['name', 'company'];

    /**
     * The rules array lets us know how to to validate this model.
     *
     * @var array
     */
    public $rules = [
        'name' => 'required',
        'company' => 'required',
    ];

    /**
     * Constructor.
     *
     * @param array $attributes - An array of attributes to initialize the model with
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->getListifyConfig()->setScope("company = 'companyA'");
    }
}
