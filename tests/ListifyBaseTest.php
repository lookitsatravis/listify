<?php

use PHPUnit\Framework\TestCase;
use Lookitsatravis\Listify\Config;
use Illuminate\Database\Capsule\Manager as Capsule;

class ListifyBaseTest extends TestCase
{
    protected $model = 'Foo';
    protected $belongsToFunction = null;
    protected $belongsToObject = null;

    protected function setUp(): void
    {
        $this->reloadDatabase();

        // Allows model events to work during testing
        $model = $this->model;
        $model::boot();

        for ($i = 1; $i <= 10; $i++) {
            $foo = new $this->model;
            $foo->name = $this->model.$i;

            if ($this->belongsToFunction && $this->belongsToObject) {
                $btf = $this->belongsToFunction;
                $bto = $this->belongsToObject;
                $foo->$btf()->associate($bto);
            }

            $foo->save();
        }

        $this->reloadFoos();
    }

    protected function tearDown(): void
    {
        $model = $this->model;
        $model::flushEventListeners();
    }

    public function test_inListScope()
    {
        $allFoos = $this->foos[0]->inList()->get();

        $this->assertEquals(10, count($allFoos));

        $this->foos[9]->delete();

        $allFoos = $this->foos[0]->inList()->get();

        $this->assertEquals(9, count($allFoos));

        $this->childAssertion();
    }

    public function test_getListifyPosition()
    {
        $position = 1;
        foreach ($this->foos as $foo) {
            $this->assertEquals($position, $foo->getListifyPosition());
            $position++;
        }

        $this->childAssertion();
    }

    public function test_setListPositionUp()
    {
        $this->foos[1]->setListPosition(1);

        $this->reloadFoos();

        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(2, $foo->getListifyPosition());
            } elseif ($foo->name == $this->model.'2') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_setListPositionDown()
    {
        $this->foos[0]->setListPosition(2);

        $this->reloadFoos();

        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(2, $foo->getListifyPosition());
            } elseif ($foo->name == $this->model.'2') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    //  Default is top
    public function test_insertAtDefault()
    {
        $foo = new $this->model;
        $foo->name = $this->model.'New';

        if ($this->belongsToFunction && $this->belongsToObject) {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        // Defaults to top of list
        $foo->insertAt();

        $this->assertEquals(1, $foo->getListifyPosition());

        $this->reloadFoos();

        // Check that the other items have moved down
        $position = 2;
        foreach ($this->foos as $foo) {
            $this->assertEquals($position, $foo->getListifyPosition());
            $position++;

            // There aren't any more records after 10
            if ($position == 11) {
                break;
            }
        }

        $this->childAssertion();
    }

    public function test_insertAtSpecificPosition()
    {
        $foo = new $this->model;
        $foo->name = $this->model.'New';

        if ($this->belongsToFunction && $this->belongsToObject) {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->insertAt(2);

        $this->assertEquals(2, $foo->getListifyPosition());

        $this->reloadFoos();

        //Check that the other items have moved down
        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;

                if ($position == 11) {
                    break;
                }
            }
        }

        $this->childAssertion();
    }

    public function test_insertAtWhenAlreadyInList()
    {
        $foo = new $this->model;
        $foo->name = $this->model.'New';

        if ($this->belongsToFunction && $this->belongsToObject) {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->save(); //currently at the bottom

        $foo->insertAt(1);

        $this->reloadFoos();

        //Check that the other items have moved down
        $position = 2;
        foreach ($this->foos as $foo) {
            if ($foo->name != $this->model.'New') {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;

                if ($position == 11) {
                    break;
                }
            }
        }

        $this->childAssertion();
    }

    public function test_moveLower()
    {
        $this->foos[0]->moveLower();

        $this->reloadFoos();

        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(2, $foo->getListifyPosition());
            } elseif ($foo->name == $this->model.'2') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_moveHigher()
    {
        $this->foos[1]->moveHigher();

        $this->reloadFoos();

        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(2, $foo->getListifyPosition());
            } elseif ($foo->name == $this->model.'2') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_moveToBottom()
    {
        $this->foos[0]->moveToBottom();

        $this->reloadFoos();

        $position = 1;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(10, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_moveToTop()
    {
        $this->foos[9]->moveToTop();

        $this->reloadFoos();

        $position = 2;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'10') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_removeFromList()
    {
        $this->foos[0]->removeFromList();

        $this->reloadFoos();

        $position = 1;
        foreach ($this->foos as $foo) {
            if ($foo->id == 1) {
                $this->assertEquals(null, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_incrementPosition()
    {
        $this->foos[0]->incrementPosition();

        $this->assertEquals(2, $this->foos[0]->getListifyPosition());

        $position = 2;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1') {
                $this->assertEquals(2, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_decrementPosition()
    {
        $this->foos[1]->decrementPosition();

        $this->assertEquals(1, $this->foos[1]->getListifyPosition());

        $position = 3;
        foreach ($this->foos as $foo) {
            if ($foo->name == $this->model.'1' || $foo->name == $this->model.'2') {
                $this->assertEquals(1, $foo->getListifyPosition());
            } else {
                $this->assertEquals($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_isFirst()
    {
        $this->assertTrue($this->foos[0]->isFirst());
        $this->assertFalse($this->foos[1]->isFirst());
    }

    public function test_isLast()
    {
        $this->assertFalse($this->foos[0]->isLast());
        $this->assertTrue($this->foos[9]->isLast());
    }

    public function test_higherItem()
    {
        $higherItem = $this->foos[1]->higherItem();

        $this->assertTrue($higherItem->name == $this->model.'1');

        $higherItem = $this->foos[0]->higherItem();

        $this->assertTrue($higherItem == null);
    }

    public function test_higherItemsAll()
    {
        $higherItems = $this->foos[0]->higherItems();

        $this->assertTrue(count($higherItems) == 0);

        $higherItems = $this->foos[9]->higherItems();

        foreach ($higherItems as $item) {
            $ids = [1, 2, 3, 4, 5, 6, 7, 8, 9];
            $this->assertTrue(in_array($item->id, $ids));
        }
    }

    public function test_higherItemsWithLimit()
    {
        $higherItems = $this->foos[0]->higherItems(1);

        $this->assertTrue(count($higherItems) == 0);

        $higherItems = $this->foos[9]->higherItems(1);

        foreach ($higherItems as $item) {
            $this->assertEquals(9, $item->id);
        }
    }

    public function test_lowerItem()
    {
        $lowerItem = $this->foos[0]->lowerItem();

        $this->assertTrue($lowerItem->name == $this->model.'2');

        $lowerItem = $this->foos[9]->lowerItem();

        $this->assertTrue($lowerItem == null);
    }

    public function test_lowerItemsAll()
    {
        $lowerItems = $this->foos[9]->lowerItems();

        $this->assertTrue(count($lowerItems) == 0);

        $lowerItems = $this->foos[0]->lowerItems();

        foreach ($lowerItems as $item) {
            $ids = [2, 3, 4, 5, 6, 7, 8, 9, 10];
            $this->assertTrue(in_array($item->id, $ids));
        }
    }

    public function test_lowerItemsWithLimit()
    {
        $lowerItems = $this->foos[9]->lowerItems(1);

        $this->assertTrue(count($lowerItems) == 0);

        $lowerItems = $this->foos[0]->lowerItems(1);

        foreach ($lowerItems as $item) {
            $this->assertTrue($item->id == 2);
        }
    }

    public function test_isInList()
    {
        $this->assertTrue($this->foos[0]->isInList());

        $foo = new $this->model;

        $this->assertFalse($foo->isInList());
    }

    public function test_isNotInList()
    {
        $this->assertFalse($this->foos[0]->isNotInList());

        $foo = new $this->model;

        $this->assertTrue($foo->isNotInList());
    }

    public function test_addToListTop()
    {
        $foo = new $this->model;
        $foo->name = $this->model.'New';

        if ($this->belongsToFunction && $this->belongsToObject) {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->getListifyConfig()->setAddNewItemTo(Config::POSITION_TOP);
        $foo->save();

        $this->assertEquals(1, $foo->getListifyPosition());
    }

    public function test_invalidScopeExceptionNonObject()
    {
        $this->expectException(\Lookitsatravis\Listify\Exceptions\InvalidScopeException::class);

        $foo = $this->model;
        $foo = new $foo();
        $foo->name = $this->model.'Test';
        $foo->getListifyConfig()->setScope(1);
        $foo->save();
    }

    public function test_invalidScopeExceptionObject()
    {
        $this->expectException(\Lookitsatravis\Listify\Exceptions\InvalidScopeException::class);

        $foo = $this->model;
        $foo = new $foo();
        $foo->name = $this->model.'Test';
        $foo->getListifyConfig()->setScope(new $this->model);
        $foo->save();
    }

    protected function reloadFoos()
    {
        $this->foos = (new $this->model)->orderBy('id', 'ASC')->get()->all();
    }

    protected function childAssertion()
    {
    }

    protected function reloadDatabase()
    {
        Capsule::table('foos')->truncate();
        Capsule::table('foo_with_string_scopes')->truncate();
        Capsule::table('foo_with_belongs_to_scope_alphas')->truncate();
        Capsule::table('foo_with_belongs_to_scope_bravos')->truncate();
        Capsule::table('foo_with_query_builder_scopes')->truncate();
    }
}
