<?php
/*
	site-wide settings, loaded after framework

	should do per-environment setup like logging, tweaking php settings, etc.
*/

/* session settings, change according to your application requirements */
session_name("_%%APP_NAME%%_session");
session_set_cookie_params($lifetime = 0, $path = "/");

/* activate encrypted cookie storage; requires the mcrypt php extension */
HalfMoon\Config::set_session_store(
	"encrypted_cookie",

	/* you must define a random encryption key here of 32 characters.
	 * "openssl rand 16 -hex" will generate one for you. */
	array("encryption_key" => "%%COOKIE_ENCRYPTION_KEY%%")
);

/* a timezone is required for DateTime functions */
date_default_timezone_set("UTC");

/* environment-specific settings */
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

	/* and log all halfmoon activity */
	HalfMoon\Config::set_log_level("full");
}

elseif (HALFMOON_ENV == "production") {
	/* be quiet in production */

	/* don't display actual php error messages to the user, just generic error
	 * pages (see skel/500.html) */
	ini_set("display_errors", 0);

	/* only log processing time */
	HalfMoon\Config::set_log_level("short");

	/* uncomment to send emails of error backtraces and debugging info */
	# HalfMoon\Config::set_exception_notification_recipient("you@example.com");
	# HalfMoon\Config::set_exception_notification_subject("[%%APP_NAME%%]");
}

?>
