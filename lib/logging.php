<?php
/*
	logging utilities
*/

namespace HalfMoon;

class Log {
	static function error($string) {
		error_log($string);
	}

	/* by directly writing to stderr, we avoid prefixing lines with [error]
	 * like error_log does */
	static function info($string) {
		$stderr = fopen("php://stderr", "w");
		/* match apache's logging style */
		fwrite($stderr, date("[D M d H:i:s Y] ") . $string . "\n");
		fclose($stderr);
	}

	static function warn($string) {
		$this->error($string);
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
