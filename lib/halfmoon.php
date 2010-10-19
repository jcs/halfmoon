<?php
/*
	main entry point
*/

if (floatval(phpversion()) < 5.3)
	die("PHP version of at least 5.3 is required and you are using "
		. phpversion() . ")");

/* where our app lives (not just where halfmoon lives) */
define("HALFMOON_ROOT", realpath(dirname(__FILE__) . "/../../"));

/* some sane defaults */
date_default_timezone_set("UTC");
session_name("_halfmoon_session");

/* we assume to be in the development environment unless told otherwise */
if (!defined("HALFMOON_ENV"))
    define("HALFMOON_ENV", "development");

require_once(HALFMOON_ROOT . "/halfmoon/lib/logging.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/exceptions.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/rescue.php");

require_once(HALFMOON_ROOT . "/halfmoon/lib/utilities.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/singleton.php");

require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/global_helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/html_helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/form_common.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/form_tag_helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/form_helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/time_helper.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/helpers/prototype_helper.php");

require_once(HALFMOON_ROOT . "/halfmoon/lib/controller.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/request.php");
require_once(HALFMOON_ROOT . "/halfmoon/lib/router.php");

require_once(HALFMOON_ROOT . "/halfmoon/lib/config.php");

/* bring in activerecord */
require_once(HALFMOON_ROOT . "/halfmoon/lib/php-activerecord/ActiveRecord.php");

/* append our root to the include path to pick up user-installed code */
set_include_path(get_include_path() . PATH_SEPARATOR . HALFMOON_ROOT);

/* load site-specific boot settings */
if (file_exists(HALFMOON_ROOT . "/config/boot.php"))
	require_once(HALFMOON_ROOT . "/config/boot.php");

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

	if (!isset($db_config[HALFMOON_ENV]))
		throw new HalfMoon\ConfigException("no database configuration found "
			. "for \"" . HALFMOON_ENV . "\" environment");

	$db = array_merge($db, $db_config[HALFMOON_ENV]);

	$cfg->set_model_directory(realpath(HALFMOON_ROOT . "/models/"));

	$cfg->set_connections(array(
		"development" => $db["adapter"] . "://" . $db["username"] . ":"
			. $db["password"] . "@" . $db["hostname"] . ":" . $db["port"] . "/"
			. $db["database"]
	));

	# support old globals for logging
	if ($GLOBALS["ACTIVERECORD_LOG"]) {
		$cfg->set_logging(true);
		$cfg->set_logger($GLOBALS["ACTIVERECORD_LOGGER"]);
	}
});

/* bring in all the controllers starting with the application_controller */
$controllers = glob(HALFMOON_ROOT . "/controllers/*_controller.php");
usort($controllers, function($a, $b) {
	return basename($b) == "application_controller.php" ? 1 : -1;
});
foreach ($controllers as $controller)
	require_once($controller);

/* bring in the route table and route our request */
HalfMoon\Router::initialize(function($router) {
	require_once(HALFMOON_ROOT . "/config/routes.php");
});

?>
