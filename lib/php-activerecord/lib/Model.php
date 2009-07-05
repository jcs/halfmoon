<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;
use DateTime;

/**
 * The base class for your models.
 * 
 * Defining an ActiveRecord model:
 * 
 * <code>
 * class Person extends ActiveRecord\Model {}
 * </code>
 * 
 * @package ActiveRecord
 */
class Model
{
	/**
	 * An instance of ActiveRecord\Errors and will be instantiated once a write method is called.
	 * 
	 * @var object
	 */
	public $errors;

	/**
	 * Contains model values as column_name => value
	 * 
	 * @var array
	 */
	private $attributes = array();

	/**
	 * Flag whether or not this model's attributes have been modified since it will either be null or an array of column_names that have been modified
	 * 
	 * @var array
	 */
	private $__dirty = null;

	/**
	 * Flag that determines of this model can have a writer method invoked such as: save/update/insert/delete
	 * 
	 * @var boolean
	 */
	private $__readonly = false;

	/**
	 * Array of relationship objects as model_attribute_name => relationship
	 * 
	 * @var array
	 */
	private $__relationships = array();

	/**
	 * Flag that determines if a call to save() should issue an insert or an update sql statement
	 * 
	 * @var boolean
	 */
	private $__new_record = true;

	/**
	 * Allows you to create aliases for attributes.
	 * 
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $alias_attribute = array(
	 *     'the_first_name' => 'first_name',
	 *     'the_last_name' => 'last_name');
	 * }
	 * 
	 * $person = Person::first();
	 * $person->the_first_name = 'Tito';
	 * echo $person->the_first_name;
	 * </code>
	 * 
	 * @var array
	 */
	static $alias_attribute = array();

	/**
	 * Whitelist of attributes that are checked from mass-assignment calls such as constructing a model or using update_attributes.
	 * 
	 * This is the opposite of $attr_protected.
	 * 
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $attr_accessible = array('first_name','last_name');
	 * }
	 * 
	 * $person = new Person(array(
	 *   'first_name' => 'Tito',
	 *   'last_name' => 'the Grief',
	 *   'id' => 11111));
	 * 
	 * echo $person->id; # => null
	 * </code>
	 * 
	 * @see $attr_protected
	 * @var array
	 */
	static $attr_accessible = array();

	/**
	 * Blacklist of attributes that cannot be mass-assigned.
	 * 
	 * This is the opposite of $attr_accessible.
	 * 
	 * @see $attr_accessible
	 * @var array
	 */
	static $attr_protected = array();

	/**
	 * Delegates calls to a relationship.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $belongs_to = array(array('venue'),array('host'));
	 *   static $delegate = array(
	 *     array('name', 'state', 'to' => 'venue'),
	 *     array('name', 'to' => 'host', 'prefix' => 'woot'));
	 * }
	 * </code>
	 *
	 * Can then do:
	 *
	 * <code>
	 * $person->state     # same as calling $person->venue->state
	 * $person->name      # same as calling $person->venue->name
	 * $person->woot_name # same as calling $person->host->name
	 * </code>
	 * 
	 * @var array
	 */
	static $delegate = array();

	/**
	 * Define customer setters methods for the model. 
	 * 
	 * You can also use this to define custom setters for attributes as well.
	 *
	 * <code>
	 * class User extends ActiveRecord\Base {
	 *   static $setters = array('password','more','even_more');
	 * 
	 *   # now to define the setter methods. Note you must
	 *   # prepend set_ to your method name:
	 *   function set_password($plaintext) {
	 *     $this->encrypted_password = md5($plaintext);
	 *   }
	 * }
	 * 
	 * $user = new User();
	 * $user->password = 'plaintext';  # will call $user->set_password('plaintext')
	 * </code>
	 *
	 * If you define a custom setter with the same name as an attribute then you 
	 * will need to use assign_attribute() to assign the value to the attribute. 
	 * This is necessary due to the way __set() works.
	 *
	 * For example, assume 'name' is a field on the table and we're defining a
	 * custom setter for called 'name':
	 *
	 * <code>
	 * class User extends ActiveRecord\Base {
	 *   static $setters = array('name');
	 * 
	 *   # INCORRECT way to do it
	 *   # function set_name($name) {
	 *   #   $this->name = strtoupper($name);
	 *   # }
	 *
	 *   function set_name($name) {
	 *     $this->assign_attribute('name',strtoupper($name));
	 *   }
	 * }
	 * 
	 * $user = new User();
	 * $user->name = 'bob';
	 * echo $user->name; # => BOB
	 * </code>
	 * 
	 * @var array
	 */
	static $setters = array();

	/**
	 * Constructs a model.
	 * 
	 * When a user instantiates a new object (e.g.: it was not ActiveRecord that instantiated via a find)
	 * then @var $attributes will be mapped according to the schema's defaults. Otherwise, the given @param
	 * $attributes will be mapped via set_attributes_via_mass_assignment.
	 * 
	 * <code>
	 * new Person(array('first_name' => 'Tito', 'last_name' => 'the Grief'));
	 * </code>
	 * 
	 * @param array $attributes Hash containing names and values to mass assign to the model
	 * @param boolean $guard_attributes Set to true to guard attributes
	 * @param boolean $instantiating_via_find Set to true if this model is being created from a find call
	 * @param boolean $new_record Set to true if this should be considered a new record
	 * @return object
	 */
	public function __construct(array $attributes=array(), $guard_attributes=true, $instantiating_via_find=false, $new_record=true)
	{
		$this->__new_record = $new_record;

		// initialize attributes applying defaults
		if (!$instantiating_via_find)
		{
			foreach (static::table()->columns as $name => $meta)
				$this->attributes[$meta->inflected_name] = $meta->default;
		}

		$this->set_attributes_via_mass_assignment($attributes, $guard_attributes);
		$this->invoke_callback('after_construct',false);
	}

	/**
	 * Retrieves an attribute's value or a relationship object based on the name passed. If the attribute
	 * accessed is 'id' then it will return the model's primary key no matter what the actual attribute name is
	 * for the primary key.
	 * 
	 * @throws ActiveRecord\UndefinedPropertyException Thrown if name could not be resolved to an attribute, relationship, ...
	 * @param string $name Name of an attribute
	 * @return mixed The value of the attribute
	 */
	public function &__get($name)
	{
		// check for aliased attribute
		if (array_key_exists($name, static::$alias_attribute))
			$name = static::$alias_attribute[$name];

		// check for attribute
		if (array_key_exists($name,$this->attributes))
			return $this->attributes[$name];

		// check relationships if no attribute
		if (array_key_exists($name,$this->__relationships))
			return $this->__relationships[$name];

		$table = static::table();

		// this may be first access to the relationship so check Table
		if (($relationship = $table->get_relationship($name)))
		{
			$this->__relationships[$name] = $relationship->load($this);
			return $this->__relationships[$name];
		}
		
		if ($name == 'id')
		{
			if (count(($this->get_primary_key(true))) > 1)
				throw new Exception("TODO composite key support");

			if (isset($this->attributes[$table->pk[0]]))
				return $this->attributes[$table->pk[0]];
		}
		
		//do not remove - have to return null by reference in strict mode
		$null = null;

		foreach (static::$delegate as &$item)
		{
			if (($delegated_name = $this->is_delegated($name,$item)))
			{
				$to = $item['to'];
				if ($this->$to)
				{
					$val =& $this->$to->$delegated_name;
					return $val;
				}
				else
					return $null;
			}
		}
		
		throw new UndefinedPropertyException($name);
	}

	/**
	 * Determines if an attribute name exists.
	 * 
	 * @param string $name Name of an attribute
	 * @return boolean Returns true if the attribute is valid for this Model
	 */
	public function __isset($name)
	{
		return array_key_exists($name,$this->attributes);
	}

	/**
	 * Magic allows un-defined attributes to set via $attributes
	 * 
	 * @throws ActiveRecord\UndefinedPropertyException if $name does not exist
	 * @param string $name Name of attribute, relationship or other to set
	 * @param mixed $value The value
	 * @return mixed The value
	 */
	public function __set($name, $value)
	{
		if (array_key_exists($name, static::$alias_attribute))
			$name = static::$alias_attribute[$name];

		elseif (in_array("set_$name",static::$setters))
		{
			$name = "set_$name";
			return $this->$name($value);
		}

		if (array_key_exists($name,$this->attributes))
			return $this->assign_attribute($name,$value);

		foreach (static::$delegate as &$item)
		{
			if (($delegated_name = $this->is_delegated($name,$item)))
				return $this->$item['to']->$delegated_name = $value;
		}

		throw new UndefinedPropertyException($name);
	}

	/**
	 * Assign a value to an attribute.
	 *
	 * @param string $name Name of the attribute
	 * @param mixed $value Value of the attribute
	 * @return mixed The attribute value
	 */
	public function assign_attribute($name, &$value)
	{
		$table = static::table();

		if (!$this->__dirty)
			$this->__dirty = array();

		if (array_key_exists($name,$table->columns) && !is_object($value))
			$value = $table->columns[$name]->cast($value);

		$this->attributes[$name] = $value;
		$this->__dirty[$name] = true;
		return $value;
	}

	/**
	 * Returns hash of attributes that have been modified since loading the model.
	 * 
	 * @return mixed Returns null if no dirty attributes otherwise returns array of dirty attributes.
	 */
	public function dirty_attributes()
	{
		if (!$this->__dirty)
			return null;

		$dirty = array_intersect_key($this->attributes,$this->__dirty);
		return !empty($dirty) ? $dirty : null;
	}

	/**
	 * Returns a copy of the model's attributes hash.
	 * 
	 * @return array The model's attribute data
	 */
	public function attributes()
	{
		return $this->attributes;
	}

	/**
	 * Retrieve the primary key name.
	 * 
	 * @param boolean $inflect Set to true to inflect the key name
	 * @return string The primary key for the model
	 */
	public function get_primary_key($inflect=true)
	{
		return Table::load(get_class($this))->pk;
	}

	/**
	 * Returns an associative array containing values for all the attributes in $attributes
	 * 
	 * @param array $properties Array containing attribute names
	 * @return array A hash containing $name => $value
	 */
	public function get_values_for($attributes)
	{
		$ret = array();

		foreach ($attributes as $name)
		{
			if (array_key_exists($name,$this->attributes))
				$ret[$name] = $this->attributes[$name];
		}
		return $ret;
	}

	/**
	 * Returns the attribute name on the delegated relationship if $name is
	 * delegated or null if not delegated.
	 *
	 * @param string $name Name of an attribute
	 * @param array $delegate An array containing delegate data
	 * @return delegated attribute name or null
	 */
	private function is_delegated($name, &$delegate)
	{
		if ($delegate['prefix'] != '')
			$name = substr($name,strlen($delegate['prefix'])+1);

		if (is_array($delegate) && in_array($name,$delegate['delegate']))
			return $name;

		return null;
	}

	/**
	 * Determine if the model is in read-only mode.
	 * 
	 * @return boolean Returns true if the model is read only
	 */
	public function is_readonly()
	{
		return $this->__readonly;
	}

	/**
	 * Determine if the model is a new record.
	 * 
	 * @return boolean Returns true if this is a new record that has not been saved
	 */
	public function is_new_record()
	{
		return $this->__new_record;
	}

	/**
	 * Throws an exception if this model is set to readonly.
	 * 
	 * @throws ActiveRecord\ReadOnlyException
	 * @param string $method_name Name of method that was invoked on model for exception message
	 */
	private function verify_not_readonly($method_name)
	{
		if ($this->is_readonly())
			throw new ReadOnlyException(get_class($this), $method_name);
	}

	/**
	 * Flag model as readonly.
	 * 
	 * @param boolean $readonly Set to true to put the model into readonly mode
	 */
	public function readonly($readonly=true)
	{
		$this->__readonly = $readonly;
	}

	/**
	 * Retrieve the connection for this model.
	 * 
	 * @return object An instance of ActiveRecord\Connection
	 */
	public static function connection()
	{
		return static::table()->conn;
	}

	/**
	 * Returns the ActiveRecord\Table object for this model.
	 * 
	 * Be sure to call in static scoping:
	 * 
	 * <code>
	 * static::table();
	 * </code>
	 * 
	 * @return object An instance of ActiveRecord\Table
	 */
	public static function table()
	{
		return Table::load(get_called_class());
	}

	/**
	 * Creates a model and save it to the database.
	 * 
	 * @param array $attributes Array of the models attributes
	 * @param boolean $validate True if the validators should be run
	 * @return object An instance ActiveRecord\Model
	 */
	public static function create($attributes, $validate=true)
	{
		$class_name = get_called_class();
		$model = new $class_name($attributes);
		$model->save($validate);
		return $model;
	}

	/**
	 * Save the model to the database.
	 *
	 * This function will automatically determine if an INSERT or UPDATE needs to occur.
	 * If a validation or a callback for this model returns false, then the model will
	 * not be saved and this will return false.
	 * 
	 * If saving an existing model only data that has changed will be saved.
	 * 
	 * @param boolean $validate Set to true or false depending on if you want the validators to run or not
	 * @return boolean True if the model was saved to the database otherwise false
	 */
	public function save($validate=true)
	{
		$this->verify_not_readonly('save');
		return $this->is_new_record() ? $this->insert($validate) : $this->update($validate);
	}

	/**
	 * Issue an INSERT sql statement for this model's attribute.
	 * 
	 * @see save
	 * @param boolean $validate Set to true or false depending on if you want the validators to run or not
	 * @return boolean True if the model was saved to the database otherwise false
	 */
	private function insert($validate=true)
	{
		$this->verify_not_readonly('insert');

		if ($validate && !$this->_validate())
			return false;

		$table = static::table();
		$this->invoke_callback('before_create',false);
		if (($dirty = $this->dirty_attributes()))
			$table->insert($dirty);
		else
			$table->insert($this->attributes);
		$this->invoke_callback('after_create',false);

		$pk = $this->get_primary_key(false);

		// if we've got an autoincrementing pk set it
		if (count($pk) == 1 && $table->columns[$pk[0]]->auto_increment)
		{
			$inflector = Inflector::instance();
			$this->attributes[$inflector->variablize($pk[0])] = $table->conn->insert_id($table->sequence);
		}

		$this->__new_record = false;
		return true;
	}

	/**
	 * Issue an UPDATE sql statement for this model's dirty attributes.
	 * 
	 * @see save
	 * @param boolean $validate Set to true or false depending on if you want the validators to run or not
	 * @return boolean True if the model was saved to the database otherwise false
	 */
	private function update($validate=true)
	{
		$this->verify_not_readonly('update');

		if ($validate && !$this->_validate())
			return false;

		if (($dirty = $this->dirty_attributes()))
		{
			$pk = $this->values_for_pk();

			if (empty($pk))
				throw new ActiveRecordException("Cannot update, no primary key defined for: " . get_called_class());

			$this->invoke_callback('before_update',false);
			static::table()->update($dirty,$pk);
			$this->invoke_callback('after_update',false);
		}

		return true;
	}

	/**
	 * Delete this model from the database.
	 * 
	 * @return boolean True if the model was deleted otherwise false
	 */
	public function delete()
	{
		$this->verify_not_readonly('delete');

		$pk = $this->values_for_pk();

		if (empty($pk))
			throw new ActiveRecordException("Cannot delete, no primary key defined for: " . get_called_class());

		$this->invoke_callback('before_destroy',false);
		static::table()->delete($pk);
		$this->invoke_callback('after_destroy',false);

		return true;
	}

	/**
	 * Helper that creates an array of values for the primary key(s).
	 * 
	 * @return array An array in the form array(key_name => value, ...)
	 */
	public function values_for_pk()
	{
		return $this->values_for(static::table()->pk);
	}

	/**
	 * Helper to return a hash of values for the specified attributes.
	 * 
	 * @param array $attribute_names Array of attribute names
	 * @return array An array in the form array(name => value, ...)
	 */
	public function values_for($attribute_names)
	{
		$filter = array();

		foreach ($attribute_names as $name)
			$filter[$name] = $this->$name;

		return $filter;
	}

	/**
	 * Validates the model.
	 * 
	 * @return boolean True if passed validators otherwise false
	 */
	private function _validate()
	{
		$validator = new Validations($this);
		$validation_on = 'validation_on_' . ($this->is_new_record() ? 'create' : 'update');

		foreach (array('before_validation', "before_$validation_on") as $callback)
		{
			if (!$this->invoke_callback($callback,false))
				return false;
		}

		$this->errors = $validator->validate();

		foreach (array('after_validation', "after_$validation_on") as $callback)
			$this->invoke_callback($callback,false);

		if (!$this->errors->is_empty())
			return false;

		return true;
	}

	/**
	 * Run validations on model
	 * 
	 * @return boolean True if passed validators otherwise false
	 */
	public function is_valid()
	{
		return $this->_validate(false);
	}

	/**
	 * Updates a model's timestamps.
	 */
	public function set_timestamps()
	{
		$now = date('Y-m-d H:i:s');

		if (isset($this->updated_at))
			$this->updated_at = $now;

		if (isset($this->created_at) && $this->is_new_record())
			$this->created_at = $now;
	}

	/**
	 * Mass update the model with an array of attribute data and saves to the database.
	 * 
	 * @param array $attributes An attribute data array in the form array(name => value, ...)
	 * @return boolean True if successfully updated and saved otherwise false
	 */
	public function update_attributes($attributes)
	{
		$this->set_attributes($attributes);
		return $this->save();
	}

	/**
	 * Updates a single attribute and saves the record without going through the normal validation procedure.
	 * 
	 * @param string $name Name of attribute
	 * @param mixed $value Value of the attribute
	 * @return boolean True if successful otherwise false
	 */
	public function update_attribute($name, $value)
	{
		$this->__set($name, $value);
		return $this->update(false);
	}

	/**
	 * Mass update the model with data from an attributes hash.
	 * 
	 * Unlike update_attributes() this method only updates the model's data
	 * but DOES NOT save it to the database.
	 * 
	 * @see update_attributes
	 * @param array $attributes An array containing data to update in the form array(name => value, ...)
	 */
	public function set_attributes(array $attributes)
	{
		$this->set_attributes_via_mass_assignment($attributes, true);
	}

	/**
	 * Passing strict as true will throw an exception if an attribute does not exist.
	 * 
	 * @throws ActiveRecord\UndefinedPropertyException
	 * @param array $attributes An array in the form array(name => value, ...)
	 * @param boolean $guard_attributes Flag of whether or not attributes should be guarded
	 */
	private function set_attributes_via_mass_assignment(array &$attributes, $guard_attributes)
	{
		//access uninflected columns since that is what we would have in result set
		$table = static::table();
		$exceptions = array();
		$use_attr_accessible = !empty(static::$attr_accessible);
		$use_attr_protected = !empty(static::$attr_protected);

		foreach ($attributes as $name => $value)
		{
			// is a normal field on the table
			if (array_key_exists($name,$table->columns))
			{
				$value = $table->columns[$name]->cast($value);
				$name = $table->columns[$name]->inflected_name;
			}

			if ($guard_attributes)
			{
				if ($use_attr_accessible && !in_array($name,static::$attr_accessible))
					continue;

				if ($use_attr_protected && in_array($name,static::$attr_protected))
					continue;

				// set valid table data
				try {
					$this->$name = $value;
				} catch (UndefinedPropertyException $e) {
					$exceptions[] = $e->getMessage();
				}
			}
			else
			{
				// set arbitrary data
				$this->attributes[$name] = $value;
			}
		}

		if (!empty($exceptions))
			throw new UndefinedPropertyException($exceptions);
	}

	/**
	 * Reloads the attributes of this object from the database and the relationships.
	 *
	 * @return object $this
	 */
	public function reload()
	{
		$this->__relationships = array();
		$pk = array_values($this->get_values_for($this->get_primary_key()));
		$this->set_attributes($this->find($pk)->attributes);
		$this->reset_dirty();

		return $this;
	}

	/**
	 * Resets the dirty array.
	 */
	public function reset_dirty()
	{
		$this->__dirty = null;
	}

	/**
	 * A list of valid finder options.
	 * 
	 * @var array
	 */
	static $VALID_OPTIONS = array('conditions', 'limit', 'offset', 'order', 'select', 'joins', 'include', 'readonly', 'group');

	/**
	 * Enables the use of dynamic finders.
	 * 
	 * <code>
	 * SomeModel::find_by_first_name('Tito');
	 * SomeModel::find_by_first_name_and_last_name('Tito','the Grief');
	 * </code>
	 * 
	 * @throws ActiveRecord\ActiveRecordException If invalid query
	 * @param string $method Name of method
	 * @param mixed $args Method args
	 * @return object An instance of ActiveRecord\Model
	 */
	public static function __callStatic($method, $args)
	{
		$options = static::extract_and_validate_options($args);

		if (substr($method,0,7) === 'find_by')
		{
			$options['conditions'] = SQLBuilder::create_conditions_from_underscored_string(substr($method,8),$args,static::$alias_attribute);
			return static::find('first',$options);
		}
		elseif (substr($method,0,11) === 'find_all_by')
		{
			$options['conditions'] = SQLBuilder::create_conditions_from_underscored_string(substr($method,12),$args,static::$alias_attribute);
			return static::find('all',$options);
		}

		throw new ActiveRecordException("Call to undefined method: $method");
	}

	/**
	 * Enables the use of build|create for associations.
	 * 
	 * @param string $method Name of method
	 * @param mixed $args Method args
	 * @return object An instance of a given ActiveRecord\Relationship
	 */
	public function __call($method, $args)
	{
		//check for build|create_association methods
		if (preg_match('/(build|create)_/', $method))
		{
			if (!empty($args))
				$args = $args[0];

			$association_name = str_replace(array('build_', 'create_'), '', $method);

			if (($association = static::table()->get_relationship($association_name)))
			{
				//access association to ensure that the relationship has been loaded
				//so that we do not double-up on records if we append a newly created
				$this->$association_name;
				$method = str_replace($association_name,'association', $method);
				return $association->$method($this, $args);
			}
		}
	}

	/**
	 * Alias for self::find('all').
	 * 
	 * @see find
	 * @return array Array of records found
	 */
	public static function all(/* ... */)
	{
		return call_user_func_array('static::find',array_merge(array('all'),func_get_args()));
	}

	/**
	 * Get a count of qualifying records.
	 * 
	 * <code>
	 * SomeModel::count(array('conditions' => 'amount > 3.14159265'));
	 * </code>
	 * 
	 * @see find
	 * @return integer Number of records that matched the query
	 */
	public static function count(/* ... */)
	{
		$args = func_get_args();
		$options = static::extract_and_validate_options($args);
		$options['select'] = 'COUNT(*) AS n';

		$row = call_user_func_array('static::find',array_merge(array('first'),$args,array($options)));
		return $row->attributes['n'];
	}

	/**
	 * Determine if a record exists.
	 * 
	 * @see find
	 * @return boolean True if it exists otherwise false
	 */
	public static function exists(/* ... */)
	{
		return call_user_func_array('static::count',func_get_args()) > 0 ? true : false;
	}

	/**
	 * Alias for self::find('first').
	 * 
	 * @see find
	 * @return object The first matched record or null if not found
	 */
	public static function first(/* ... */)
	{
		return call_user_func_array('static::find',array_merge(array('first'),func_get_args()));
	}

	/**
	 * Alias for self::find('last')
	 * 
	 * @see find
	 * @return object The last matched record or null if not found
	 */
	public static function last(/* ... */)
	{
		return call_user_func_array('static::find',array_merge(array('last'),func_get_args()));
	}

	/**
	 * Find records in the database.
	 * 
	 * Finding by the primary key:
	 * 
	 * <code>
	 * # queries for the model with id=123
	 * $model->find(123);
	 * 
	 * # queries for model with id in(1,2,3)
	 * $model->find(1,2,3);
	 * 
	 * # finding by pk accepts an options array
	 * $model->find(123,array('order' => 'name desc'));
	 * </code>
	 * 
	 * Finding by using a conditions array:
	 * 
	 * <code>
	 * $model->find('first', array('conditions' => array('name=?','Tito'), 'order' => 'name asc))
	 * $model->find('all', array('conditions' => 'amount > 3.14159265'));
	 * $model->find('all', array('conditions' => array('id in(?)', array(1,2,3))));
	 * </code>
	 * 
	 * An options array can take the following parameters:
	 * 
	 * <ul>
	 * <li>select: A SQL fragment for what fields to return such as: '*', 'people.*', 'first_name, last_name, id'</li>
	 * <li>joins: A SQL join fragment such as: 'JOIN roles ON(roles.user_id=user.id)'</li>
	 * <li>include: TODO not implemented yet</li>
	 * <li>conditions: A SQL fragment such as: 'id=1', array('id=1'), array('name=? and id=?','Tito',1), array('name IN(?)', array('Tito','Bob')),
	 * array('name' => 'Tito', 'id' => 1)</li>
	 * <li>limit: Number of records to limit the query to</li>
	 * <li>offset: The row offset to return results from for the query</li>
	 * <li>order: A SQL fragment for order such as: 'name asc', 'name asc, id desc'</li>
	 * <li>readonly: Return all the models in readonly mode</li>
	 * <li>group: A SQL group by fragment</li>
	 * </ul>
	 * 
	 * @throws ActiveRecord\RecordNotFound if no options are passed
	 * @return mixed An array of records found if doing a find_all otherwise a
	 *   single Model object or null if it wasn't found. NULL is only return when
	 *   doing a first/last find. If doing an all find and no records matched this
	 *   will return an empty array. 
	 */
	public static function find(/* $type, $options */)
	{
		$class = get_called_class();

		if (func_num_args() <= 0)
			throw new RecordNotFound("Couldn't find $class without an ID");

		$args = func_get_args();
		$options = static::extract_and_validate_options($args);
		$num_args = count($args);
		$single = true;

		if ($num_args > 0 && ($args[0] == 'all' || $args[0] == 'first' || $args[0] == 'last'))
		{
			switch ($args[0])
			{
				case 'all':
					$single = false;
					break;

			 	case 'last':
					if (!array_key_exists('order',$options))
						$options['order'] = join(' DESC, ',static::table()->pk) . ' DESC';
					else
						$options['order'] = SQLBuilder::reverse_order($options['order']);

					// fall thru

			 	case 'first':
			 		$options['limit'] = 1;
			 		$options['offset'] = 0;
			 		break;
			}

			$args = array_slice($args,1);
			$num_args--;
		}
		//find by pk
		elseif (1 === count($args) && 1 == $num_args)
			$args = $args[0];

		// anything left in $args is a find by pk
		if ($num_args > 0)
			return static::find_by_pk($args, $options);

		$options['mapped_names'] = static::$alias_attribute;
		$list = static::table()->find($options);

		return $single ? (!empty($list) ? $list[0] : null) : $list;
	}

	/**
	 * Finder method which will find by a single or array of primary keys for this model.
	 * 
	 * @see find
	 * @throws ActiveRecord\RecordNotFound if a record could not be found with the @param $values passed
	 * @param $values mixed An array containing values for the pk
	 * @param $options mixed An options array 
	 * @return object An instance of ActiveRecord\Model
	 */
	public static function find_by_pk($values, $options)
	{
		if (($expected = count($values)) <= 1)
		{
			$options['limit'] = 1;
			$options['offset'] = 0;
		}

		$options['conditions'] = static::pk_conditions($values);

		$list = static::table()->find($options);
		$results = count($list);

		if ($results != $expected)
		{
			$class = get_called_class();

			if ($expected == 1)
			{
				if (!is_array($values))
					$values = array($values);

				throw new RecordNotFound("Couldn't find $class with ID=" . join(',',$values));
			}

			$values = join(',',$values);
			throw new RecordNotFound("Couldn't find all $class with IDs ($values) (found $results, but was looking for $expected)");
		}
		return $expected == 1 ? $list[0] : $list;
	}

	/**
	 * Find using a raw SELECT query.
	 * 
	 * <code>
	 * $model->find_by_sql("SELECT * FROM people WHERE name=?",array('Tito'));
	 * $model->find_by_sql("SELECT * FROM people WHERE name='Tito'");
	 * </code>
	 * 
	 * @param string $sql The raw SELECT query
	 * @param array $values An array of values for any parameters that needs to be bound
	 * @return array An array of models
	 */
	public static function find_by_sql($sql, $values=null)
	{
		return static::table()->find_by_sql($sql, $values, true);
	}

	/**
	 * Determines if the specified array is a valid ActiveRecord options array.
	 * 
	 * @throws ActiveRecord\ActiveRecordException If the array contained any invalid options
	 * @param array $array An options array
	 * @return boolean True if valid otherwise valse
	 */
	public static function is_options_hash($array)
	{
		if (is_hash($array))
		{
			$keys = array_keys($array);
			$diff = array_diff($keys,self::$VALID_OPTIONS);

			if (!empty($diff))
				throw new ActiveRecordException("Unknown key(s): " . join(', ',$diff));

			$intersect = array_intersect($keys,self::$VALID_OPTIONS);

			if (!empty($intersect))
				return true;
		}
		return false;
	}

	/**
	 * Returns a hash containing the names => values of the primary key.
	 * 
	 * This needs to eventually support composite keys.
	 * 
	 * @params mixed $args Primary key value(s)
	 * @return array An array in the form array(name => value, ...)
	 */
	public static function pk_conditions($args)
	{
		$table = static::table();
		$ret = array($table->pk[0] => $args);
		return $ret;
	}

	/**
	 * Pulls out the options hash from $array if any.
	 * 
	 * DO NOT remove the reference on $array.
	 * 
	 * @param array $array An array
	 * @return array A valid options array
	 */
	public static function extract_and_validate_options(array &$array)
	{
		$options = array();

		if ($array)
		{
			$last = &$array[count($array)-1];

			if (self::is_options_hash($last))
			{
				array_pop($array);
				$options = $last;
			}
		}
		return $options;
	}

	/**
	 * Returns a JSON representation of this model.
	 * 
	 * @see Serialization
	 * @param array $options An array containing options for json serialization (see Serialization class for valid options)
	 * @return string JSON representation of the model
	 */
	public function to_json(array $options=array())
	{
		return $this->serialize('Json', $options);
	}

	/**
	 * Returns an XML representation of this model.
	 * 
	 * @see Serialization
	 * @param array $options An array containing options for xml serialization (see Serialization class for valid options)
	 * @return string XML representation of the model
	 */
	public function to_xml(array $options=array())
	{
		return $this->serialize('Xml', $options);
	}

	/**
	 * Creates a serializer based on pre-defined to_serializer()
	 * 
	 * Use options['only'] and options['except'] to include/exclude desired attributes.
	 * 
	 * @param string $type Either Xml or Json
	 * @param array $options Options array for the serializer
	 * @return string Serialized representation of the model
	 */
	private function serialize($type, $options)
	{
		$class = "ActiveRecord\\{$type}Serializer";
		$serializer = new $class($this, $options);
		return $serializer->to_s();
	}

	/**
	 * Invokes the specified callback on this model.
	 * 
	 * @param string $method_name Name of the call back to run.
	 * @param boolean $must_exist Set to true to raise an exception if the callback does not exist.
	 * @return boolean True if invoked or null if not
	 */
	private function invoke_callback($method_name, $must_exist=true)
	{
		return static::table()->callback->invoke($this,$method_name,$must_exist);
	}

	/**
	 * Execute a closure inside a transaction.
	 *
	 * @param Closure $closure The closure to execute. To cause a rollback have your closure return false or throw an exception.
	 */
	public static function transaction(\Closure $closure)
	{
		$connection = static::connection();

		try
		{
			$connection->transaction();

			if ($closure() === false)
				$connection->rollback();
			else
				$connection->commit();
		}
		catch (\Exception $e)
		{
			$connection->rollback();
			throw $e;
		}
	}
};
?>
