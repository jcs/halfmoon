<?php
/*
	logging utilities
*/

namespace HalfMoon;

class Log {
	static function error($string) {
		error_log($string);
	}

	static function info($string) {
		error_log($string);
	}

	static function warn($string) {
		error_log($string);
	}

	/* print_r() to the error log */
	static function error_log_r($param) {
		ob_start();
		print_r($param);
		$lines = explode("\n", ob_get_clean());

		foreach ($lines as $line)
			error_log($line);
	}
}

?>
