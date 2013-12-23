<?php

/*
This is here because the files aren't being autoloaded by PHPUnit due to the scope of the testing.
If you have a better idea, I'm all ears! travis@lookitsatravis.com
 */

require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Listify.php';
require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Exceptions/ListifyException.php';
require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Exceptions/NullForeignKeyException.php';
require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Exceptions/NullScopeException.php';
require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Exceptions/InvalidScopeException.php';
require_once __DIR__ . '/../../../../src/lookitsatravis/Listify/Exceptions/InvalidQueryBuilderException.php';

use Way\Tests\Assert;

class ListifyBaseTest extends TestCase {

    protected $model = 'Foo';
    protected $belongsToFunction = NULL;
    protected $belongsToObject = NULL;

    public function setUp()
    {
        parent::setUp();

        try
        {
            Artisan::call('migrate:rollback', array('--env' => 'testing'));
        } catch(Exception $e) { }

        Artisan::call('migrate', array('--env' => 'testing'));

        //Allows model events to work during testing
        $model = $this->model;
        $model::boot();

        for($i = 1; $i <= 10; $i++)
        {
            $foo = App::make($this->model);
            $foo->name = $this->model . $i;

            if($this->belongsToFunction && $this->belongsToObject)
            {
                $btf = $this->belongsToFunction;
                $bto = $this->belongsToObject;
                $foo->$btf()->associate($bto);
            }

            $foo->save();
        }

        $this->reloadFoos();
    }

    public function tearDown()
    {
        parent::tearDown();
        
        $model = $this->model;
        $model::flushEventListeners();
    }

    public function test_defaultPosition()
    {
        $foo = App::make($this->model);
        Assert::null($foo->position);
    }

    public function test_isInDefaultPosition()
    {
        $foo = App::make($this->model);
        Assert::true($foo->isDefaultPosition());
    }

    public function test_inListScope()
    {
        $allFoos = $this->foos[0]->inList()->get();
        
        Assert::eq(10, count($allFoos));

        $this->foos[9]->delete();

        $allFoos = $this->foos[0]->inList()->get();
        
        Assert::eq(9, count($allFoos));

        $this->childAssertion();
    }

    public function test_getListifyPosition()
    {
        $position = 1;
        foreach($this->foos as $foo)
        {
            Assert::eq($position, $foo->getListifyPosition());
            $position++;
        }

        $this->childAssertion();
    }

    public function test_setListPositionUp()
    {
        $this->foos[1]->setListPosition(1);

        $this->reloadFoos();

        $position = 3;
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(2, $foo->getListifyPosition());
            elseif($foo->name == $this->model . "2")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
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
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(2, $foo->getListifyPosition());
            elseif($foo->name == $this->model . "2")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_insertAtDefault() //top
    {
        $foo = App::make($this->model);
        $foo->name = $this->model . "New";

        if($this->belongsToFunction && $this->belongsToObject)
        {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->insertAt(); //Defaults to top of list

        Assert::eq(1, $foo->getListifyPosition());

        $this->reloadFoos();

        //Check that the other items have moved down
        $position = 2;
        foreach($this->foos as $foo)
        {
            Assert::eq($position, $foo->getListifyPosition());
            $position++;

            if($position == 11) break; //There aren't any more records after 10
        }
        
        $this->childAssertion();
    }

    public function test_insertAtSpecificPosition()
    {
        $foo = App::make($this->model);
        $foo->name = $this->model . "New";

        if($this->belongsToFunction && $this->belongsToObject)
        {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->insertAt(2);

        Assert::eq(2, $foo->getListifyPosition());

        $this->reloadFoos();

        //Check that the other items have moved down
        $position = 3;
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;

                if($position == 11) break;
            }
        }

        $this->childAssertion();
    }

    public function test_insertAtWhenAlreadyInList()
    {
        $foo = App::make($this->model);
        $foo->name = $this->model . "New";

        if($this->belongsToFunction && $this->belongsToObject)
        {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->save(); //currently at the bottom

        $foo->insertAt(1);

        $this->reloadFoos();

        //Check that the other items have moved down
        $position = 2;
        foreach($this->foos as $foo)
        {
            if($foo->name != $this->model . "New")
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;

                if($position == 11) break;
            }
        }

        $this->childAssertion();
    }

    public function test_moveLower()
    {
        $this->foos[0]->moveLower();

        $this->reloadFoos();

        $position = 3;
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(2, $foo->getListifyPosition());
            elseif($foo->name == $this->model . "2")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
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
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(2, $foo->getListifyPosition());
            elseif($foo->name == $this->model . "2")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
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
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(10, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
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
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "10")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
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
        foreach($this->foos as $foo)
        {
            if($foo->id == 1)
                Assert::eq(NULL, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_incrementPosition()
    {
        $this->foos[0]->incrementPosition();

        Assert::eq(2, $this->foos[0]->getListifyPosition());

        $position = 2;
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1")
                Assert::eq(2, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_decrementPosition()
    {
        $this->foos[1]->decrementPosition();

        Assert::eq(1, $this->foos[1]->getListifyPosition());

        $position = 3;
        foreach($this->foos as $foo)
        {
            if($foo->name == $this->model . "1" || $foo->name == $this->model . "2")
                Assert::eq(1, $foo->getListifyPosition());
            else
            {
                Assert::eq($position, $foo->getListifyPosition());
                $position++;
            }
        }

        $this->childAssertion();
    }

    public function test_isFirst()
    {
        Assert::true($this->foos[0]->isFirst());
        Assert::false($this->foos[1]->isFirst());
    }

    public function test_isLast()
    {
        Assert::false($this->foos[0]->isLast());
        Assert::true($this->foos[9]->isLast());
    }

    public function test_higherItem()
    {
        $higherItem = $this->foos[1]->higherItem();

        Assert::true($higherItem->name == $this->model . "1");

        $higherItem = $this->foos[0]->higherItem();

        Assert::true($higherItem == NULL);
    }

    public function test_higherItemsAll()
    {
        $higherItems = $this->foos[0]->higherItems();

        Assert::true(count($higherItems) == 0);

        $higherItems = $this->foos[9]->higherItems();

        foreach ($higherItems as $item)
        {
            $ids = [1,2,3,4,5,6,7,8,9];
            Assert::true(in_array($item->id, $ids));
        }
    }

    public function test_higherItemsWithLimit()
    {
        $higherItems = $this->foos[0]->higherItems(1);

        Assert::true(count($higherItems) == 0);

        $higherItems = $this->foos[9]->higherItems(1);

        foreach ($higherItems as $item)
        {
            Assert::true($item->id == 9);
        }
    }

    public function test_lowerItem()
    {
        $lowerItem = $this->foos[0]->lowerItem();

        Assert::true($lowerItem->  name == $this->model . "2");

        $lowerItem = $this->foos[9]->lowerItem();

        Assert::true($lowerItem == NULL);
    }

    public function test_lowerItemsAll()
    {
        $lowerItems = $this->foos[9]->lowerItems();

        Assert::true(count($lowerItems) == 0);

        $lowerItems = $this->foos[0]->lowerItems();

        foreach ($lowerItems as $item)
        {
            $ids = [2,3,4,5,6,7,8,9,10];
            Assert::true(in_array($item->id, $ids));
        }
    }

    public function test_lowerItemsWithLimit()
    {
        $lowerItems = $this->foos[9]->lowerItems(1);

        Assert::true(count($lowerItems) == 0);

        $lowerItems = $this->foos[0]->lowerItems(1);

        foreach ($lowerItems as $item)
        {
            Assert::true($item->id == 2);
        }
    }

    public function test_isInList()
    {
        Assert::true($this->foos[0]->isInList());

        $foo = App::make($this->model);

        Assert::false($foo->isInList());
    }

    public function test_isNotInList()
    {
        Assert::false($this->foos[0]->isNotInList());

        $foo = App::make($this->model);

        Assert::true($foo->isNotInList());
    }

    public function test_addToListTop()
    {
        $foo = App::make($this->model);
        $foo->name = $this->model . "New";

        if($this->belongsToFunction && $this->belongsToObject)
        {
            $btf = $this->belongsToFunction;
            $bto = $this->belongsToObject;
            $foo->$btf()->associate($bto);
        }

        $foo->setListifyConfig('add_new_at', 'top');
        $foo->save();

        Assert::eq(1, $foo->getListifyPosition());
    }

    /**
     * @expectedException lookitsatravis\Listify\Exceptions\InvalidScopeException
     */
    public function test_invalidScopeExceptionNonObject()
    {
        $foo = $this->model;
        $foo = new $foo();
        $foo->name = $this->model . "Test";
        $foo->setListifyConfig('scope', 1);
        $foo->save();
    }

    /**
     * @expectedException lookitsatravis\Listify\Exceptions\InvalidScopeException
     */
    public function test_invalidScopeExceptionObject()
    {
        $foo = $this->model;
        $foo = new $foo();
        $foo->name = $this->model . "Test";
        $foo->setListifyConfig('scope', App::make($this->model));
        $foo->save();
    }

    protected function reloadFoos()
    {
        $this->foos = App::make($this->model)->orderBy('id', "ASC")->get()->all();
    }

    protected function childAssertion()
    {

    }
}