# Listify

## Description

`Listify` provides the capabilities for sorting and reordering a number of objects in a list. The class that has this specified needs to have a `position` column defined as an integer on the mapped database table.

* [Requirements](#requirements)
* [Installation](#installation)
* [Quick Start](#quickstart)
* [Overview](#overview)
* [Configuration](#configuration)
* [Notes](#notes)

## Requirements
* `Listify` currently requires php >= 5.4 (`Listify` is implemented via the use of traits).
* Laravel 4

## Installation
`Listify` is distributed as a composer package, which is how it should be used in your app.

Install the package using Composer.  Edit your project's `composer.json` file to require `lookitsatravis/listify`.

```js
  "require": {
    "laravel/framework": "4.0.*",
    "lookitsatravis/listify": "dev-master"
  }  
```

Once this operation completes, the final step is to add the service provider. Open `app/config/app.php`, and add a new item to the providers array.

```php
    'lookitsatravis\Listify\ListifyServiceProvider' 
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
    use lookitsatravis\Listify\listify;

    public function __construct(array $attributes = array()) {

        parent::__construct($attributes);

        $this->initListify();
    }
}
```

> Make sure that the `initListify()` method is called *after* `parent::__construct()` of your model.

That's all it takes to get access to the `Listify` hotness.

##Overview

### Instance Methods Added To Eloquent Models

You'll have a number of methods added to each instance of the Eloquent model that to which `Listify` is added. 

In `Listify`, "higher" means further up the list (a lower `position`), and "lower" means further down the list (a higher `position`). That can be confusing, so it might make sense to add tests that validate that you're using the right method given your context.

#### Methods That Change Position and Reorder List

- `eloquentModel.insertAt(2)`
- `eloquentModel.moveLower()` will do nothing if the item is the lowest item
- `eloquentModel.moveHigher()` will do nothing if the item is the highest item
- `eloquentModel.moveToBottom()`
- `eloquentModel.moveToTop()`
- `eloquentModel.removeFromList()`

#### Methods That Change Position Without Reordering List

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

## Notes

If the `position` column has a default value, then there is a slight change in behavior, i.e if you have 4 items in the list, and you insert 1, with a default position 0, it would be pushed to the bottom of the list. Please look at the tests for this and some recent pull requests for discussions related to this.

All `position` queries (select, update, etc.) inside trait methods are executed without the default scope, this will prevent nasty issues when the default scope is different from `Listify` scope.

The `position` column is set after validations are called, so you should not put a `presence` validation on the `position` column.

## Build Status
[![Build Status](https://secure.travis-ci.org/lookitsatravis/listify.png)](https://secure.travis-ci.org/lookitsatravis/listify)

## Contributing to `Listify`
 
- Check out the latest master to make sure the feature hasn't been implemented or the bug hasn't been fixed yet
- Check out the issue tracker to make sure someone already hasn't requested it and/or contributed it
- Fork the project
- Start a feature/bugfix branch
- Commit and push until you are happy with your contribution
- Make sure to add tests for it. This is important so I don't break it in a future version unintentionally.
- Please try not to mess with the Composer.json, version, or history. If you want to have your own version, or is otherwise necessary, that is fine, but please isolate to its own commit so I can cherry-pick around it.
- I would recommend using Laravel 4.0.x and higher for testing the build before a pull request.

## Copyright

Copyright (c) 2013 Travis Vignon, released under the MIT license