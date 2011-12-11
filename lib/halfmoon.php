<?php
/*
	main entry point
*/

if (floatval(phpversion()) < 5.3)
	die("PHP version of at least 5.3 is required and you are using "
		. phpversion() . ")");

/* where our app lives (not just where halfmoon lives) */
define("HALFMOON_ROOT", realpath(__DIR__ . "/../../"));

/* some sane defaults */
date_default_timezone_set("UTC");
session_name("_halfmoon_session");

if (!defined("HALFMOON_ENV")) {
	if (getenv("HALFMOON_ENV"))
		define("HALFMOON_ENV", getenv("HALFMOON_ENV"));
	else
		/* assume to be in the development environment unless told otherwise */
		define("HALFMOON_ENV", "development");
}

require_once(HALFMOON_ROOT . "/halfmoon/lib/exceptions.php");

/* install error handlers as soon as possible */
require_once(HALFMOON_ROOT . "/halfmoon/lib/rescuer.php");

/* set some sane defaults */
date_default_timezone_set("UTC");
session_name("_halfmoon_session");

require_once(HALFMOON_ROOT . "/halfmoon/lib/logging.php");

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

if (file_exists(HALFMOON_ROOT . "/config/db.ini"))
	require_once(HALFMOON_ROOT . "/halfmoon/lib/php-activerecord/"
		. "ActiveRecord.php");

/* append our root to the include path to pick up user-installed code */
set_include_path(get_include_path() . PATH_SEPARATOR . HALFMOON_ROOT);

/* load site-specific boot settings */
if (file_exists(HALFMOON_ROOT . "/config/boot.php"))
	require_once(HALFMOON_ROOT . "/config/boot.php");

/* autoload controllers and helpers as needed */
function halfmoon_autoload($class_name) {
	if (preg_match("/^([A-Za-z0-9_]+)(Controller|Helper)$/", $class_name, $m)) {
		$file = HALFMOON_ROOT . "/" . (strtolower($m[2]) . "s") . "/"
			. strtolower($m[1]) . "_" . strtolower($m[2]) . ".php";

		if (file_exists($file))
			require_once($file);
	}
}

spl_autoload_register("halfmoon_autoload", false, false);

if (defined("PHP_ACTIVERECORD_ROOT"))
	HalfMoon\Config::initialize_activerecord();

/* bring in the route table and route our request */
if (file_exists(HALFMOON_ROOT . "/config/routes.php"))
	HalfMoon\Router::initialize(function($router) {
		require_once(HALFMOON_ROOT . "/config/routes.php");
	});

?>
