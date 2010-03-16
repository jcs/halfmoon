<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

require 'Column.php';

use PDO;
use PDOException;
use Closure;

/**
 * The base class for database connection adapters.
 *
 * @package ActiveRecord
 */
abstract class Connection
{
	/**
	 * The PDO connection object.
	 * @var mixed
	 */
	public $connection;

	/**
	 * The last query run.
	 * @var string
	 */
	public $last_query;

	/**
	 * Default PDO options to set for each connection.
	 * @var array
	 */
	static $PDO_OPTIONS = array(
		PDO::ATTR_CASE				=> PDO::CASE_LOWER,
		PDO::ATTR_ERRMODE			=> PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_ORACLE_NULLS		=> PDO::NULL_NATURAL,
		PDO::ATTR_STRINGIFY_FETCHES	=> false);

	/**
	 * The quote character for stuff like column and field names.
	 * @var string
	 */
	static $QUOTE_CHARACTER = '`';

	/**
	 * Retrieve a database connection.
	 *
	 * @param string $connection_string_or_connection_name A database connection string (ex. mysql://user:pass@host[:port]/dbname)
	 *   Everything after the protocol:// part is specific to the connection adapter.
	 *   OR
	 *   A connection name that is set in ActiveRecord\Config
	 *   If null it will use the default connection specified by ActiveRecord\Config->set_default_connection
	 * @return Connection
	 */
	public static function instance($connection_string_or_connection_name=null)
	{
		$config = Config::instance();

		if (strpos($connection_string_or_connection_name,'://') === false)
		{
			$connection_string = $connection_string_or_connection_name ?
				$config->get_connection($connection_string_or_connection_name) :
				$config->get_default_connection_string();
		}
		else
			$connection_string = $connection_string_or_connection_name;

		if (!$connection_string)
			throw new DatabaseException("Empty connection string");

		$info = static::parse_connection_url($connection_string);
		$fqclass = static::load_adapter_class($info->protocol);

		try {
			$connection = new $fqclass($info);
			$connection->protocol = $info->protocol;
		} catch (PDOException $e) {
			throw new DatabaseException($e);
		}
		return $connection;
	}

	/**
	 * Loads the specified class for an adapter.
	 *
	 * @param string $adapter Name of the adapter.
	 * @return string The full name of the class including namespace.
	 */
	private static function load_adapter_class($adapter)
	{
		$class = ucwords($adapter) . 'Adapter';
		$fqclass = 'ActiveRecord\\' . $class;
		$source = dirname(__FILE__) . "/adapters/$class.php";

		if (!file_exists($source))
			throw new DatabaseException("$fqclass not found!");

		require_once($source);
		return $fqclass;
	}

	/**
	 * Use this for any adapters that can take connection info in the form below
	 * to set the adapters connection info.
	 *
	 * protocol://user:pass@host[:port]/dbname
	 *
	 * @param string $url A connection URL
	 * @return object the parsed URL as an object.
	 */
	public static function parse_connection_url($url)
	{
		$url = @parse_url($url);

		if (!isset($url['host']))
			throw new DatabaseException('Database host must be specified in the connection string.');

		$info = new \stdClass();
		$info->protocol = $url['scheme'];
		$info->host		= $url['host'];
		$info->db		= isset($url['path']) ? substr($url['path'],1) : null;
		$info->user		= isset($url['user']) ? $url['user'] : null;
		$info->pass		= isset($url['pass']) ? $url['pass'] : null;

		if (isset($url['port']))
			$info->port = $url['port'];

		return $info;
	}

	/**
	 * Class Connection is a singleton. Access it via instance().
	 *
	 * @param array $info Array containing URL parts
	 * @return Connection
	 */
	protected function __construct($info)
	{
		try {
			$this->connection = new PDO("$info->protocol:host=$info->host" .
				(isset($info->port) ? ";port=$info->port":'') .	";dbname=$info->db",$info->user,$info->pass,
				static::$PDO_OPTIONS);
		} catch (PDOException $e) {
			throw new DatabaseException($e);
		}
	}

	/**
	 * Retrieves column meta data for the specified table.
	 *
	 * @param string $table Name of a table
	 * @return array An array of {@link Column} objects.
	 */
	public function columns($table)
	{
		$columns = array();
		$sth = $this->query_column_info($table);

		while (($row = $sth->fetch()))
		{
			$c = $this->create_column($row);
			$columns[$c->name] = $c;
		}
		return $columns;
	}

	/**
	 * Escapes quotes in a string.
	 *
	 * @param string $string The string to be quoted.
	 * @return string The string with any quotes in it properly escaped.
	 */
	public function escape($string)
	{
		return $this->connection->quote($string);
	}

	/**
	 * Retrieve the insert id of the last model saved.
	 *
	 * @param string $sequence Optional name of a sequence to use
	 * @return int
	 */
	public function insert_id($sequence=null)
	{
		return $this->connection->lastInsertId($sequence);
	}

	/**
	 * Execute a raw SQL query on the database.
	 *
	 * @param string $sql Raw SQL string to execute.
	 * @param array &$values Optional array of bind values
	 * @return mixed A result set object
	 */
	public function query($sql, &$values=array())
	{
		if (isset($GLOBALS['ACTIVERECORD_LOG']) && $GLOBALS['ACTIVERECORD_LOG'])
			$GLOBALS['ACTIVERECORD_LOGGER']->log($sql, PEAR_LOG_INFO);

		$this->last_query = $sql;

		try {
			if (!($sth = $this->connection->prepare($sql)))
				throw new DatabaseException($this);
		} catch (PDOException $e) {
			throw new DatabaseException($this);
		}

		$sth->setFetchMode(PDO::FETCH_ASSOC);

		try {
			if (!$sth->execute($values))
				throw new DatabaseException($this);
		} catch (PDOException $e) {
			throw new DatabaseException($sth);
		}
		return $sth;
	}

	/**
	 * Execute a query that returns maximum of one row with one field and return it.
	 *
	 * @param string $sql Raw SQL string to execute.
	 * @param array &$values Optional array of values to bind to the query.
	 * @return string
	 */
	public function query_and_fetch_one($sql, &$values=array())
	{
		$sth = $this->query($sql,$values);
		$row = $sth->fetch(PDO::FETCH_NUM);
		return $row[0];
	}

	/**
	 * Execute a raw SQL query and fetch the results.
	 *
	 * @param string $sql Raw SQL string to execute.
	 * @param Closure $handler Closure that will be passed the fetched results.
	 */
	public function query_and_fetch($sql, Closure $handler)
	{
		$sth = $this->query($sql);

		while (($row = $sth->fetch(PDO::FETCH_ASSOC)))
			$handler($row);
	}

	/**
	 * Returns all tables for the current database.
	 *
	 * @return array Array containing table names.
	 */
	public function tables()
	{
		$tables = array();
		$sth = $this->query_for_tables();

		while (($row = $sth->fetch(PDO::FETCH_NUM)))
			$tables[] = $row[0];

		return $tables;
	}

	/**
	 * Starts a transaction.
	 */
	public function transaction()
	{
		if (!$this->connection->beginTransaction())
			throw new DatabaseException($this);
	}

	/**
	 * Commits the current transaction.
	 */
	public function commit()
	{
		if (!$this->connection->commit())
			throw new DatabaseException($this);
	}

	/**
	 * Rollback a transaction.
	 */
	public function rollback()
	{
		if (!$this->connection->rollback())
			throw new DatabaseException($this);
	}

	/**
	 * Tells you if this adapter supports sequences or not.
	 *
	 * @return boolean
	 */
	function supports_sequences() { return false; }

	/**
	 * Return a default sequence name for the specified table.
	 *
	 * @param string $table Name of a table
	 * @param string $column_name Name of column sequence is for
	 * @return string sequence name or null if not supported.
	 */
	public function get_sequence_name($table, $column_name)
	{
		return "{$table}_seq";
	}

	/**
	 * Return SQL for getting the next value in a sequence.
	 *
	 * @param string $sequence_name Name of the sequence 
	 * @return string
	 */
	public function next_sequence_value($sequence_name) { return null; }

	/**
	 * Quote a name like table names and field names.
	 *
	 * @param string $string String to quote.
	 * @return string
	 */
	public function quote_name($string)
	{
		return $string[0] === static::$QUOTE_CHARACTER || $string[strlen($string)-1] === static::$QUOTE_CHARACTER ?
			$string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
	}

	/**
	 * Returns the default port of the database server.
	 */
	abstract function default_port();

	/**
	 * Adds a limit clause to the SQL query.
	 *
	 * @param string $sql The SQL statement.
	 * @param int $offset Row offset to start at.
	 * @param int $limit Maximum number of rows to return.
	 * @return string The SQL query that will limit results to specified parameters
	 */
	abstract function limit($sql, $offset, $limit);

	/**
	 * Query for column meta info and return statement handle.
	 *
	 * @param string $table Name of a table
	 * @return PDOStatement
	 */
	abstract public function query_column_info($table);

	/**
	 * Query for all tables in the current database. The result must only
	 * contain one column which has the name of the table.
	 *
	 * @return PDOStatement
	 */
	abstract function query_for_tables();
};
?>
