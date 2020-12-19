<?php

namespace Lookitsatravis\Listify;

use Lookitsatravis\Listify\Exceptions\InvalidQueryBuilderException;
use Lookitsatravis\Listify\Exceptions\InvalidScopeException;
use Lookitsatravis\Listify\Exceptions\NullForeignKeyException;
use Lookitsatravis\Listify\Exceptions\NullScopeException;

/**
 * Gives some nice sorting features to a model.
 *
 * Ported from https://github.com/swanandp/acts_as_list
 *
 * @version 2.0.0
 *
 * @author Travis Vignon <travis@lookitsatravis.com>
 * @see http://lookitsatravis.github.io/listify
 */
trait Listify
{
    /**
     * Listify's configuration.
     *
     * @var Config
     */
    protected $listifyConfig;

    /**
     * Contains whether the original attributes are loaded on the model or not.
     *
     * @var bool
     */
    protected $originalAttributesLoaded = false;

    /**
     * Contains the current raw scope string. Used to check for changes.
     *
     * @var string
     */
    protected $stringScopeValue;

    /**
     * Container for the changed attributes of the model.
     *
     * @var array
     */
    protected $swappedAttributes = [];

    /**
     * Returns the value of the 'add_new_at' option.
     *
     * @return string
     */
    public function addNewAt()
    {
        return $this->getListifyConfig()->getAddNewItemTo();
    }

    /**
     * Decrease the position of this item without adjusting the rest of the list.
     *
     * @param int $count default 1
     *
     * @return $this
     */
    public function decrementPosition($count = 1)
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->setListifyPosition($this->getListifyPosition() - $count);

        return $this;
    }

    /**
     * Returns the Listify config. If it is new, a new instance will be instantiated.
     *
     * @return \Lookitsatravis\Listify\Config
     */
    public function getListifyConfig()
    {
        if ($this->listifyConfig === null) {
            $this->listifyConfig = new Config;
        }

        return $this->listifyConfig;
    }

    /**
     * Returns the value of the model's current position.
     *
     * @return int
     */
    public function getListifyPosition()
    {
        return $this->getAttribute($this->getPositionColumnName());
    }

    /**
     * Get the name of the position 'column' option.
     *
     * @return string
     */
    public function getPositionColumnName()
    {
        return $this->getListifyConfig()->getPositionColumnName();
    }

    /**
     * Return the next higher item in the list.
     *
     * @return null|static
     */
    public function higherItem()
    {
        if ($this->isNotInList()) {
            return;
        }

        return $this->listifyList()
            ->where($this->getPositionColumnName(), '<', $this->getListifyPosition())
            ->orderBy($this->getTable().'.'.$this->getPositionColumnName(), 'DESC')
            ->first();
    }

    /**
     * Return the next n higher items in the list. Selects all higher items by default.
     *
     * @param  null|int $limit The maximum number of items to return
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function higherItems($limit = null)
    {
        if ($limit === null) {
            $limit = $this->listifyList()->count();
        }

        $positionValue = $this->getListifyPosition();

        return $this->listifyList()
            ->where($this->getPositionColumnName(), '<', $positionValue)
            ->where($this->getPositionColumnName(), '>=', $positionValue - $limit)
            ->orderBy($this->getTable().'.'.$this->getPositionColumnName(), 'ASC')
            ->take($limit)
            ->get();
    }

    /**
     * Increase the position of this item without adjusting the rest of the list.
     *
     * @param int $count default 1
     *
     * @return $this
     */
    public function incrementPosition($count = 1)
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->setListifyPosition($this->getListifyPosition() + $count);

        return $this;
    }

    /**
     * Insert the item at the given position (defaults to the top position of 1).
     *
     * @param  int $position
     *
     * @return $this
     */
    public function insertAt($position = null)
    {
        if ($position === null) {
            $position = $this->listifyTopPositionInList();
        }

        $this->insertAtPosition($position);

        return $this;
    }

    /**
     * Returns if this object is the first in the list.
     *
     * @return bool
     */
    public function isFirst()
    {
        if ($this->isNotInList()) {
            return false;
        }

        return $this->getListifyPosition() == $this->listifyTopPositionInList();
    }

    /**
     * Returns whether the item is in the list.
     *
     * @return bool
     */
    public function isInList()
    {
        return $this->getListifyPosition() !== null;
    }

    /**
     * Returns if this object is the last in the list.
     *
     * @return bool
     */
    public function isLast()
    {
        if ($this->isNotInList()) {
            return false;
        }

        return $this->getListifyPosition() == $this->bottomPositionInList();
    }

    /**
     * Returns whether the item is not in the list.
     *
     * @return bool
     */
    public function isNotInList()
    {
        return ! $this->isInList();
    }

    /**
     * Get the value of the top of list option.
     *
     * @return string
     */
    public function listifyTopPositionInList()
    {
        return $this->getListifyConfig()->getTopPositionInList();
    }

    /**
     * Return the next lower item in the list.
     *
     * @return null|static
     */
    public function lowerItem()
    {
        if ($this->isNotInList()) {
            return;
        }

        return $this->listifyList()
            ->where($this->getPositionColumnName(), '>', $this->getListifyPosition())
            ->orderBy($this->getTable().'.'.$this->getPositionColumnName(), 'ASC')
            ->first();
    }

    /**
     * Return the next n lower items in the list. Selects all lower items by default.
     *
     * @param null|int $limit The maximum number of items to return
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function lowerItems($limit = null)
    {
        $query = $this->listifyList()
            ->where($this->getPositionColumnName(), '>', $this->getListifyPosition())
            ->orderBy($this->getTable().'.'.$this->getPositionColumnName(), 'ASC');

        if ($limit !== null) {
            $query->take($limit);
        }

        return $query->get();
    }

    /**
     * Swap positions with the next higher item, if one exists.
     *
     * @return $this
     */
    public function moveHigher()
    {
        if (! $this->higherItem()) {
            return $this;
        }

        $this->getConnection()->transaction(function (): void {
            $this->higherItem()->increment($this->getPositionColumnName());
            $this->decrement($this->getPositionColumnName());
        });

        return $this;
    }

    /**
     * Swap positions with the next lower item, if one exists.
     *
     * @return $this
     */
    public function moveLower()
    {
        if (! $this->lowerItem()) {
            return $this;
        }

        $this->getConnection()->transaction(function (): void {
            $this->lowerItem()->decrement($this->getPositionColumnName());
            $this->increment($this->getPositionColumnName());
        });

        return $this;
    }

    /**
     * Move to the bottom of the list. If the item is already in the list, the items below it have their positions adjusted accordingly.
     *
     * @return $this
     */
    public function moveToBottom()
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->getConnection()->transaction(function (): void {
            $this->decrementPositionsOnLowerItems();
            $this->setListPosition($this->bottomPositionInList($this) + 1);
        });

        return $this;
    }

    /**
     * Move to the top of the list. If the item is already in the list, the items above it have their positions adjusted accordingly.
     *
     * @return $this
     */
    public function moveToTop()
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->getConnection()->transaction(function (): void {
            $this->incrementPositionsOnHigherItems();
            $this->setListPosition($this->listifyTopPositionInList());
        });

        return $this;
    }

    /**
     * Removes the item from the list.
     *
     * @return $this
     */
    public function removeFromList()
    {
        if ($this->isInList()) {
            $this->decrementPositionsOnLowerItems();
            $this->setListPosition(null);
        }

        return $this;
    }

    /**
     * An Eloquent scope that returns only items currently in the list.
     *
     * @param $query
     *
     * @return Eloquent Query Builder instance
     */
    public function scopeInList($query)
    {
        return $query->listifyScope()->whereNotNull($this->getTable().'.'.$this->getPositionColumnName());
    }

    /**
     * An Eloquent scope based on the processed scope option.
     *
     * @param  $query An Eloquent Query Builder instance
     *
     * @return Eloquent Query Builder instance
     */
    public function scopeListifyScope($query)
    {
        return $query->whereRaw($this->scopeCondition());
    }

    /**
     * Get the value of the 'scope' option.
     *
     * @return mixed Can be a string, an Eloquent BelongsTo, or an Eloquent Builder
     */
    public function scopeName()
    {
        return $this->getListifyConfig()->getScope();
    }

    /**
     * Sets the value of the model's position.
     *
     * @param int $position
     *
     * @return void
     */
    public function setListifyPosition($position): void
    {
        $this->setAttribute($this->getPositionColumnName(), $position);
    }

    /**
     * Sets the new position and saves it.
     *
     * @param int|null $position null removes the item from the list
     *
     * @return bool
     */
    public function setListPosition($position = null)
    {
        $this->setListifyPosition($position);

        return $this->save();
    }

    /**
     * Returns whether the scope has changed during the course of interaction with the model.
     *
     * @return bool
     */
    public static function bootListify()
    {
        //Bind to model events
        static::deleting(function ($model): void {
            /* @var Listify $model */
            $model->reloadPosition();
        });

        static::deleted(function ($model): void {
            /* @var Listify $model */
            $model->decrementPositionsOnLowerItems();
        });

        static::updating(function ($model): void {
            /* @var Listify $model */
            $model->checkScope();
        });

        static::updated(function ($model): void {
            /* @var Listify $model */
            $model->updatePositions();
        });

        static::creating(function ($model): void {
            /* @var Listify $model */
            if ($model->addNewAt()) {
                $methodName = 'addToList'.$model->addNewAt();
                $model->{$methodName}();
            }
        });
    }

    /**
     * Adds item to the bottom of the list.
     *
     * @return void
     */
    protected function addToListBottom(): void
    {
        if ($this->isInList()) {
            return;
        }

        $this->setListifyPosition($this->bottomPositionInList() + 1);
    }

    /**
     * Adds item to the top of the list.
     *
     * @return void
     */
    protected function addToListTop(): void
    {
        if ($this->isInList()) {
            return;
        }

        $this->incrementPositionsOnAllItems();
        $this->setListifyPosition($this->listifyTopPositionInList());
    }

    /**
     * Returns the bottom position number in the list.
     *
     * @param  mixed $except An Eloquent model instance
     *
     * @return int
     */
    protected function bottomPositionInList($except = null)
    {
        $item = $this->getBottomItem($except);

        if ($item) {
            return $item->getListifyPosition();
        }

        return $this->listifyTopPositionInList() - 1;
    }

    /**
     * Determines whether scope has changed. If so, it will move the current item to the top/bottom of the list and update all other items.
     *
     * @return void
     */
    protected function checkScope(): void
    {
        if ($this->hasScopeChanged()) {
            $this->swapChangedAttributes();
            if ($this->lowerItem()) {
                $this->decrementPositionsOnLowerItems();
            }

            $this->swapChangedAttributes();
            // make this item "not in the list" so subsequent call to addToListBottom() works (b/c it only operates on items that have no position)
            $this->setListifyPosition(null);
            $methodName = 'addToList'.$this->addNewAt();
            $this->{$methodName}();
        }
    }

    /**
     * This has the effect of moving all the lower items up one.
     *
     * @param  int $position All items below the passed in position will be modified
     *
     * @return void
     */
    protected function decrementPositionsOnLowerItems($position = null): void
    {
        if ($this->isNotInList()) {
            return;
        }

        if ($position === null) {
            $position = $this->getListifyPosition();
        }

        $this->listifyList()
            ->where($this->getPositionColumnName(), '>', $position)
            ->decrement($this->getPositionColumnName());
    }

    /**
     * Returns the bottom item.
     *
     * @param  mixed $except An Eloquent model instance
     *
     * @return mixed Returns an item of the same type as the current class instance
     */
    protected function getBottomItem($except = null)
    {
        $conditions = $this->scopeCondition();

        if ($except !== null) {
            $conditions = $conditions.' AND '.$this->getPrimaryKey().' != '.$except->id;
        }

        return $this->listifyList()
            ->whereNotNull($this->getTable().'.'.$this->getPositionColumnName())
            ->whereRaw($conditions)
            ->orderBy($this->getTable().'.'.$this->getPositionColumnName(), 'DESC')
            ->take(1)
            ->first();
    }

    /**
     * Returns a raw WHERE clause based off of a Query Builder object.
     *
     * @param  $query A Query Builder instance
     *
     * @return string
     */
    protected function getConditionStringFromQueryBuilder($query)
    {
        $initialQueryChunks = explode('where ', $query->toSql());
        if (count($initialQueryChunks) == 1) {
            throw new InvalidQueryBuilderException('The Listify scope is a Query Builder object, but it has no "where", so it can\'t be used as a scope.');
        }

        $queryChunks = explode('?', $initialQueryChunks[1]);
        $bindings = $query->getBindings();

        $theQuery = '';

        for ($i = 0; $i < count($queryChunks); $i++) {
            // "boolean"
            // "integer"
            // "double" (for historical reasons "double" is returned in case of a float, and not simply "float")
            // "string"
            // "array"
            // "object"
            // "resource"
            // "NULL"
            // "unknown type"

            $theQuery .= $queryChunks[$i];
            if (isset($bindings[$i])) {
                switch (gettype($bindings[$i])) {
                    case 'string':
                        $theQuery .= '\''.$bindings[$i].'\'';

                        break;
                }
            }
        }

        return $theQuery;
    }

    /**
     * Returns the primary key of the current Eloquent instance.
     *
     * @return string
     */
    protected function getPrimaryKey()
    {
        return $this->getConnection()->getTablePrefix().$this->getQualifiedKeyName();
    }

    /**
     * Returns whether the scope has changed during the course of interaction with the model.
     *
     * @return bool
     */
    protected function hasScopeChanged()
    {
        $theScope = $this->scopeName();

        if (is_string($theScope)) {
            if (! $this->stringScopeValue) {
                $this->stringScopeValue = $theScope;

                return false;
            }

            return $theScope != $this->stringScopeValue;
        }

        $reflector = new \ReflectionClass($theScope);
        if ($reflector->getName() == 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
            $foreignKey = method_exists($theScope, 'getForeignKey') ? $theScope->getForeignKey() : $theScope->getForeignKeyName();
            $originalVal = $this->getOriginal()[$foreignKey];
            $currentVal = $this->getAttribute($foreignKey);

            if ($originalVal != $currentVal) {
                return true;
            }
        } elseif ($reflector->getName() == 'Illuminate\Database\Query\Builder') {
            if (! $this->stringScopeValue) {
                $this->stringScopeValue = $this->getConditionStringFromQueryBuilder($theScope);

                return false;
            }

            $theQuery = $this->getConditionStringFromQueryBuilder($theScope);
            if ($theQuery != $this->stringScopeValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Increments position of all items in the list.
     *
     * @return void
     */
    protected function incrementPositionsOnAllItems(): void
    {
        $this->listifyList()
            ->increment($this->getPositionColumnName());
    }

    /**
     * This has the effect of moving all the higher items down one.
     *
     * @return void
     */
    protected function incrementPositionsOnHigherItems(): void
    {
        if ($this->isNotInList()) {
            return;
        }

        $this->listifyList()
            ->where($this->getPositionColumnName(), '<', $this->getListifyPosition())
            ->increment($this->getPositionColumnName());
    }

    /**
     * This has the effect of moving all the lower items down one.
     *
     * @param  int $position All items below the passed in position will be modified
     *
     * @return void
     */
    protected function incrementPositionsOnLowerItems($position): void
    {
        $this->listifyList()
            ->where($this->getPositionColumnName(), '>=', $position)
            ->increment($this->getPositionColumnName());
    }

    /**
     * Inserts the item at a particular location in the list. All items around it will be modified.
     *
     * @param  int $position
     *
     * @return void
     */
    protected function insertAtPosition($position): void
    {
        if ($this->isInList()) {
            $oldPosition = $this->getListifyPosition();
            if ($position == $oldPosition) {
                return;
            }

            $this->shufflePositionsOnIntermediateItems($oldPosition, $position);
        } else {
            $this->incrementPositionsOnLowerItems($position);
        }

        $this->setListPosition($position);
    }

    /**
     * Creates an instance of the current class scope as a list.
     *
     * @return mixed
     */
    protected function listifyList()
    {
        $model = new self();
        $model->getListifyConfig()->setScope($this->scopeCondition());

        return $model->listifyScope();
    }

    /**
     * Reloads the position value of the current item. This is only called when an item is deleted and is here to prevent unsetting the position column which would prevent other items from being moved properly.
     *
     * @return void
     */
    protected function reloadPosition(): void
    {
        $this->setListifyPosition($this->getOriginal()[$this->getPositionColumnName()]);
    }

    /**
     * Returns the raw WHERE clause to be used as the Listify scope.
     *
     * @return string
     */
    protected function scopeCondition()
    {
        $theScope = $this->scopeName();

        if ($theScope === null) {
            throw new NullScopeException('You cannot pass in a null scope into Listify. It breaks stuff.');
        }

        if ($theScope !== $this->defaultScope) {
            if (is_string($theScope)) {
                // Good for you for being brave. Let's hope it'll run in your DB! You sanitized it, right?
                $this->stringScopeValue = $theScope;
            } else {
                if (is_object($theScope)) {
                    $reflector = new \ReflectionClass($theScope);

                    if ($reflector->getName() == 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                        $foreignKey = method_exists($theScope, 'getForeignKey') ? $theScope->getForeignKey() : $theScope->getForeignKeyName();
                        $relationshipId = $this->getAttribute($foreignKey);

                        if ($relationshipId === null) {
                            throw new NullForeignKeyException('The Listify scope is a "belongsTo" relationship, but the foreign key is null.');
                        }
                        $theScope = $foreignKey.' = '.$this->getAttribute($foreignKey);
                    } elseif ($reflector->getName() == 'Illuminate\Database\Query\Builder') {
                        $this->stringScopeValue = $theScope = $this->getConditionStringFromQueryBuilder($theScope);
                    } else {
                        throw new InvalidScopeException('Listify scope parameter must be a String, an Eloquent BelongsTo object, or a Query Builder object.');
                    }
                } else {
                    throw new InvalidScopeException('Listify scope parameter must be a String, an Eloquent BelongsTo object, or a Query Builder object.');
                }
            }
        }

        return $theScope;
    }

    /**
     * Reorders intermediate items to support moving an item from oldPosition to newPosition.
     *
     * @param  int $oldPosition
     * @param  int $newPosition
     * @param  string $avoidId You can pass in an ID of a record matching the current class and it will be ignored
     *
     * @return void
     */
    protected function shufflePositionsOnIntermediateItems($oldPosition, $newPosition, $avoidId = null): void
    {
        if ($oldPosition == $newPosition) {
            return;
        }

        $avoidIdCondition = $avoidId ? $this->getPrimaryKey().' != '.$avoidId : '1 = 1';

        if ($oldPosition < $newPosition) {
            // Decrement position of intermediate items

            // e.g., if moving an item from 2 to 5,
            // move [3, 4, 5] to [2, 3, 4]

            $this->listifyList()
                ->where($this->getPositionColumnName(), '>', $oldPosition)
                ->where($this->getPositionColumnName(), '<=', $newPosition)
                ->whereRaw($avoidIdCondition)
                ->decrement($this->getPositionColumnName());
        } else {
            // Increment position of intermediate items

            // e.g., if moving an item from 5 to 2,
            // move [2, 3, 4] to [3, 4, 5]

            $this->listifyList()
                ->where($this->getPositionColumnName(), '>=', $newPosition)
                ->where($this->getPositionColumnName(), '<', $oldPosition)
                ->whereRaw($avoidIdCondition)
                ->increment($this->getPositionColumnName());
        }
    }

    /**
     * Temporarily swap changes attributes with current attributes.
     *
     * @return void
     */
    protected function swapChangedAttributes(): void
    {
        if ($this->originalAttributesLoaded === false) {
            $this->swappedAttributes = $this->getAttributes();
            $this->fill($this->getOriginal());
            $this->originalAttributesLoaded = true;
        } else {
            if (count($this->swappedAttributes) == 0) {
                $this->swappedAttributes = $this->getAttributes();
            }

            $this->fill($this->swappedAttributes);
            $this->originalAttributesLoaded = false;
        }
    }

    /**
     * Updates all items based on the original position of the item and the new position of the item.
     *
     * @return void
     */
    protected function updatePositions(): void
    {
        $oldPosition = $this->getOriginal()[$this->getPositionColumnName()];
        $newPosition = $this->getListifyPosition();

        if ($newPosition === null) {
            $matchingPositionRecords = 0;
        } else {
            $matchingPositionRecords = $this->listifyList()->where($this->getPositionColumnName(), '=', $newPosition)->count();
        }

        if ($matchingPositionRecords <= 1) {
            return;
        }

        $this->shufflePositionsOnIntermediateItems($oldPosition, $newPosition, $this->id);
    }
}
