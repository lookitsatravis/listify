<?php

use Lookitsatravis\Listify\Listify;
use Illuminate\Database\Eloquent\Model as Eloquent;

class FooWithBelongsToScopeAlpha extends Eloquent
{
    use Listify;

    /**
     * The fillable array lets laravel know which fields are fillable.
     *
     * @var array
     */
    protected $fillable = ['name', 'foo_with_belongs_to_scope_bravo_id'];

    /**
     * The rules array lets us know how to to validate this model.
     *
     * @var array
     */
    public $rules = [
        'name' => 'required',
        'foo_with_belongs_to_scope_bravo_id' => 'required',
    ];

    /**
     * Constructor.
     *
     * @param array $attributes - An array of attributes to initialize the model with
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->getListifyConfig()->setScope($this->foo_with_belongs_to_scope_bravo());
    }

    public function foo_with_belongs_to_scope_bravo()
    {
        return $this->belongsTo(FooWithBelongsToScopeBravo::class);
    }
}
