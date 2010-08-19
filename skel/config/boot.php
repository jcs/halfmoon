<?php
/*
	site-wide settings, loaded after framework

	should do per-environment setup like logging, tweaking php settings, etc.
*/

if (HALFMOON_ENV == "development") {
	/* be open and verbose during development */

	/* show errors in the browser */
	ini_set("display_errors", 1);

	/* setup a simple logging mechanism for activerecord, logging all sql
	 * queries to apache's error log */
	class LogLogLog {
		public function log($sql) {
			error_log("  " . $sql);
		}
	}

	$GLOBALS['ACTIVERECORD_LOG'] = true;
	$GLOBALS['ACTIVERECORD_LOGGER'] = new LogLogLog;
}

elseif (HALFMOON_ENV == "production") {
	/* be quiet in production */

	ini_set("display_errors", 0);
}

/* settings for all environments */
date_default_timezone_set("US/Chicago");

?>
