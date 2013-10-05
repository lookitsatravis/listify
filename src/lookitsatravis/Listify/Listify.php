<?php namespace lookitsatravis\Listify;

use DB, Event, Config, App;
use lookitsatravis\Listify\Exceptions\ListifyException;

/**
 * Gives some nice sorting features to a model.
 * 
 * Inspired by https://github.com/swanandp/acts_as_list
 * 
 * @package lookitsatravis/Listify
 * @version v1.0.0
 * @author Travis Vignon <travis@lookitsatravis.com>
 * @link    
 */

trait Listify
{
    private static $listifyConfig = [
        'top_of_list' => 1,
        'column' => 'position',
        'scope' => '1 = 1',
        'add_new_at' => 'bottom'
    ];

    private $deletedCallbackFired = FALSE;
    private static $defaultScope = '1 = 1';
    private static $originalAttributesLoaded = FALSE;
    private static $swappedAttributes = [];
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
    public function initListify($options = [])
    {
        //Update config with options
        static::$listifyConfig = array_replace(static::$listifyConfig, $options);

        //Get initial scope value
        $scope = $this->scopeCondition();

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

    public function scopeInList($query)
    {
        return $query->whereNotNull($this->getTable() . "." . $this->positionColumn());
    }

    public static function listifyTop()
    {
        return static::$listifyConfig['top_of_list'];
    }

    public function listifyClass()
    {
        return get_class($this);
    }

    public static function positionColumn()
    {
        return static::$listifyConfig['column'];
    }

    public static function scopeName()
    {
        return static::$listifyConfig['scope'];
    }

    public static function addNewAt()
    {
        return static::$listifyConfig['add_new_at'];
    }

    public function getListifyPosition()
    {
        return $this->getAttribute($this->positionColumn());
    }

    public function setListifyPosition($position)
    {
        $this->setAttribute($this->positionColumn(), $position);
    }

    // Insert the item at the given position (defaults to the top position of 1).
    public function insertAt($position = NULL)
    {
        if($position === NULL) $position = static::listifyTop();
        $this->insertAtPosition($position);
    }

    // Swap positions with the next lower item, if one exists.
    public function moveLower()
    {
        if(!$this->lowerItem()) return;
        
        DB::transaction(function()
        {
            $this->lowerItem()->decrementPosition();
            $this->incrementPosition();
        });
    }

    // Swap positions with the next higher item, if one exists.
    public function moveHigher()
    {
        if(!$this->higherItem()) return;

        DB::transaction(function()
        {
            $this->higherItem()->incrementPosition();
            $this->decrementPosition();
        });
    }

    // Move to the bottom of the list. If the item is already in the list, the items below it have their
    // position adjusted accordingly.
    public function moveToBottom()
    {
        if($this->isNotInList()) return NULL;

        DB::transaction(function()
        {
            $this->decrementPositionsOnLowerItems();
            $this->assumeBottomPosition();
        });
    }

    // Move to the top of the list. If the item is already in the list, the items above it have their
    // position adjusted accordingly.
    public function moveToTop()
    {
        if($this->isNotInList()) return NULL;

        DB::transaction(function()
        {
            $this->incrementPositionsOnHigherItems();
            $this->assumeTopPosition();
        });
    }

    // Removes the item from the list.
    public function removeFromList()
    {
        if($this->isInList())
        {
            $this->decrementPositionsOnLowerItems();
            $this->setListPosition(NULL);
        }
    }

    // THIS IS NOT ACTUALLY IN USE IN acts_as_list
    // Move the item within scope
    // public function moveWithinScope($scope_id)
    // {
    //  // send("#{scope_name}=", scope_id)
    //  //      save!
    //  $this->save();
    // }

    // Increase the position of this item without adjusting the rest of the list.
    public function incrementPosition()
    {
        if($this->isNotInList()) return NULL;
        $this->setListPosition($this->getListifyPosition() + 1);
    }

    // Decrease the position of this item without adjusting the rest of the list.
    public function decrementPosition()
    {
        if($this->isNotInList()) return NULL;
        $this->setListPosition($this->getListifyPosition() - 1);
    }

    // Return +true+ if this object is the first in the list.
    public function isFirst()
    {
        if($this->isNotInList()) return FALSE;
        if($this->getListifyPosition() == static::listifyTop()) return TRUE;
        return FALSE;
    }

    // Return +true+ if this object is the last in the list.
    public function isLast()
    {
        if($this->isNotInList()) return FALSE;
        if($this->getListifyPosition() == $this->bottomPositionInList()) return TRUE;
        return FALSE;
    }

    // Return the next higher item in the list.
    public function higherItem()
    {
        if($this->isNotInList()) return NULL;

        return App::make($this->listifyClass())
            ->whereRaw($this->scopeCondition() . " AND " . $this->positionColumn() . " < " . $this->getListifyPosition())
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "DESC")
            ->first();
    }

    // Return the next n higher items in the list
    //    selects all higher items by default
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

    // Return the next lower item in the list.
    public function lowerItem()
    {
        if($this->isNotInList()) return NULL;

        return App::make($this->listifyClass())
            ->whereRaw($this->scopeCondition() . " AND " . $this->positionColumn() . " > " . $this->getListifyPosition())
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "ASC")
            ->first();
    }

    // Return the next n lower items in the list
    //    selects all lower items by default
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

    public function isInList()
    {
        return !$this->isNotInList();
    }

    public function isNotInList()
    {
        return $this->getListifyPosition() === NULL;
    }

    public function defaultPosition()
    {
        return NULL;
    }

    public function isDefaultPosition()
    {
        return $this->defaultPosition() == $this->getListifyPosition();
    }

    // Sets the new position and saves it
    public function setListPosition($new_position)
    {
        $this->setListifyPosition($new_position);
        $this->save();
    }


 //    /* Private Methods */

    private function listifyList()
    {
        return App::make($this->listifyClass())
            ->whereRaw($this->scopeCondition());
    }

    private function addToListTop()
    {
        $this->incrementPositionsOnAllItems();
        $this->setListifyPosition(static::listifyTop());
    }

    private function addToListBottom()
    {
        if($this->isNotInList() || $this->isDefaultPosition())
            $this->setListifyPosition($this->bottomPositionInList() + 1);
        else
            $this->incrementPositionsOnLowerItems($this->getListifyPosition());
    }

    // Returns the bottom position number in the list.
    //   bottomPositionInList    # => 2
    private function bottomPositionInList($except = NULL)
    {
        $item = $this->bottomItem($except);

        if($item)
            return $item->getListifyPosition();
        else
            return static::listifyTop() - 1;
    }

    // Returns the bottom item
    private function bottomItem($except = NULL)
    {
        $conditions = $this->scopeCondition();
        if($except !== NULL)
        {
            $conditions = $conditions . " AND " . $this->primaryKey() . " != " . $except->id;
        }

        return App::make($this->listifyClass())
            ->whereNotNull($this->getTable() . "." . $this->positionColumn())
            ->whereRaw($conditions)
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "DESC")
            ->first();
    }

    private function primaryKey()
    {
        return $this->getQualifiedKeyName();
    }

    // Forces item to assume the bottom position in the list.
    private function assumeBottomPosition()
    {
        $this->setListPosition($this->bottomPositionInList($this) + 1);
    }

    // Forces item to assume the top position in the list.
    private function assumeTopPosition()
    {
        $this->setListPosition(static::listifyTop());
    }

    // This has the effect of moving all the higher items up one.
    private function decrementPositionsOnHigherItems($position)
    {
        DB::table($this->getTable())
           ->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' <= ' . $position)
           ->decrement($this->positionColumn());
    }

    // This has the effect of moving all the lower items up one.
    private function decrementPositionsOnLowerItems($position = NULL)
    {
        if($this->isNotInList()) return NULL;
        if($position === NULL) $position = $this->getListifyPosition();

        DB::table($this->getTable())
           ->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' > ' . $position)
           ->decrement($this->positionColumn());
    }

    // This has the effect of moving all the higher items down one.
    private function incrementPositionsOnHigherItems()
    {
        if($this->isNotInList()) return NULL;

        DB::table($this->getTable())
           ->whereRaw($this->scopeCondition() . " AND " . $this->positionColumn() . ' < ' . $this->getListifyPosition())
           ->increment($this->positionColumn());
    }

    //This has the effect of moving all the lower items down one.
    private function incrementPositionsOnLowerItems($position)
    {
        DB::table($this->getTable())
            ->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' >= ' . $position)
            ->increment($this->positionColumn());
    }

    // Increments position (<tt>position_column</tt>) of all items in the list.
    private function incrementPositionsOnAllItems()
    {
        DB::table($this->getTable())
            ->whereRaw($this->scopeCondition())
            ->increment($this->positionColumn());
    }

    // Reorders intermediate items to support moving an item from old_position to new_position.
    private function shufflePositionsOnIntermediateItems($old_position, $new_position, $avoid_id = NULL)
    {
        if($old_position == $new_position) return;
        $avoid_id_condition = $avoid_id ? " AND " . $this->primaryKey() . " != " . $avoid_id : '';
        
        if($old_position < $new_position)
        {
            // Decrement position of intermediate items

            // e.g., if moving an item from 2 to 5,
            // move [3, 4, 5] to [2, 3, 4]

            DB::table($this->getTable())
                ->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' > ' . $old_position)
                ->whereRaw($this->positionColumn() . ' <= ' . $new_position . $avoid_id_condition)
                ->decrement($this->positionColumn());
        }
        else
        {
            // Increment position of intermediate items

            // e.g., if moving an item from 5 to 2,
            // move [2, 3, 4] to [3, 4, 5]

            DB::table($this->getTable())
                ->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' >= ' . $new_position)
                ->whereRaw($this->positionColumn() . ' < ' . $old_position . $avoid_id_condition)
                ->increment($this->positionColumn());
        }
    }

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

    // used by insert_at_position instead of remove_from_list, as postgresql raises error if position_column has non-null constraint
    private function storeAt0()
    {
        if($this->isInList())
        {
            $old_position = $this->getListifyPosition();
            $this->setListPosition(0);
            $this->decrementPositionsOnLowerItems($old_position);
        }
    }

    private function updatePositions()
    {
        $old_position = $this->getOriginal()['position'];
        $new_position = $this->getListifyPosition();

        if($new_position === NULL)
            $matching_position_records = 0;
        else
            $matching_position_records = DB::table($this->getTable())->whereRaw($this->scopeCondition() . ' AND ' . $this->positionColumn() . ' = ' . $new_position)->count();

        if($matching_position_records <= 1)
        {
            return;
        }

        $this->shufflePositionsOnIntermediateItems($old_position, $new_position, $this->id);
    }

    // Temporarily swap changes attributes with current attributes
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

    private function reloadPosition()
    {
        //Perhaps don't ned this for this port
    }
}