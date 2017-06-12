<?php

namespace Lookitsatravis\Listify;

use InvalidArgumentException;

class Config
{
    const POSITION_TOP = 'top';
    const POSITION_BOTTOM = 'bottom';

    const TOP_POSITION_IN_LIST_KEY = "topPositionInList";
    const POSITION_COLUMN_NAME_KEY = "positionColumnName";
    const SCOPE_KEY = "scope";
    const ADD_NEW_ITEM_TO_KEY = "addNewItemTo";

    protected $defaultTopPositionInList = 1;
    protected $defaultPositionColumnName = 'position';
    protected $defaultScope = '1 = 1';
    protected $defaultAddNewItemTo = self::POSITION_BOTTOM;

    protected $config = [];

    function __construct() {
        $this->config = $this->buildDefaultConfig();
    }

    public function setTopPositionInList($position) {
        if (!is_int($position)) {
            throw new InvalidArgumentException('Only integers are allowed.');
        }

        return $this->set(self::TOP_POSITION_IN_LIST_KEY, $position);
    }

    public function setPositionColumnName($name) {
        return $this->set(self::POSITION_COLUMN_NAME_KEY, $name);
    }

    public function setScope($scope) {
        return $this->set(self::SCOPE_KEY, $scope);
    }

    public function setAddNewItemTo($listPosition) {
        if ($listPosition != self::POSITION_TOP && $listPosition != self::POSITION_BOTTOM) {
            throw new InvalidArgumentException('Only POSITION_TOP and POSITION_BOTTOM are allowed.');
        }

        return $this->set(self::ADD_NEW_ITEM_TO_KEY, $listPosition);
    }

    public function getTopPositionInList() {
        return $this->get(self::TOP_POSITION_IN_LIST_KEY);
    }

    public function getPositionColumnName() {
        return $this->get(self::POSITION_COLUMN_NAME_KEY);
    }

    public function getScope() {
        return $this->get(self::SCOPE_KEY);
    }

    public function getAddNewItemTo() {
        return $this->get(self::ADD_NEW_ITEM_TO_KEY);
    }

    protected function buildDefaultConfig() {
        return [
            self::TOP_POSITION_IN_LIST_KEY => $this->defaultTopPositionInList,
            self::POSITION_COLUMN_NAME_KEY => $this->defaultPositionColumnName,
            self::SCOPE_KEY => $this->defaultScope,
            self::ADD_NEW_ITEM_TO_KEY => $this->defaultAddNewItemTo,
        ];
    }

    protected function get($key) {
        $this->assertKeyIsValid($key);
        return $this->config[$key];
    }

    protected function set($key, $value) {
        $this->assertKeyIsValid($key);
        $this->config[$key] = $value;
        return $this;
    }

    protected function assertKeyIsValid($key) {
        if (!in_array($key, $this->validKeys())) {
            throw new InvalidArgumentException("Key '$key' is invalid.");
        }
    }

    protected function validKeys() {
        return [self::TOP_POSITION_IN_LIST_KEY, self::POSITION_COLUMN_NAME_KEY, self::SCOPE_KEY, self::ADD_NEW_ITEM_TO_KEY];
    }
}
