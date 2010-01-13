<?php
/*
	interface apache with halfmoon and route the request via $QUERY_STRING
*/

if ($_SERVER["HALFMOON_ENV"])
	define("HALFMOON_ENV", $_SERVER["HALFMOON_ENV"]);

require_once(dirname(__FILE__) . "/../lib/halfmoon.php");

HalfMoon\Router::instance()->routeRequest($_SERVER["PATH_INFO"], $_SERVER["QUERY_STRING"]);

?>
