<?php
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
	die('PHP ActiveRecord requires PHP 5.3 or higher');

define('PHP_ACTIVERECORD_VERSION_ID','1.0');

define('PHP_ACTIVERECORD_ROOT', realpath(dirname(__FILE__)) . "/");

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_PREPEND'))
	define('PHP_ACTIVERECORD_AUTOLOAD_PREPEND',true);

require(PHP_ACTIVERECORD_ROOT . 'lib/Singleton.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Config.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Utils.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/DateTime.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Model.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Table.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/ConnectionManager.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Connection.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/SQLBuilder.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Reflections.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Inflector.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/CallBack.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Exceptions.php');
require(PHP_ACTIVERECORD_ROOT . 'lib/Cache.php');

if (!defined('PHP_ACTIVERECORD_AUTOLOAD_DISABLE'))
	spl_autoload_register('activerecord_autoload',false,PHP_ACTIVERECORD_AUTOLOAD_PREPEND);

function activerecord_autoload($class_name)
{
	$path = ActiveRecord\Config::instance()->get_model_directory();
	$root = realpath(isset($path) ? $path : '.');

	if (($namespaces = ActiveRecord\get_namespaces($class_name)))
	{
		$class_name = array_pop($namespaces);
		$directories = array();

		foreach ($namespaces as $directory)
			$directories[] = $directory;

		$root .= DIRECTORY_SEPARATOR . implode($directories, DIRECTORY_SEPARATOR);
	}

	$file = "$root/$class_name.php";

	if (file_exists($file))
		require $file;
}
?>
