<?
require_once 'Log.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'DatabaseTest.php';
require_once 'AdapterTest.php';
require_once dirname(__FILE__) . '/../../ActiveRecord.php';

$GLOBALS['ACTIVERECORD_LOGGER'] = Log::singleton('file', dirname(__FILE__) . '/../log/query.log','ident',array('mode' => 0664, 'timeFormat' =>  '%Y-%m-%d %H:%M:%S'));

if (getenv('LOG') !== 'false')
	DatabaseTest::$log = true;

ActiveRecord\Config::initialize(function($cfg)
{
	$cfg->set_model_directory(realpath(dirname(__FILE__) . '/../models'));
	$cfg->set_connections(array(
		'mysql'		=> 'mysql://test:test@127.0.0.1/test',
		'mysqli'	=> 'mysqli://test:test@127.0.0.1/test',
		'pgsql'		=> 'pgsql://test:test@127.0.0.1/test',
		'sqlite3'	=> 'sqlite3://test.db'));
	$cfg->set_default_connection('mysql');
});
?>
