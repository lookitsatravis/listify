<?php namespace lookitsatravis\Listify;

use Event, Config, App;

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
    private var $listifyConfig = {
    	'top_of_list' => 1,
    	'column' => 'position',
    	'scope' => '1 = 1',
    	'add_new_at' => 'bottom'
    };

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
    public function initListify($options = {})
    {
    	//Update config with options

     //      configuration[:scope] = "#{configuration[:scope]}_id".intern if configuration[:scope].is_a?(Symbol) && configuration[:scope].to_s !~ /_id$/

		$this->destroying(function($model))
		{
			$this->reloadPosition();
		}

		$this->destroyed(function($model))
		{
			$this->decrementPositionsOnLowerItems();
		}

		$this->updating(function($model))
		{
			$this->checkScope();
		}

		$this->updated(function($model))
		{
			$this->updatePositions();
		}

      	if($this->addNewAt() != NULL)
      	{
      		$this->creating(function($model))
      		{
      			$method_name = "addToList" . $this->addNewAt();
      			$this->$method_name();
      		}
      	}
    }

    private function hasScopeChanged()
    {
    	// if configuration[:scope].is_a?(Symbol)
    	// 	def scope_changed?
     // //            changes.include?(scope_name.to_s)
     // //          end

    	// elsif configuration[:scope].is_a?(Array)
    	// 	def scope_changed?
     // //            (attrs.keys & changes.keys.map(&:to_sym)).any?
     // //          end
    	// else

    	return FALSE;
    }

        // Overwrite this method to define the scope of the list changes
    private function scopeCondition()
    {
    	if configuration[:scope].is_a?(Symbol)
     //        scope_methods = %(
     //          def scope_condition
     //            self.class.send(:sanitize_sql_hash_for_conditions, { :#{configuration[:scope].to_s} => send(:#{configuration[:scope].to_s}) })
     //          end

    	elsif configuration[:scope].is_a?(Array)
    	def scope_condition
     //            self.class.send(:sanitize_sql_hash_for_conditions, attrs)
     //          end
     	else	
     		def scope_condition
     //            "#{configuration[:scope]}"
     //          end

    	return "1";
    }

    public function scopeInList($query)
    {
    	return $query->where($this->table() . "." . $this->positionColumn() . " IS NOT NULL");
    }

    public function listifyTop()
    {
    	return $this->listifyConfig['top_of_list'];
    }

    public function listifyClass()
    {
    	return get_class($this);
    }

    public function positionColumn()
    {
    	return $this->listifyConfig['column'];
    }

    public function scopeName()
    {
    	return $this->listifyConfig['scope'];
    }

    public function addNewAt()
    {
    	return $this->listifyConfig['add_new_at'];
    }

    # Insert the item at the given position (defaults to the top position of 1).
    public function insertAt($position = $this->listifyTop())
    {
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
    // 	// send("#{scope_name}=", scope_id)
    //  //      save!
    // 	$this->save();
    // }

    // Increase the position of this item without adjusting the rest of the list.
    public function incrementPosition()
    {
    	if($this->isNotInList()) return NULL;
    	$this->setListPosition($this->position + 1);
    }

    // Decrease the position of this item without adjusting the rest of the list.
    public function decrementPosition()
    {
    	if($this->isNotInList()) return NULL;
    	$this->setListPosition($this->position - 1);
    }

    // Return +true+ if this object is the first in the list.
    public function isFirst()
    {
    	if($this->isNotInList()) return FALSE;
    	if($this->position == $this->listifyTop()) return TRUE;
    	return FALSE;
    }

    // Return +true+ if this object is the last in the list.
    public function isLast()
    {
    	if($this->isNotInList()) return FALSE;
    	if($this->position == $this->bottomPositionInList()) return TRUE;
    	return FALSE;
    }

    // Return the next higher item in the list.
    public function higherItem()
    {
    	if($this->isNotInList()) return NULL;

    	$model = App::make($this->listifyClass());
    	return $model->where($this->scopeCondition() . " AND " . $this->positionColumn(), "<", $this->position)->orderBy($this->table() . "." . $this->positionColumn . " DESC")->first();
    }

    // Return the next n higher items in the list
    //    selects all higher items by default
    public function higherItems($limit = NULL)
    {
    	if($limit == NULL) $limit = $this->listifyList()->count();
    	$position_value = $this->position;

    	return $this->listifyList()->where($this->positionColumn(), "<", $position_value)->
    		where($this->positionColumn(), ">=", $position_value - $limit)->
    		take($limit)->
    		orderBy($this->table() . "." . $this->positionColumn() . " ASC");
    }

    // Return the next lower item in the list.
    public function lowerItem()
    {
    	if($this->isNotInList()) return NULL;

    	$model = App::make($this->listifyClass());
    	return $model->where($this->scopeCondition() . " AND " . $this->positionColumn(), ">", $this->position)->
    		orderBy($this->table() . "." . $this->positionColumn() . " ASC");
    }

    // Return the next n lower items in the list
    //    selects all lower items by default
    public function lowerItems($limit = NULL)
    {
    	if($limit == NULL) $limit = $this->listifyList()->count();
    	$position_value = $this->position;

    	return $this->listifyList()->where($this->positionColumn(), '>', $position_value)->
    		where($this->positionColumn(), '<=', $position_value + $limit)->
    		take($limit)->
    		orderBy($this->table() . "." . $this->positionColumn() . " ASC");
    }

    public function isInList()
    {
    	return !$this->isNotInList();
    }

    public function isNotInList()
    {
    	return $this->position == NULL;
    }

    public function defaultPosition()
    {
    	return NULL;
    }

    public function isDefaultPosition()
    {
    	return $this->defaultPosition() == $this->position;
    }

    // Sets the new position and saves it
    public function setListPosition($new_position)
    {
    	$this->position = $new_position;
    	$this->save();
    }


    /* Private Methods */

    private function listifyList()
    {
    	$model = App::make($this->listifyClass());
    	return $model->where($this->scopeCondition());
    }

    private function addToListTop()
    {
    	$this->incrementPositionOnAllItems();
    	$this->position = $this->listifyTop();
    }

    private function addToListBottom()
    {
    	if($this->isNotInList() || $this->isDefaultPosition())
    		$this->position = $this->bottomPositionInList() + 1;
    	else
    		$this->incrementPositionsOnLowerItems($this->position);
    }

	// Returns the bottom position number in the list.
    //   bottomPositionInList    # => 2
    private function bottomPositionInList($except = NULL)
    {
    	$item = $this->bottomItem($except);
    	if($item)
    		return $item->position;
    	else
    		return $this->listifyTop() - 1;
    }

	// Returns the bottom item
    private function bottomItem($except = NULL)
    {
    	$conditions = $this->scopeCondition();
    	if($except != NULL)
    	{
    		$conditions = $conditions . " AND " . $this->primaryKey() . " != " . $except->id;
    	}
    	$model = App::make($this->listifyClass());
    	return $model->inList()->where($conditions)->
    		orderBy($this->table() . "." . $this->positionColumn . " DESC")->first();
    }

    private function primaryKey()
    {
    	return "id"; //NEED TO GET ACTUAL PRIMARY KEY HERE - FIX THIS
    }

    // Forces item to assume the bottom position in the list.
    private function assumeBottomPosition()
    {
    	$this->setListPosition($this->bottomPositionInList($this) + 1);
    }

    // Forces item to assume the top position in the list.
    private function assumeTopPosition()
    {
    	$this->setListPosition($this->listifyTop());
    }

	// This has the effect of moving all the higher items up one.
    private function decrementPositionsOnHigherItems($position)
    {
    	$model = App::make($this->listifyClass());
    	$model->where($this->scopeCondition() . ' AND ' . $this->positionColumn(), '<=', $position)->
    		update(array($this->positionColumn() => $this->position - 1));
    }

    // This has the effect of moving all the lower items up one.
    private function decrementPositionsOnLowerItems($position = NULL)
    {
    	if($this->isNotInList()) return NULL;
    	if($position == NULL) $position = $this->position;

    	$model = App::make($this->listifyClass());
    	$model->where($this->scopeCondition . ' AND ' . $this->positionColumn(), '>', $position)->
    		update(array($this->positionColumn(), $this->position - 1));
    }

    // This has the effect of moving all the higher items down one.
    private function incrementPositionsOnHigherItems()
    {
    	if($this->isNotInList()) return NULL;

    	$model = App::make($this->listifyClass());
    	$model->where($this->scopeCondition() . " AND " . $this->positionColumn(), '<', $this->position)->
    		update(array($this->positionColumn(), $this->position + 1));
    }

    //This has the effect of moving all the lower items down one.
    private function incrementPositionsOnLowerItems($position)
    {
    	$model = App::make($this->listifyClass());
    	$model->where($this->scopeCondition() . ' AND ' . $this->positionColumn(), '>=', $position)->
    		update(array($this->positionColumn(), $this->position + 1));
    }

    // Increments position (<tt>position_column</tt>) of all items in the list.
    private function incrementPositionsOnAllItems()
    {
    	$model = App::make($this->listifyClass());
    	$model->where($this->scopeCondition())->
    		update(array($this->positionColumn(), $this->position + 1));
    }

    // Reorders intermediate items to support moving an item from old_position to new_position.
    private function shufflePositionsOnIntermediateItems($old_position, $new_position, $avoid_id = NULL)
    {
    	if($old_position == $new_position) return;
    	$avoid_id_condition = $avoid_id ? " AND " . $this->primaryKey() . " != " . $avoid_id : '';
    	
    	$model = App::make($this->listifyClass());

    	if $old_position < $new_position
    	{
			// Decrement position of intermediate items

			// e.g., if moving an item from 2 to 5,
			// move [3, 4, 5] to [2, 3, 4]

			$model->where($this->scopeCondition() . ' AND ' . $this->positionColumn(), '>', $old_position)->
				where($this->positionColumn(), '<=', $new_position . $avoid_id_condition)->
				update(array($this->positionColumn(), $this->position - 1));
    	}
    	else
    	{
			// Increment position of intermediate items

			// e.g., if moving an item from 5 to 2,
			// move [2, 3, 4] to [3, 4, 5]

    		$model->where($this->scopeCondition() . ' AND ' . $this->positionColumn(), '>=', $new_position)->
				where($this->positionColumn(), '<', $old_position . $avoid_id_condition)->
				update(array($this->positionColumn(), $this->position + 1));
    	}
    }

    private function insertAtPosition($position)
    {
    	if(!$this->isInList())
    	{
    		$old_position = $this->position;
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
    		$old_position = $this->position;
    		$this->setListPosition(0);
    		$this->decrementPositionsOnLowerItems($old_position);
    	}
    }

    private function updatePositions()
    {
    	$old_position = $this->getOriginal()['position'];
    	$new_position = $this->position;

    	$model = App::make($this->listifyClass());
    	if($model->where($this->scopeCondition() . ' AND ' . $this->positionColumn(), '=', $new_position)->count() <= 1)
    	{
    		return;
    	}

    	$this->shufflePositionsOnIntermediateItems($old_position, $new_position, $id);
    }

    // Temporarily swap changes attributes with current attributes
    private function swapChangedAttributes()
    {
    	// @changed_attributes.each { |k, _| @changed_attributes[k], self[k] =
     	//          self[k], @changed_attributes[k] }
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
    	//self.reload FIX THIS
    }
}