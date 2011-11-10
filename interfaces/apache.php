<?php
/*
	interface apache with halfmoon and route the request via $QUERY_STRING
*/

$start_time = microtime(true);

if (isset($_SERVER["HALFMOON_ENV"]))
	define("HALFMOON_ENV", $_SERVER["HALFMOON_ENV"]);

require_once(__DIR__ . "/../lib/halfmoon.php");

global $_HALFMOON_REQUEST;
$_HALFMOON_REQUEST = new HalfMoon\Request(
	$_SERVER["SCRIPT_URI"] . (empty($_SERVER["QUERY_STRING"]) ? ""
		: "?" . $_SERVER["QUERY_STRING"]),
	$_GET, $_POST, $_SERVER, $start_time
);
$_HALFMOON_REQUEST->process();

?>
