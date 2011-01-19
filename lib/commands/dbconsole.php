<?php

if (!function_exists("pcntl_exec"))
	die("pcntl extension not installed/loaded. exiting.\n");

require_once(dirname(__FILE__) . "/../halfmoon.php");

$db_config = Halfmoon\Config::instance()->db_config;

switch ($db_config["adapter"]) {
case "mysql":
	if ($db_config["socket"])
		$args = array(
			"-S", $db_config["socket"],
		);
	else
		$args = array(
			"-h", $db_config["hostname"],
			"-P", $db_config["port"],
		);
	
	array_push($args, "-D");
	array_push($args, $db_config["database"]);

	array_push($args, "-u");
	array_push($args, $db_config["username"]);

	if ($argv[1] == "-p")
		array_push($args, "--password=" . $db_config["password"]);

	$bin_path = null;
	foreach (explode(":", getenv("PATH")) as $dir)
		if (file_exists($dir . "/mysql")) {
			$bin_path = $dir . "/mysql";
			break;
		}

	if (!$bin_path)
		die("cannot find mysql in \$PATH\n");

    pcntl_exec($bin_path, $args);
    break;

default:
    die($db_config["adapter"] . " not yet supported\n");
}

?>
