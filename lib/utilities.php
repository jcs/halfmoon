<?php
/*
	internal utility functions
*/

/* treat $data as an array, whatever it is */
function A($data, $index) {
	return $data[$index];
}

/* print_r() to the error log */
function error_log_r($param) {
	ob_start();
	print_r($param);
	$lines = explode("\n", ob_get_clean());

	foreach ($lines as $line)
		error_log($line);
}

/* when passed a closure containing print/<?=?> code, execute it, capture the
 * output, and return it as a string */
function to_s($closure) {
	ob_start();
	$closure();
	$str = ob_get_contents();
	ob_end_clean();

	return $str;
}

?>
