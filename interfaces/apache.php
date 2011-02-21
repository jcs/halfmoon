<?php
/*
	interface apache with halfmoon and route the request via $QUERY_STRING
*/

$start_time = microtime(true);

if (isset($_SERVER["HALFMOON_ENV"]))
	define("HALFMOON_ENV", $_SERVER["HALFMOON_ENV"]);

require_once(__DIR__ . "/../lib/halfmoon.php");

$req = new HalfMoon\Request($_SERVER["SCRIPT_URI"], $_GET, $_POST, $_SERVER,
	$start_time);
$req->process();

?>
