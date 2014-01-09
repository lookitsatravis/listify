<?php

use Way\Tests\Assert;

class ListifyModelWithQueryBuilderScopeTest extends ListifyBaseTest {

    protected $model = 'FooWithQueryBuilderScopeA';
    protected $modelScopeValue = "company = 'ACME'";

    private $modelB = 'FooWithQueryBuilderScopeB';
    private $modelBScopeValue = "company = 'NOT_ACME'";

    public function setUp()
    {
        parent::setUp();

        //Allows model events to work during testing
        $modelB = $this->modelB;
        $modelB::boot();

        for($i = 1; $i <= 10; $i++)
        {
            $foo = App::make($this->modelB);
            $foo->name = $this->modelB . $i;
            $foo->company = 'NOT_ACME';
            $foo->save();
        }

        $this->reloadBFoos();
    }

    public function tearDown()
    {
        parent::tearDown();
        
        $modelB = $this->modelB;
        $modelB::flushEventListeners();
    }

    /**
     * @expectedException Lookitsatravis\Listify\Exceptions\InvalidQueryBuilderException
     */
    public function test_passScopeInWithMissingWhere()
    {
        $foo = App::make($this->model);
        $foo->name = $this->model . "New";
        $foo->setListifyConfig('scope', DB::table('foos')->orderBy('id ASC'));
        $foo->save();
    }

    public function test_changeScopeBeforeUpdate()
    {
        $foo1 = App::make($this->model);
        $foo1->name = $this->model . "Test1";
        $foo1->company = 'TestCompany1';
        $foo1->setListifyConfig('scope', DB::table('foo_with_query_builder_scopes')->where('company', '=', 'TestCompany1'));
        $foo1->save();

        $foo2 = App::make($this->model);
        $foo2->name = $this->model . "Test2";
        $foo2->company = 'TestCompany1';
        $foo2->setListifyConfig('scope', DB::table('foo_with_query_builder_scopes')->where('company', '=', 'TestCompany1'));
        $foo2->save();

        Assert::eq(1, $foo1->getListifyPosition());
        Assert::eq(2, $foo2->getListifyPosition());

        $foo1->setListifyConfig('scope', DB::table('foo_with_query_builder_scopes')->where('company', '=', 'TestCompany2'));
        $foo1->save();

        Assert::eq(1, $foo1->getListifyPosition());
        Assert::eq(2, $foo2->getListifyPosition());
    }

    //The whole point of this is to validate that the secondary model (that shares the table) is not modified when manipulating the primary model. The scope should prevent that, so we validate that the secondary model has not changed after each test.
    protected function childAssertion()
    {
        $this->reloadBFoos();

        $position = 1;
        foreach($this->bfoos as $bfoo)
        {
            Assert::eq($position, $bfoo->getListifyPosition());
            $position++;
        }
    }

    protected function reloadFoos()
    {
        $this->foos = App::make($this->model)->whereRaw($this->modelScopeValue)->orderBy('id', "ASC")->get()->all();
    }

    private function reloadBFoos()
    {
        $this->bfoos = App::make($this->modelB)->whereRaw($this->modelBScopeValue)->orderBy('id', "ASC")->get()->all();
    }
}
