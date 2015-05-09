# Listify

Turn any Eloquent model into a list!

## Description

`Listify` provides the capabilities for sorting and reordering a number of objects in a list. The class that has this specified needs to have a `position` column defined as an integer on the mapped database table. `Listify` is an Eloquent port of the highly useful Ruby gem `acts_as_list` (https://github.com/swanandp/acts_as_list).

[![Build Status](https://secure.travis-ci.org/lookitsatravis/listify.png)](https://secure.travis-ci.org/lookitsatravis/listify)
[![Coverage Status](https://coveralls.io/repos/lookitsatravis/listify/badge.png)](https://coveralls.io/r/lookitsatravis/listify)

[![Latest Stable Version](https://poser.pugx.org/lookitsatravis/listify/v/stable.png)](https://packagist.org/packages/lookitsatravis/listify)

* [Requirements](#requirements)
* [Installation](#installation)
* [Quick Start](#quickstart)
* [Overview](#overview)
* [Configuration](#configuration)
* [Notes](#notes)
* [Future Plans](#futureplans)
* [Contributing](#contributing)
* [Copyright](#copyright)


## Requirements
* `Listify` currently requires php >= 5.4 (`Listify` is implemented via the use of traits).
* Laravel 5.0 or higher

> For use with Laravel 4, please use version 1.0.6.

## Installation
`Listify` is distributed as a composer package, which is how it should be used in your app.

Install the package using Composer.  Edit your project's `composer.json` file to require `lookitsatravis/listify`.

```js
  "require": {
    "laravel/framework": "~5.0",
    "lookitsatravis/listify": "1.1.x"
  }
```

Once this operation completes, the final step is to add the service provider. Open `app/config/app.php`, and add a new item to the providers array.

```php
    'Lookitsatravis\Listify\ListifyServiceProvider'
```

Optionally, you can define an alias to the `Listify` trait. Open `app/config/app.php`, and add a new item to the aliases array.

```php
    'Listify' => 'Lookitsatravis\Listify\Listify'
```

## Quickstart

First things first, you'll need to add a column to store the position. From the command line, use the migration generator:

```php
php artisan listify:attach {table_name} {position_column_name}
php artisan migrate
```

> `{table_name}` is a required argument. `{position_column_name}` is optional and the default value is "position".

Then, in your model:

```php
class User extends Eloquent
{
    use \Lookitsatravis\Listify\Listify;

    public function __construct(array $attributes = array(), $exists = false) {

        parent::__construct($attributes, $exists);

        $this->initListify();
    }
}
```

> Make sure that the `initListify()` method is called *after* `parent::__construct()` of your model.

That's all it takes to get access to the `Listify` hotness.

## Overview

### Instance Methods Added To Eloquent Models

You'll have a number of methods added to each instance of the Eloquent model to which `Listify` is added.

In `Listify`, "higher" means further up the list (a lower `position`), and "lower" means further down the list (a higher `position`). That can be confusing, so it might make sense to add tests that validate that you're using the right method given your context.

#### Methods That Change Position and Reorder List

- `eloquentModel.insertAt(2)`
- `eloquentModel.moveLower()` will do nothing if the item is the lowest item
- `eloquentModel.moveHigher()` will do nothing if the item is the highest item
- `eloquentModel.moveToBottom()`
- `eloquentModel.moveToTop()`
- `eloquentModel.removeFromList()`

#### Methods That Change Position Without Reordering List Immediately
###### Note: a changed position will still trigger updates to other items in the list once the model is saved

- `eloquentModel.incrementPosition()`
- `eloquentModel.decrementPosition()`
- `eloquentModel.setListPosition(3)`

#### Methods That Return Attributes of the Item's List Position
- `eloquentModel.isFirst()`
- `eloquentModel.isLast()`
- `eloquentModel.isInList()`
- `eloquentModel.isNotInList()`
- `eloquentModel.isDefaultPosition()`
- `eloquentModel.higherItem()`
- `eloquentModel.higherItems()` will return all the items above `eloquentModel` in the list (ordered by the position, ascending)
- `eloquentModel.lowerItem()`
- `eloquentModel.lowerItems()` will return all the items below `eloquentModel` in the list (ordered by the position, ascending)

##Configuration

There are a few configuration options available. You'll need to pass these in as an array argument for `initListify()` in your model's constructor. Here are the options:

- `top_of_list` sets the integer position for the top of the list (default: `1`).
- `column` sets the name of your position column that you chose during installation (default: `'position'`).
- `add_new_at` sets the name of your position column that you chose during installation (default: `'bottom'`, options: `'top'` or `'bottom'`).
- `scope` allows you to scope the items in your list. This one requires a bit of elaboration. There are three posible values accepted:
    - `string`
    - `Illuminate\Database\Eloquent\Relations\BelongsTo` object
    - `Illuminate\Database\Query\Builder` object

***

###String

If `string` is passed in, a raw string is passed in as a `whereRaw` to the scope. This allows you to do something like `'custom_foreign_key = 42'` and have all of the items scoped to that result set. You can pass as complicated of a where clause as you want, and it will be passed straight into each DB operation.

Example:

```php
class User extends Eloquent
{
    use \Lookitsatravis\Listify\Listify;

    public function __construct(array $attributes = array(), $exists = false) {

        parent::__construct($attributes, $exists);

        $this->initListify([
            'scope' => 'answer_to_ltuae = 42'
        ]);
    }
}
```

Results in a scope of:

`WHERE answer_to_ltuae = 42`

***

###Illuminate\Database\Eloquent\Relations\BelongsTo

If `Illuminate\Database\Eloquent\Relations\BelongsTo` is passed in, `Listify` will match up the foreign key of the scope to the value of the corresponding foreign key of the model instance.

Example:

```php
class ToDoListItem extends Eloquent
{
    use \Lookitsatravis\Listify\Listify;

    public function __construct(array $attributes = array(), $exists = false) {

        parent::__construct($attributes, $exists);

        $this->initListify([
            'scope' => $this->toDoList()
        ]);
    }

    public function toDoList()
    {
        $this->belongsTo('ToDoList');
    }
}
```

Results in a scope of:

`WHERE to_do_list_id = {{value of toDoListItem.to_do_list_id}}`

***

###Illuminate\Database\Query\Builder

And lastly, if `Illuminate\Database\Query\Builder` is passed in, `Listify` will extract the where clause of the builder and use it as the scope of the `Listify` items. This scope type was added in an attempt to keep parity between the `acts_as_list` version and `Listify`; **however, due to differences in the languages and in ActiveRecord versus Eloquent, it is a limited implementation so far and needs impovement to be more flexible and secure. This is a big limitation and will be the first thing addressed in upcoming releases.**

This one is tricky, because in order for it to work the query objects `where` array is prepared with the bindings *outside of PDO* and then passed in as a raw string. So, please keep in mind that this route can open your application up to abuse if you are not careful about how the object is built. If you use direct user input, please sanitize the data before using this as a scope for `Listify`.

Example:

```php
class ToDoListItem extends Eloquent
{
    use \Lookitsatravis\Listify\Listify;

    public function __construct(array $attributes = array(), $exists = false) {

        parent::__construct($attributes, $exists);

        $this->initListify([
            'scope' => DB::table($this->getTable())->where('type', '=', 'Not A List of My Favorite Porn Videos')
        ]);
    }
}
```

Results in a scope of:

`to_do_list_items.type = 'Not A List of My Favorite Porn Videos'`

***

### Changing the configuration

You may also change any configuration value during runtime by using `$this->setListifyConfig('key', 'value');`. For example, to change the scope, you can do this:

```php
$this->setListifyConfig('scope', 'what_does_the_fox_say = "ring ding ding ding"');
```

When an update is processed, the original scope will be used to remove the record from that list, and insert it into the new list scope. *Be careful here*. Changing the configuration during processing can have unpredictable effects.

## Notes

All `position` queries (select, update, etc.) inside trait methods are executed without the default scope, this will prevent nasty issues when the default scope is different from `Listify` scope.

The `position` column is set after validations are called, so you should not put a `presence` validation on the `position` column.

## Future Plans

- Add support for using a closure as a scope
- Update `Illuminate\Database\Query\Builder` scope to be more secure and flexible
- Additional features for the install command. Things like:
    - update the model with trait automatically (including init method in constructor)
    - generate (or add to) a controller with actions for each public method for `Listify`, including adding necessary routes. This would make it easy to, say, call something like `http://localhost:8000/foos/1/move_lower` through an AJAX-y front end.

Aside from that, I hope to just keep in parity with the Ruby gem `acts_as_list` (https://github.com/swanandp/acts_as_list) as necessary.

## Contributing to `Listify`

- Check out the latest master to make sure the feature hasn't been implemented or the bug hasn't been fixed yet
- Check out the issue tracker to make sure someone already hasn't requested it and/or contributed it
- Fork the project
- Start a feature/bugfix branch
- Commit and push until you are happy with your contribution
- Make sure to add tests for it. This is important so I don't break it in a future version unintentionally.
- Please try not to mess with the Composer.json, version, or history. If you want to have your own version, or is otherwise necessary, that is fine, but please isolate to its own commit so I can cherry-pick around it.
- I would recommend using Laravel 5.0 and higher for testing the build before a pull request.

## Copyright

Copyright (c) 2013-2014 Travis Vignon, released under the MIT license
