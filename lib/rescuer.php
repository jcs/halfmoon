<?php
/*
	error and exception handling, logging, and notification
*/

namespace HalfMoon;

class Rescuer {
	/* exceptions that won't trigger an email in notify_of_exception() */
	static $exceptions_to_ignore = array(
		'\HalfMoon\RoutingException',
		'\HalfMoon\InvalidAuthenticityToken'
	);

	static function error_handler($errno, $errstr, $errfile, $errline) {
		return Rescuer::shutdown_error_handler(array(
			"type" => $errno,
			"message" => $errstr,
			"file" => $errfile,
			"line" => $errline,
		));
	}

	/* handle after-shutdown errors (like parse errors) */
	static function shutdown_error_handler($error = null) {
		if (is_null($error))
			if (is_null($error = error_get_last()))
				return;

		/* if this shouldn't be reported at all, just bail */
		if (!((bool)($error["type"] & ini_get("error_reporting"))))
			return;

		$exception = new \ErrorException($error["message"], 0, $error["type"],
			$error["file"], $error["line"]);

		$title = $error["message"] . " in " . $error["file"] . " on line "
			. $error["line"];

		/* everything according to the error_reporting ini value should be
		 * logged */
		Rescuer::log_exception($exception, $title, $request = null);

		Rescuer::notify_of_exception($exception, $title, $request);

		/* if it's a major/fatal problem (according to
		 * http://php.net/manual/en/errorfunc.constants.php), then we should
		 * show the user an error page and exit */
		if (in_array($error["type"], array(E_ERROR, E_PARSE, E_CORE_ERROR,
		E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR)))
			return Rescuer::rescue_in_public($exception, $title, $request);
	}

	/* handle exceptions by logging them, notifying about them (if we're in
	 * production), and then showing the user an error page */
	static function rescue_exception($exception, $request = null) {
		$title = get_class($exception);

		/* activerecord includes the stack trace in the message, so strip it
		 * out */
		if ($exception instanceof \ActiveRecord\DatabaseException)
			$title .= ": " . preg_replace("/\nStack trace:.*/s", "",
				$exception->getMessage());
		elseif ($exception->getMessage())
			$title .= ": " . $exception->getMessage() . " in "
				. $exception->getFile() . " on line " . $exception->getLine();

		Rescuer::log_exception($exception, $title, $request);

		Rescuer::notify_of_exception($exception, $title, $request);

		return Rescuer::rescue_in_public($exception, $title, $request);
	}

	/* log an exception, mail it, try to show the user something */
	static function log_exception($exception, $title, $request) {
		Log::error($title . ":");

		if (!is_null($exception) && is_object($exception))
			foreach ($exception->getTrace() as $call)
				Log::error("    "
					. (isset($call["file"]) ? $call["file"] : $call["class"])
					. ":"
					. (isset($call["line"]) ? $call["line"] : "")
					. " in " . $call["function"] . "()");

		return;
	}

	/* mail off the details of the exception */
	static function notify_of_exception($exception, $title, $request) {
		if (HALFMOON_ENV != "production")
			return;

		foreach (static::$exceptions_to_ignore as $e)
			if ($exception instanceof $e)
				return;

		$config = Config::instance();

		if (!isset($config->exception_notification_recipient))
			return;

		/* render the text template and mail it off */
		@ob_end_clean();
		@ob_start();
		@require(HALFMOON_ROOT . "/halfmoon/lib/rescue.ptxt");
		$mail_body = trim(@ob_get_contents());
		@ob_end_clean();

		@mail($config->exception_notification_recipient,
			$config->exception_notification_subject . " " . $title,
			$mail_body);

		return;
	}

	/* return a friendly error page to the user (or a full one with debugging
	 * if we're in development mode with display_errors turned on) */
	static function rescue_in_public($exception, $title, $request) {
		/* kill all buffered output */
		while (count(@ob_list_handlers()))
			@ob_end_clean();

		if (HALFMOON_ENV == "development" && ini_get("display_errors"))
			require_once(__DIR__ . "/rescue.phtml");
		else {
			/* production mode, try to handle gracefully */

			if ($exception instanceof \HalfMoon\RoutingException) {
				header($_SERVER["SERVER_PROTOCOL"] . " 404 File Not Found");

				if (file_exists($f = HALFMOON_ROOT . "/public/404.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>File Not Found</title>
					</head>
					<body>
					<h1>File Not Found</h1>
					The file you requested could not be found.  An additional error
					occured while processing the error document.
					</body>
					</html>
					<?php
				}
			}
			
			elseif ($exception instanceof \HalfMoon\InvalidAuthenticityToken) {
				/* be like rails and give the odd 422 status */
				header($_SERVER["SERVER_PROTOCOL"] . " 422 Unprocessable Entity");

				if (file_exists($f = HALFMOON_ROOT . "/public/422.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>Change Rejected</title>
					</head>
					<body>
					<h1>Change Rejected</h1>
					The change you submitted was rejected due to a security
					problem.  An additional error occured while processing the
					error document.
					</body>
					</html>
					<?php
				}
			}
			
			else {
				header($_SERVER["SERVER_PROTOCOL"] . " 500 Server Error");

				if (file_exists($f = HALFMOON_ROOT . "/public/500.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>Application Error</title>
					</head>
					<body>
					<h1>Application Error</h1>
					An internal application error occured while processing your
					request.  Additionally, an error occured while processing the
					error document.
					</body>
					</html>
					<?php
				}
			}
		}

		/* that's it, end of the line */
		exit;
	}
}

/* make traditional errors throw exceptions so we can handle everything in one
 * place */
set_error_handler(array("\\HalfMoon\\Rescuer", "error_handler"));

/* catch errors on the cleanup that we couldn't handle at runtime */
register_shutdown_function(array("\\HalfMoon\\Rescuer", "shutdown_error_handler"));

/* and catch all exceptions */
set_exception_handler(array("\\HalfMoon\\Rescuer", "rescue_exception"));

?>
