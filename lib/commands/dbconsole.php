<?php
/*
	for the given halfmoon environment, extract its database settings and pass
	them to the command-line utility used to administrate that database
*/

namespace HalfMoon;

class DBConsole {
	private $args;
	public $include_password;

    public function __construct($args) {
		if (!function_exists("pcntl_exec"))
			die("pcntl extension not installed/loaded. exiting.\n");

		$this->args = $args;
		$this->include_password = false;

		for ($x = 1; $x < count($args); $x++)
			switch ($args[$x]) {
			case "-h":
			case "--help":
				$this->usage();
				break;
			
			case "-p":
			case "--include-password":
				$this->include_password = true;
				break;

			default:
				if (substr($args[$x], 0, 1) == "-")
					$this->usage();
				elseif (defined("HALFMOON_ENV"))
					$this->usage();
				else
					define("HALFMOON_ENV", $args[$x]);
			}

		$this->run_db_utility();
	}

	public function usage() {
		die("usage: " . $this->args[0] . " [-hp] [environment]\n");
	}

	public function run_db_utility() {
		require_once(dirname(__FILE__) . "/../halfmoon.php");

		$db_config = Config::instance()->db_config;

		switch ($db_config["adapter"]) {
		case "mysql":
			if ($db_config["socket"])
				$bin_args = array(
					"-S", $db_config["socket"],
				);
			else
				$bin_args = array(
					"-h", $db_config["hostname"],
					"-P", $db_config["port"],
				);
			
			array_push($bin_args, "-D");
			array_push($bin_args, $db_config["database"]);

			array_push($bin_args, "-u");
			array_push($bin_args, $db_config["username"]);

			if ($this->include_password)
				array_push($bin_args, "--password=" . $db_config["password"]);

			$bin_path = null;
			foreach (explode(":", getenv("PATH")) as $dir)
				if (file_exists($dir . "/mysql")) {
					$bin_path = $dir . "/mysql";
					break;
				}

			if (!$bin_path)
				die("cannot find mysql in \$PATH\n");

			pcntl_exec($bin_path, $bin_args);
			break;

		default:
			die($db_config["adapter"] . " not yet supported\n");
		}
	}
}

?>
