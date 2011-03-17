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

	/* get result options for only/except option hashes */
	static function options_for_key_from_options_hash($key, $options) {
		if (empty($options))
			return array();
		elseif (!is_array($options))
			return array($options);

		/* if options is a hash, then the result should just be the keys that
		 * match.  otherwise, it should contain all of the values that match.
		 */
		$is_assoc = Utils::is_assoc($options);

		$result = array();

		foreach ($options as $k => $opthash) {
			if (Utils::is_assoc($opthash)) {
				$apply = false;

				if (isset($opthash["only"])) {
					if (!is_array($opthash["only"]))
						$opthash["only"] = array($opthash["only"]);

					foreach ($opthash["only"] as $pkey)
						if (Utils::strcasecmp_or_preg_match($pkey, $key)) {
							$apply = true;
							break;
						}
				} elseif (isset($opthash["except"])) {
					$apply = true;

					if (!is_array($opthash["except"]))
						$opthash["except"] = array($opthash["except"]);

					foreach ($opthash["except"] as $pkey)
						if (Utils::strcasecmp_or_preg_match($pkey, $key)) {
							$apply = false;
							break;
						}
				}

				if ($apply) {
					if ($is_assoc)
						array_push($result, $k);
					else {
						unset($opthash["only"]);
						unset($opthash["except"]);
						array_push($result, $opthash);
					}
				}
			} else
				array_push($result, $opthash);
		}

		return $result;
	}

	/* get a true or false for only/except option hashes */
	static function option_applies_for_key($key, $options) {
		$noptions = array(
			true => $options
		);

		$ret = Utils::options_for_key_from_options_hash($key, $noptions);

		return !empty($ret);
	}

	/* determine the current controller class by looking at a backtrace */
	static function current_controller() {
		$controller = null;

		foreach (debug_backtrace() as $stack)
			if (isset($stack["object"]) &&
			preg_match("/^(HalfMoon\\\\)?ApplicationController$/",
			get_parent_class($stack["object"]))) {
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

	/* return a 40-character random string */
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
