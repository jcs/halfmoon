<?php
/*
	internal utility functions
*/

/* treat $data as an array, whatever it is */
function A($data, $index) {
	return $data[$index];
}

/* determine the current controller class by looking at a backtrace */
function current_controller() {
	$controller = null;

	foreach (debug_backtrace() as $stack)
		if ($stack["object"]) {
			$controller = get_class($stack["object"]);
			break;
		}

	if ($controller)
		return $controller;
	else
		throw new HalfmoonException("couldn't determine current controller");
}

/* print_r() to the error log */
function error_log_r($param) {
	ob_start();
	print_r($param);
	$lines = explode("\n", ob_get_clean());

	foreach ($lines as $line)
		error_log($line);
}

/* return an array of public methods for a given class */
function get_public_class_methods($class) {
    $methods = array();

	foreach (get_class_methods($class) as $method) {
		$reflect = new ReflectionMethod($class, $method);

		if ($reflect->isPublic())
            array_push($methods, $method);
	}

    return $methods;
}

/* return true if $int is between (inclusive) an array of $low and $high */
function is_or_between($int, $low_and_high) {
	return ($int >= $low_and_high[0] && $int <= $low_and_high[1]);
}

/* when passed a closure containing print/<?=?> code, execute it, capture the
 * output, and return it as a string */
function to_s($obj, $closure) {
	ob_start();
	$closure($obj);
	$str = ob_get_contents();
	ob_end_clean();

	return $str;
}

?>
