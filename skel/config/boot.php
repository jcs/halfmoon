<?php
/*
	early initialization of site-wide settings, loaded after halfmoon framework
	but before activerecord is initialized.

	per-environment setup like logging, tweaking php settings, etc. can be done
	here.  any code requiring activerecord or needing to be done after
	everything is initialized should be done in config/application.php.
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

	/* log all activerecord queries and values */
	HalfMoon\Config::set_activerecord_log_level("full");

	/* log all halfmoon activity */
	HalfMoon\Config::set_log_level("full");
}

elseif (HALFMOON_ENV == "production") {
	/* be quiet in production */

	/* don't display actual php error messages to the user, just generic error
	 * pages (see skel/500.html) */
	ini_set("display_errors", 0);

	/* do not log any activerecord queries */
	HalfMoon\Config::set_activerecord_log_level("none");

	/* only log halfmoon processing times with urls */
	HalfMoon\Config::set_log_level("short");

	/* perform file caching for controllers that request it, and store files in
	 * this directory (must be writable by web server user running halfmoon */
	HalfMoon\Config::set_cache_store_path(HALFMOON_ROOT . "/public/cache");

	/* uncomment to send emails of error backtraces and debugging info */
	# HalfMoon\Config::set_exception_notification_recipient("you@example.com");
	# HalfMoon\Config::set_exception_notification_subject("[%%APP_NAME%%]");
}

?>
