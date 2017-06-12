<?php

use Lookitsatravis\Listify\Listify;
use Illuminate\Database\Eloquent\Model as Eloquent;

class FooWithBelongstoScopeA extends Eloquent
{
    use Listify;

    /**
     * The fillable array lets laravel know which fields are fillable.
     *
     * @var array
     */
    protected $fillable = ['name', 'foo_with_belongsto_scope_b_id'];

    /**
     * The rules array lets us know how to to validate this model.
     *
     * @var array
     */
    public $rules = [
        'name' => 'required',
        'foo_with_belongsto_scope_b_id' => 'required',
    ];

    /**
     * Constructor.
     *
     * @param array $attributes - An array of attributes to initialize the model with
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->getListifyConfig()->setScope($this->foo_with_belongsto_scope_b());
    }

    public function foo_with_belongsto_scope_b()
    {
        return $this->belongsTo('FooWithBelongstoScopeB');
    }
}
