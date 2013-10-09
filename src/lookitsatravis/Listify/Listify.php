<?php namespace lookitsatravis\Listify;

use DB, Event, Config, App;
use lookitsatravis\Listify\Exceptions\ListifyException;

/**
 * Gives some nice sorting features to a model.
 * 
 * Ported from https://github.com/swanandp/acts_as_list
 * 
 * @package lookitsatravis/Listify
 * @version 0.1.0
 * @author Travis Vignon <travis@lookitsatravis.com>
 * @link    
 */

trait Listify
{
    /**
     * Array of current config values
     * @var array
     */
    private static $listifyConfig = [
        'top_of_list' => 1,
        'column' => 'position',
        'scope' => '1 = 1',
        'add_new_at' => 'bottom'
    ];

    /**
     * Contains whether the deleted Model Event has already fired
     * @var boolean
     */
    private $deletedCallbackFired = FALSE;

    /**
     * Default scope of the list
     * @var string
     */
    private static $defaultScope = '1 = 1';

    /**
     * Contains whether the original attributes are loaded on the model or not
     * @var boolean
     */
    private static $originalAttributesLoaded = FALSE;

    /**
     * Container for the changed attributes of the model
     * @var [type]
     */
    private static $swappedAttributes = [];

    /**
     * Contains the current raw scope string. Used to check for changes.
     * @var string
     */
    private static $builderQueryString = '';

    // Configuration options are:
    // * +column+ - specifies the column name to use for keeping the position integer (default: +position+)
    // * +scope+ - restricts what is to be considered a list. Given a symbol, it'll attach <tt>_id</tt>
    // (if it hasn't already been added) and use that as the foreign key restriction. It's also possible
    // to give it an entire string that is interpolated if you need a tighter scope than just a foreign key.
    // Example: <tt>acts_as_list scope: 'todo_list_id = #{todo_list_id} AND completed = 0'</tt>
    // * +top_of_list+ - defines the integer used for the top of the list. Defaults to 1. Use 0 to make the collection
    // act more like an array in its indexing.
    // * +add_new_at+ - specifies whether objects get added to the :top or :bottom of the list. (default: +bottom+)
    //                 `nil` will result in new items not being added to the list on create
    //                 
    
    /**
     * Required to override options and kick off the Listify's automatic list management.
     * @param  array $options [column=>string, scope=>string|BelongsTo|Builder, top_of_list=>int, add_new_at=>string]
     * @return void
     */
    public function initListify($options = [])
    {
        //Update config with options
        static::$listifyConfig = array_replace(static::$listifyConfig, $options);

        //Get initial scope value
        $scope = $this->listifyScope();

        //Bind to model events
        $this::deleting(function($model)
        {
            $model->reloadPosition();
        });

        $this::deleted(function($model)
        {
            //For whatever reason, this event is fired three times on a single delete during dev. So, there's this.
            if($model->deletedCallbackFired === FALSE)
            {
                $model->decrementPositionsOnLowerItems();
                $model->deletedCallbackFired = TRUE;
            }
        });

        $this::updating(function($model)
        {
            $model->checkScope();
        });

        $this::updated(function($model)
        {
            $model->updatePositions();
        });

        if($this::addNewAt() != NULL)
        {
            $this::creating(function($model)
            {
                $method_name = "addToList" . $model::addNewAt();
                $model->$method_name();
            });
        }
    }

    /**
     * Returns whether the scope has changed during the course of interaction with the model
     * @return boolean
     */
    private function hasScopeChanged()
    { 
        $theScope = static::scopeName();

        if(is_string($theScope)) return FALSE;

        $reflector = new \ReflectionClass($theScope);
        if($reflector->getName() == 'Illuminate\Database\Eloquent\Relations\BelongsTo')
        {
            $originalVal = $theScope->getOriginal($theScope->getForeignKey());
            $currentVal = $theScope->getAttribute($theScope->getForeignKey());

            if($originalVal != $currentVal) return TRUE;    
        }
        else if ($reflector->getName() == 'Illuminate\Database\Eloquent\Builder')
        {
            $theQuery = $this->getConditionStringFromQueryBuilder($theScope);
            if($theQuery != static::$builderQueryString) return TRUE;
        }
        
        return FALSE;
    }

    /**
     * Returns the raw WHERE clause to be used as the Listify scope
     * @return string
     */
    private function scopeCondition()
    {
        $theScope = static::scopeName();

        if($theScope != static::$defaultScope)
        {
            if(is_string($theScope))
            {
                //Good for you for being brave. Let's hope it'll run in your DB! You sanitized it, right?
            }
            else
            {
                $reflector = new \ReflectionClass($theScope);
                if($reflector->getName() == 'Illuminate\Database\Eloquent\Relations\BelongsTo')
                {
                    $relationshipId = $this->getAttribute($theScope->getForeignKey());

                    if($relationshipId === NULL)
                    {
                        throw new ListifyException('The Listify scope is a "belongsTo" relationship, but the foreign key is null.');
                    }
                    else
                    {
                        $theScope = $theScope->getForeignKey() . ' = ' . $this->getAttribute($theScope->getForeignKey());       
                    }
                }
                else if ($reflector->getName() == 'Illuminate\Database\Eloquent\Builder')
                {
                    $theQuery = $this->getConditionStringFromQueryBuilder($theScope);
                    static::$builderQueryString = $theQuery;
                    $theScope = $theQuery;
                }
                else
                {
                    throw new ListifyException('Listify scope parameter must be a String, an Eloquent BelongsTo object, or an Eloquent Query Builder object.');
                }
            }
        }

        return $theScope;
    }

    /**
     * Returns a raw WHERE clause based off of an Eloquent Query Builder object
     * @param  $query An Eloquent Query Builder instance
     * @return string
     */
    private function getConditionStringFromQueryBuilder($query)
    {
        $initialQueryChunks = explode('where ', $query->getQuery()->toSql());
        if($initialQueryChunks == 1) throw new ListifyException('The Listify scope is an Eloquent Query Builder object, but it has no "where", so it can\'t be used as a scope.');
        $queryChunks = explode('?', $initialQueryChunks[1]);
        $bindings = $query->getQuery()->getBindings();

        $theQuery = '';

        for($i = 0; $i < count($queryChunks); $i++)
        {
            $theQuery .= $queryChunks[$i];
            if(isset($bindings[$i])) $theQuery .= $bindings[$i];
        }

        return $theQuery;
    }

    /**
     * An Eloquent scope based on the processed scope option 
     * @param  $query An Eloquent Query Builder instance
     * @return Eloquent Query Builder instance
     */
    public function scopeListifyScope($query)
    {
        return $query->whereRaw($this->scopeCondition());
    }

    /**
     * An Eloquent scope that returns only items currently in the list
     * @param $query 
     * @return Eloquent Query Builder instance
     */
    public function scopeInList($query)
    {
        return $query->whereNotNull($this->getTable() . "." . $this->positionColumn());
    }

    /**
     * Get the value of the "top_of_list" option
     * @return string
     */
    public static function listifyTop()
    {
        return static::$listifyConfig['top_of_list'];
    }

    /**
     * Get the name of the current model instance
     * @return string
     */
    public function listifyClass()
    {
        return get_class($this);
    }

    /**
     * Get the name of the position 'column' option
     * @return string
     */
    public static function positionColumn()
    {
        return static::$listifyConfig['column'];
    }

    /**
     * Get the value of the 'scope' option
     * @return mixed Can be a string, an Eloquent BelongsTo, or an Eloquent Builder
     */
    public static function scopeName()
    {
        return static::$listifyConfig['scope'];
    }

    /**
     * Returns the value of the 'add_new_at' option
     * @return string
     */
    public static function addNewAt()
    {
        return static::$listifyConfig['add_new_at'];
    }

    /**
     * Returns the value of the model's current position
     * @return int
     */
    public function getListifyPosition()
    {
        return $this->getAttribute($this->positionColumn());
    }

    /**
     * Sets the value of the model's position
     * @param int $position 
     * @return void
     */
    public function setListifyPosition($position)
    {
        $this->setAttribute($this->positionColumn(), $position);
    }
 
    /**
     * Insert the item at the given position (defaults to the top position of 1).
     * @param  int $position
     * @return void
     */
    public function insertAt($position = NULL)
    {
        if($position === NULL) $position = static::listifyTop();
        $this->insertAtPosition($position);
    }

    /**
     * Swap positions with the next lower item, if one exists.
     * @return void
     */
    public function moveLower()
    {
        if(!$this->lowerItem()) return;
        
        DB::transaction(function()
        {
            $this->lowerItem()->decrementPosition();
            $this->incrementPosition();
        });
    }

    /**
     * Swap positions with the next higher item, if one exists.
     * @return void
     */
    public function moveHigher()
    {
        if(!$this->higherItem()) return;

        DB::transaction(function()
        {
            $this->higherItem()->incrementPosition();
            $this->decrementPosition();
        });
    }

    /**
     * Move to the bottom of the list. If the item is already in the list, the items below it have their positions adjusted accordingly.
     * @return void
     */
    public function moveToBottom()
    {
        if($this->isNotInList()) return NULL;

        DB::transaction(function()
        {
            $this->decrementPositionsOnLowerItems();
            $this->assumeBottomPosition();
        });
    }

    /**
     * Move to the top of the list. If the item is already in the list, the items above it have their positions adjusted accordingly.
     * @return void
     */
    public function moveToTop()
    {
        if($this->isNotInList()) return NULL;

        DB::transaction(function()
        {
            $this->incrementPositionsOnHigherItems();
            $this->assumeTopPosition();
        });
    }

    /**
     * Removes the item from the list.
     * @return void
     */
    public function removeFromList()
    {
        if($this->isInList())
        {
            $this->decrementPositionsOnLowerItems();
            $this->setListPosition(NULL);
        }
    }

    /**
     * Increase the position of this item without adjusting the rest of the list.
     * @return void
     */
    public function incrementPosition()
    {
        if($this->isNotInList()) return NULL;
        $this->setListPosition($this->getListifyPosition() + 1);
    }

    /**
     * Decrease the position of this item without adjusting the rest of the list.
     * @return void
     */
    public function decrementPosition()
    {
        if($this->isNotInList()) return NULL;
        $this->setListPosition($this->getListifyPosition() - 1);
    }

    /**
     * Returns if this object is the first in the list.
     * @return boolean
     */
    public function isFirst()
    {
        if($this->isNotInList()) return FALSE;
        if($this->getListifyPosition() == static::listifyTop()) return TRUE;
        return FALSE;
    }

    /**
     * Returns if this object is the last in the list.
     * @return boolean
     */
    public function isLast()
    {
        if($this->isNotInList()) return FALSE;
        if($this->getListifyPosition() == $this->bottomPositionInList()) return TRUE;
        return FALSE;
    }

    /**
     * Return the next higher item in the list.
     * @return mixed Returned item will be of the same type as the current class instance
     */
    public function higherItem()
    {
        if($this->isNotInList()) return NULL;

        return $this->listifyList()
            ->where($this->positionColumn(), "<", $this->getListifyPosition())
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "DESC")
            ->first();
    }

    /**
     * Return the next n higher items in the list. Selects all higher items by default
     * @param  int $limit The number of items to return
     * @return mixed Returned items will be of the same type as the current class instance
     */
    public function higherItems($limit = NULL)
    {
        if($limit === NULL) $limit = $this->listifyList()->count();
        $position_value = $this->getListifyPosition();

        return $this->listifyList()
            ->where($this->positionColumn(), "<", $position_value)
            ->where($this->positionColumn(), ">=", $position_value - $limit)
            ->take($limit)
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "ASC")
            ->get();
    }

    /**
     * Return the next lower item in the list.
     * @return mixed Returned item will be of the same type as the current class instance
     */
    public function lowerItem()
    {
        if($this->isNotInList()) return NULL;

        return $this->listifyList()
            ->where($this->positionColumn(), ">", $this->getListifyPosition())
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "ASC")
            ->first();
    }

    /**
     * Return the next n lower items in the list. Selects all lower items by default.
     * @param int $limit The number of items to return
     * @return mixed Returned items will be of the same type as the current class instance
     */
    public function lowerItems($limit = NULL)
    {
        if($limit === NULL) $limit = $this->listifyList()->count();
        $position_value = $this->getListifyPosition();

        return $this->listifyList()
            ->where($this->positionColumn(), '>', $position_value)
            ->where($this->positionColumn(), '<=', $position_value + $limit)
            ->take($limit)
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "ASC")
            ->get();
    }

    /**
     * Returns whether the item is in the list
     * @return boolean
     */
    public function isInList()
    {
        return !$this->isNotInList();
    }

    /**
     * Returns whether the item is not in the list
     * @return boolean
     */
    public function isNotInList()
    {
        return $this->getListifyPosition() === NULL;
    }

    /**
     * Get the default item position
     * @return mixed
     */
    public function defaultPosition()
    {
        return NULL;
    }

    /**
     * Returns whether the item's current position matches the default position
     * @return boolean
     */
    public function isDefaultPosition()
    {
        return $this->defaultPosition() == $this->getListifyPosition();
    }

    /**
     * Sets the new position and saves it
     * @param int $new_position 
     * @return void
     */
    public function setListPosition($new_position)
    {
        $this->setListifyPosition($new_position);
        $this->save();
    }

    /* Private Methods */

    /**
     * Creates an instance of the current class scope as a list
     * @return mixed
     */
    private function listifyList()
    {
        return App::make($this->listifyClass())
            ->listifyScope();
    }

    /**
     * Adds item to the top of the list
     * @return void
     */
    private function addToListTop()
    {
        $this->incrementPositionsOnAllItems();
        $this->setListifyPosition(static::listifyTop());
    }

    /**
     * Adds item to the bottom of the list
     * @return void
     */
    private function addToListBottom()
    {
        if($this->isNotInList() || $this->isDefaultPosition())
            $this->setListifyPosition($this->bottomPositionInList() + 1);
        else
            $this->incrementPositionsOnLowerItems($this->getListifyPosition());
    }

    /**
     * Returns the bottom position number in the list
     * @param  mixed $except An Eloquent model instance
     * @return int
     */
    private function bottomPositionInList($except = NULL)
    {
        $item = $this->bottomItem($except);

        if($item)
            return $item->getListifyPosition();
        else
            return static::listifyTop() - 1;
    }

    /**
     * Returns the bottom item
     * @param  mixed $except An Eloquent model instance
     * @return mixed Returns an item of the same type as the current class instance
     */
    private function bottomItem($except = NULL)
    {
        $conditions = $this->scopeCondition();
        if($except !== NULL)
        {
            $conditions = $conditions . " AND " . $this->primaryKey() . " != " . $except->id;
        }

        return $this->listifyList()
            ->whereNotNull($this->getTable() . "." . $this->positionColumn())
            ->whereRaw($conditions)
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "DESC")
            ->first();
    }

    /**
     * Returns the primary key of the current Eloquent instance
     * @return string
     */
    private function primaryKey()
    {
        return $this->getQualifiedKeyName();
    }

    /**
     * Forces item to assume the bottom position in the list.
     * @return void
     */
    private function assumeBottomPosition()
    {
        $this->setListPosition($this->bottomPositionInList($this) + 1);
    }

    /**
     * Forces item to assume the top position in the list.
     * @return void
     */
    private function assumeTopPosition()
    {
        $this->setListPosition(static::listifyTop());
    }

    /**
     * This has the effect of moving all the higher items up one.
     * @param  int $position All items above the passed in position will be modified
     * @return void
     */
    private function decrementPositionsOnHigherItems($position)
    {
        $this->listifyList()
           ->where($this->positionColumn(), '<=', $position)
           ->decrement($this->positionColumn());
    }

    /**
     * This has the effect of moving all the lower items up one.
     * @param  int $position All items below the passed in position will be modified
     * @return void
     */
    private function decrementPositionsOnLowerItems($position = NULL)
    {
        if($this->isNotInList()) return NULL;
        if($position === NULL) $position = $this->getListifyPosition();

        $this->listifyList()
           ->where($this->positionColumn(), '>', $position)
           ->decrement($this->positionColumn());
    }

    /**
     * This has the effect of moving all the higher items down one.
     * @return void
     */
    private function incrementPositionsOnHigherItems()
    {
        if($this->isNotInList()) return NULL;

        $this->listifyList()
           ->where($this->positionColumn(), '<', $this->getListifyPosition())
           ->increment($this->positionColumn());
    }

    /**
     * This has the effect of moving all the lower items down one.
     * @param  int $position All items below the passed in position will be modified
     * @return void
     */
    private function incrementPositionsOnLowerItems($position)
    {
        $this->listifyList()
            ->where($this->positionColumn(), '>=', $position)
            ->increment($this->positionColumn());
    }

    /**
     * Increments position of all items in the list.
     * @return void
     */
    private function incrementPositionsOnAllItems()
    {
        $this->listifyList()
            ->increment($this->positionColumn());
    }

    /**
     * Reorders intermediate items to support moving an item from old_position to new_position.
     * @param  int $old_position
     * @param  int $new_position
     * @param  string $avoid_id     You can pass in an ID of a record matching the current class and it will be ignored
     * @return void
     */
    private function shufflePositionsOnIntermediateItems($old_position, $new_position, $avoid_id = NULL)
    {
        if($old_position == $new_position) return;
        $avoid_id_condition = $avoid_id ? $this->primaryKey() . " != " . $avoid_id : '';
        
        if($old_position < $new_position)
        {
            // Decrement position of intermediate items

            // e.g., if moving an item from 2 to 5,
            // move [3, 4, 5] to [2, 3, 4]

            $this->listifyList()
                ->where($this->positionColumn(), '>', $old_position)
                ->where($this->positionColumn(), '<=', $new_position)
                ->whereRaw($avoid_id_condition)
                ->decrement($this->positionColumn());
        }
        else
        {
            // Increment position of intermediate items

            // e.g., if moving an item from 5 to 2,
            // move [2, 3, 4] to [3, 4, 5]

            $this->listifyList()
                ->where($this->positionColumn(), '>=', $new_position)
                ->where($this->positionColumn(), '<', $old_position)
                ->whereRaw($avoid_id_condition)
                ->increment($this->positionColumn());
        }
    }

    /**
     * Inserts the item at a particular location in the list. All items around it will be modified
     * @param  int $position
     * @return void
     */
    private function insertAtPosition($position)
    {
        if($this->isInList())
        {
            $old_position = $this->getListifyPosition();
            if($position == $old_position) return;

            $this->shufflePositionsOnIntermediateItems($old_position, $position);
        }
        else
        {
            $this->incrementPositionsOnLowerItems($position);
        }

        $this->setListPosition($position);
    }

    /**
     * Updates all items based on the original position of the item and the new position of the item
     * @return void
     */
    private function updatePositions()
    {
        $old_position = $this->getOriginal()['position'];
        $new_position = $this->getListifyPosition();

        if($new_position === NULL)
            $matching_position_records = 0;
        else
            $matching_position_records = $this->listifyList()->where($this->positionColumn(), '=', $new_position)->count();

        if($matching_position_records <= 1)
        {
            return;
        }

        $this->shufflePositionsOnIntermediateItems($old_position, $new_position, $this->id);
    }

    /**
     * Temporarily swap changes attributes with current attributes
     * @return void
     */
    public function swapChangedAttributes()
    {
        if(static::$originalAttributesLoaded === FALSE)
        {
            static::$swappedAttributes = $this->getAttributes();
            $this->fill($this->getOriginal());
            static::$originalAttributesLoaded = TRUE;
        }
        else
        {
            if(count(static::$swappedAttributes) == 0) static::$swappedAttributes = $this->getAttributes();
            $this->fill(static::$swappedAttributes);
            static::$originalAttributesLoaded = FALSE;
        }
    }

    /**
     * Determines whether scope has changed. If so, it will move the current item to the top/bottom of the list and update all other items
     * @return void
     */
    private function checkScope()
    {
        if($this->hasScopeChanged())
        {
            $this->swapChangedAttributes();
            if($this->lowerItem()) $this->decrementPositionsOnLowerItems();
            $this->swapChangedAttributes();
            $method_name = "addToList" . $this->addNewAt();
            $this->$method_name();
        }
    }

    /**
     * Reloads the position value of the current item.
     * @return void
     */
    private function reloadPosition()
    {
        //Perhaps don't ned this for this port
    }
}