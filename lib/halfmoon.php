<?php
/*
	main entry point
*/

if (floatval(phpversion()) < 5.3) {
	die("PHP version of at least 5.3 is required and you are using "
		. phpversion() . ")");
}

/* where our app lives (not just where halfmoon lives) */
define("HALFMOON_ROOT", realpath(dirname(__FILE__) . "/../../"));

/* some sane defaults */
date_default_timezone_set("UTC");
session_name("halfmoon_session");

/* we assume to be in the development environment unless told otherwise */
if (!defined("HALFMOON_ENV"))
    define("HALFMOON_ENV", "development");

require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/singleton.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/controller.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/router.php");

/* load site-specific boot settings */
if (file_exists(HALFMOON_ROOT . "/config/boot.php"))
	require_once(HALFMOON_ROOT . "/config/boot.php");

/* bring in activerecord */
require_once(HALFMOON_ROOT . "/halfmoon/lib/php-activerecord/ActiveRecord.php");

/* establish db config from ../config/db.ini */
ActiveRecord\Config::initialize(function($cfg) {
	$db = array(
		"adapter"  => "mysql",
		"username" => "username",
		"password" => "password",
		"hostname" => "localhost",
		"database" => "database",
		"port" => 3306,
	);

	$db_config = parse_ini_file(HALFMOON_ROOT . "/config/db.ini", true);

	if (!$db_config[HALFMOON_ENV])
		die("no database configuration found for \"" . HALFMOON_ENV . "\" "
			. "environment");

	$db = array_merge($db, $db_config[HALFMOON_ENV]);

	$cfg->set_model_directory(realpath(HALFMOON_ROOT . "/models/"));

	$cfg->set_connections(array(
		"development" => $db["adapter"] . "://" . $db["username"] . ":"
			. $db["password"] . "@" . $db["hostname"] . ":" . $db["port"] . "/"
			. $db["database"]
	));
});

/* bring in all the controllers starting with the application_controller */
$controllers = glob(HALFMOON_ROOT . "/controllers/*_controller.php");
usort($controllers, function($a, $b) {
	return basename($b) == "application_controller.php" ? 1 : -1;
});
foreach ($controllers as $controller)
	require_once($controller);

/* bring in the route table */
HalfMoon\Router::initialize(function($router) {
	require_once(HALFMOON_ROOT . "/config/routes.php");
});

?>
