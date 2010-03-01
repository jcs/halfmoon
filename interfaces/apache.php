<?php
/*
	interface apache with halfmoon and route the request via $QUERY_STRING
*/

if (isset($_SERVER["HALFMOON_ENV"]))
	define("HALFMOON_ENV", $_SERVER["HALFMOON_ENV"]);

require_once(dirname(__FILE__) . "/../lib/halfmoon.php");

$req = new HalfMoon\Request($_SERVER["SCRIPT_URI"], $_GET, $_POST, $_SERVER);
$req->process();

?>
