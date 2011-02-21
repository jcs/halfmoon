<?php
/*
	based on halfmoon_console by David Phillips <david@acz.org>
	http://david.acz.org/halfmoon_console/ says "This software is public domain."
*/

namespace HalfMoon;

class Console {
	private $args;
	public static $have_readline;
	public $line;
	public $history;

	public function __construct($args) {
		$this->args = $args;

		Console::$have_readline = function_exists("readline");

		for ($x = 1; $x < count($args); $x++)
			switch ($args[$x]) {
			case "-h":
			case "--help":
				$this->usage();
				break;
			
			default:
				if (substr($args[$x], 0, 1) == "-")
					$this->usage();
				elseif (defined("HALFMOON_ENV"))
					$this->usage();
				else
					define("HALFMOON_ENV", $args[$x]);
			}

		require_once(__DIR__ . "/../halfmoon.php");

		error_reporting(E_ALL | E_STRICT);

		ini_set("error_log", NULL);
		ini_set("log_errors", 1);
		ini_set("html_errors", 0);
		ini_set("display_errors", 0);

		while (ob_get_level())
			ob_end_clean();

		ob_implicit_flush(true);

		/* TODO: forcibly load models so they're in the tab-completion cache */

		print "Loaded " . HALFMOON_ENV . " environment (halfmoon)\n";

		$this->loop();
	}

	public function usage() {
		die("usage: " . $this->args[0] . " [-h] [environment]\n");
	}

	public function readline_complete($line, $pos, $cursor) {
		$consts = array_keys(get_defined_constants());
		$vars = array_keys($GLOBALS);
		$funcs = get_defined_functions();
		$classes = get_declared_classes();

		return array_merge($consts, $vars, $funcs, $classes);
	}

	public function loop() {
		for (;;) {
			if (Console::$have_readline) {
				readline_completion_function(array($this,
					"readline_complete"));
				$this->line = @readline(">> ");
			} else {
				print ">> ";
				$this->line = trim(fgets(STDIN));
			}

			if ($this->line === false ||
			(!Console::$have_readline && feof(STDIN))) {
				echo "\n";
				break;
			}

			if (strlen($this->line) == 0)
				continue;

			if (Console::$have_readline && (!isset($this->history) ||
			($this->line != $this->history))) {
				readline_add_history($this->line);
				$this->history = $this->line;
			}

			if ($this->is_immediate($this->line))
				$this->line = "return (" . $this->line . ")";

			ob_start();

			try {
				$ret = @eval($this->line . ";");

				if (ob_get_length() == 0) {
					if (is_bool($ret))
						echo ($ret ? "true" : "false");
					else if (is_string($ret))
						echo "'" . addcslashes($ret, "\0..\37\177..\377")  . "'";
					else if (!is_null($ret))
						print_r($ret);
				}

				unset($ret);
			} catch (\Exception $exception) {
				$title = get_class($exception);

				/* activerecord includes the stack trace in the message, so strip
				 * it out */
				if ($exception instanceof \ActiveRecord\DatabaseException)
					$title .= ": " . preg_replace("/\nStack trace:.*/s", "",
						$exception->getMessage());
				elseif ($exception->getMessage())
					$title .= ": " . $exception->getMessage() . " in "
						. $exception->getFile() . " on line "
						. $exception->getLine();

				print $title . "\n";

				foreach ($exception->getTrace() as $call)
					print "    "
						. (isset($call["file"]) ? $call["file"] : $call["class"])
						. ":"
						. (isset($call["line"]) ? $call["line"] : "")
						. " in " . $call["function"] . "()\n";
			}

			$out = ob_get_contents();
			ob_end_clean();

			if ((strlen($out) > 0) && (substr($out, -1) != "\n"))
				$out .= "\n";

			echo $out;

			unset($out);
		}
	}

	public function is_immediate($line) {
		$skip = array("class", "declare", "die", "echo", "exit", "for",
					  "foreach", "function", "global", "if", "include",
					  "include_once", "print", "require", "require_once",
					  "return", "static", "switch", "unset", "while");
		$okeq = array("===", "!==", "==", "!=", "<=", ">=");
		$code = "";
		$sq = false;
		$dq = false;
		for ($i = 0; $i < strlen($line); $i++) {
			$c = $line{$i};
			if ($c == "'")
				$sq = !$sq;
			else if ($c == '"')
				$dq = !$dq;
			else if (($sq) || ($dq)) {
				if ($c == "\\")
					$i++;
			} else
				$code .= $c;
		}

		$code = str_replace($okeq, "", $code);

		if (strcspn($code, ";{=") != strlen($code))
			return false;

		foreach (preg_split("/[^A-Za-z0-9_]/", $code) as $i)
			if (in_array($i, $skip))
				return false;

		return true;
	}
}

?>
