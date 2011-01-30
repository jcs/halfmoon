<?php
/*
	based on halfmoon_console by David Phillips <david@acz.org>
	http://david.acz.org/halfmoon_console/ says "This software is public domain."
*/

if (!function_exists("readline"))
	die("readline extension not installed/loaded. exiting.\n");

require_once(dirname(__FILE__) . "/../halfmoon.php");

function __halfmoon_console__init() {
	error_reporting(E_ALL | E_STRICT);

	ini_set("error_log", NULL);
	ini_set("log_errors", 1);
	ini_set("html_errors", 0);
	ini_set("display_errors", 0);

	while (ob_get_level())
		ob_end_clean();

	ob_implicit_flush(true);

	print "Loading " . HALFMOON_ENV . " environment (halfmoon)\n";

	/* TODO: forcibly load models so they're in the tab-completion cache */

	__halfmoon_console__loop();
}

function __halfmoon_console__rl_complete($line, $pos, $cursor) {
	$consts = array_keys(get_defined_constants());
	$vars = array_keys($GLOBALS);
	$funcs = get_defined_functions();
	$classes = get_declared_classes();

	/* hide internal functions */
	$s = "__halfmoon_console__";
	foreach ($funcs["user"] as $i)
		if (substr($i, 0, strlen($s)) != $s)
			$funcs["internal"][] = $i;
	$funcs = $funcs["internal"];

	return array_merge($consts, $vars, $funcs, $classes);
}

function __halfmoon_console__loop() {
	for (;;) {
		readline_completion_function("__halfmoon_console__rl_complete");
		$__halfmoon_console__line = readline(">> ");

		if ($__halfmoon_console__line === false) {
			echo "\n";
			break;
		}

		if (strlen($__halfmoon_console__line) == 0)
			continue;

		if (!isset($__halfmoon_console__hist) ||
		($__halfmoon_console__line != $__halfmoon_console__hist)) {
			readline_add_history($__halfmoon_console__line);
			$__halfmoon_console__hist = $__halfmoon_console__line;
		}

		if (__halfmoon_console__is_immediate($__halfmoon_console__line))
			$__halfmoon_console__line = "return (" . $__halfmoon_console__line
				. ")";

		ob_start();

		try {
			$ret = eval("unset(\$__halfmoon_console__line); "
				. $__halfmoon_console__line . ";");

			if (ob_get_length() == 0) {
				if (is_bool($ret))
					echo ($ret ? "true" : "false");
				else if (is_string($ret))
					echo "'" . addcslashes($ret, "\0..\37\177..\377")  . "'";
				else if (!is_null($ret))
					print_r($ret);
			}

			unset($ret);
		} catch (Exception $exception) {
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

function __halfmoon_console__is_immediate($line) {
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

__halfmoon_console__init();

?>
