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
		$this->error($string);
	}

	/* print_r() to the error log */
	static function error_log_r($param) {
		array_map("error_log", explode("\n", print_r($param, true)));
	}
}

class ActiveRecordLogger {
	private $logging_queries;
	private $logging_values;

	function __construct($log_level = -1) {
		if ($log_level == -1)
			$log_level = Config::$DEFAULT_ACTIVERECORD_LOG_LEVEL;

		$this->logging_queries = false;
		$this->logging_values = false;

		if ($log_level >= Config::$LOG_LEVELS["short"])
			$this->logging_queries = true;

		if ($log_level >= Config::$LOG_LEVELS["full"])
			$this->logging_values = true;
	}

	function log($sql, &$values = array()) {
		if (!$this->logging_queries)
			return;

		if ($this->logging_values) {
			$x = 0;
			$sql = preg_replace_callback("/(\?)/", function($s)
				use (&$x, &$values) {
					$r = "";
					if ($values[$x] === NULL)
						$r = "NULL";
					else
						$r = "[" . $values[$x] . "]";

					$x++;
					return $r;
				}, $sql);

			reset($values);
		}

		Log::info("  " . $sql);
	}
}

?>
