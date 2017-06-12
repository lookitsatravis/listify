<?php

use PHPUnit\Framework\TestCase;
use Lookitsatravis\Listify\Config;

class ConfigTest extends TestCase
{
    protected $config;

    public function setUp()
    {
        parent::setUp();

        $this->config = new Config();
    }

    public function test_gettingAndSetttingTopPosition()
    {
        $this->assertEquals(1, $this->config->getTopPositionInList());
        $this->config->setTopPositionInList(0);
        $this->assertEquals(0, $this->config->getTopPositionInList());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_invalidPositionThrowsWhenSettingTopPosition()
    {
        $this->config->setTopPositionInList('foo');
    }

    public function test_gettingAndSettingPositionColumnName()
    {
        $this->assertEquals('position', $this->config->getPositionColumnName());
        $this->config->setPositionColumnName('order');
        $this->assertEquals('order', $this->config->getPositionColumnName());
    }

    public function test_gettingAndSettingScope()
    {
        $this->assertEquals('1 = 1', $this->config->getScope());
        $this->config->setScope('"foo" = "foo"');
        $this->assertEquals('"foo" = "foo"', $this->config->getScope());
    }

    public function test_gettingAndSettingAddNewItemTo()
    {
        $this->assertEquals(Config::POSITION_BOTTOM, $this->config->getAddNewItemTo());
        $this->config->setAddNewItemTo(Config::POSITION_TOP);
        $this->assertEquals(Config::POSITION_TOP, $this->config->getAddNewItemTo());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_invalidPositionThrowsWhenSettingAddNewItemTo()
    {
        $this->config->setAddNewItemTo('something');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_assertKeyIsValid()
    {
        // Grab ahold of the method and change its accessibility.
        $method = new \ReflectionMethod('\Lookitsatravis\Listify\Config', 'assertKeyIsValid');
        $method->setAccessible(true);

        $method->invoke($this->config, 'foo');
    }
}
