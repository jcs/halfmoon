<?php
/*
	interface nginx+php_fpm with halfmoon and route the request
*/

$start_time = microtime(true);

if (isset($_SERVER["HALFMOON_ENV"]))
	define("HALFMOON_ENV", $_SERVER["HALFMOON_ENV"]);

require_once(__DIR__ . "/../lib/halfmoon.php");

$__url = "";
if (empty($_SERVER["HTTPS"])) {
	$__url = "http://" . $_SERVER["SERVER_NAME"];
	if (!empty($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != 80)
		$__url .= ":" . $_SERVER["SERVER_PORT"];
}
else {
	$__url = "https://" . $_SERVER["SERVER_NAME"];
	if (!empty($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != 443)
		$__url .= ":" . $_SERVER["SERVER_PORT"];
}

$__url .= $_SERVER["REQUEST_URI"];

global $_HALFMOON_REQUEST;
$_HALFMOON_REQUEST = new HalfMoon\Request($__url, $_GET, $_POST, $_SERVER,
	$start_time);
$_HALFMOON_REQUEST->process();

?>
