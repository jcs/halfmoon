<?php
/*
	internal utility functions
*/

namespace HalfMoon;

class Utils {
	/* treat $data as an array, whatever it is */
	static function A($data, $index) {
		return $data[$index];
	}

	/* determine the current controller class by looking at a backtrace */
	static function current_controller() {
		$controller = null;

		foreach (debug_backtrace() as $stack)
			if ($stack["object"] &&
			get_parent_class($stack["object"]) == "ApplicationController") {
				$controller = $stack["object"];
				break;
			}

		if ($controller)
			return $controller;
		else
			throw new HalfMoonException("couldn't determine current "
				. "controller");
	}

	/* return the class type of the current controller object */
	static function current_controller_name() {
		return get_class(Utils::current_controller());
	}

	/* print_r() to the error log */
	static function error_log_r($param) {
		ob_start();
		print_r($param);
		$lines = explode("\n", ob_get_clean());

		foreach ($lines as $line)
			error_log($line);
	}

	/* return an array of public methods for a given class */
	static function get_public_class_methods($class) {
		$methods = array();

		foreach (get_class_methods($class) as $method) {
			$reflect = new \ReflectionMethod($class, $method);

			if ($reflect->isPublic())
				array_push($methods, $method);
		}

		return $methods;
	}

	/* like is_array() but tells whether it's an associative array */
	function is_assoc($a) {
		return is_array($a) && array_diff_key($a, array_keys(array_keys($a)));
	}

	/* return true if $int is inclusively between an array of $low and $high */
	static function is_or_between($int, $low_and_high) {
		return ($int >= $low_and_high[0] && $int <= $low_and_high[1]);
	}

	static function random_hash() {
		return sha1(pack("N10",
			mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(),
			mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand()));
	}

	/* if passed a regular expression, do preg_match() on $string, otherwise do
	 * a case-insensitive match */
	static function strcasecmp_or_preg_match($check, $string) {
		if (substr($check, 0, 1) == "/")
			return preg_match($check, $string);
		else
			return !strcasecmp($check, $string);
	}

	/* when passed a closure containing print/<?=?> code, execute it, capture the
	 * output, and return it as a string */
	static function to_s($obj, $closure) {
		ob_start();
		$closure($obj);
		$str = ob_get_contents();
		ob_end_clean();

		return $str;
	}
}

?>
