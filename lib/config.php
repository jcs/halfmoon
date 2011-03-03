<?php
/*
	configuration
*/

namespace HalfMoon;

class Config extends Singleton {
	public $activerecord;
	public $db_config;

	public $exception_notification_recipient;
	public $exception_notification_subject;

	public static function load_db_config() {
		if (Config::instance()->db_config)
			return Config::instance()->db_config;

		$db_config = parse_ini_file(HALFMOON_ROOT . "/config/db.ini", true);

		if (!isset($db_config[HALFMOON_ENV]))
			throw new HalfMoon\ConfigException("no database configuration "
				. "found for \"" . HALFMOON_ENV . "\" environment");

		Config::instance()->db_config = array_merge(array(
			"adapter"  => "mysql",
			"username" => "username",
			"password" => "password",
			"hostname" => "localhost",
			"database" => "database",
			"socket"   => "",
			"port" => 3306,
		), $db_config[HALFMOON_ENV]);

		return Config::instance()->db_config;
	}

	public static function initialize_activerecord() {
		$db = Config::instance()->load_db_config();

		Config::instance()->activerecord = \ActiveRecord\Config::instance();
		Config::instance()->activerecord->set_model_directory(realpath(HALFMOON_ROOT
			. "/models/"));

		if ($db["socket"] == "")
			$host = $db["hostname"] . ":" . $db["port"];
		else
			$host = "unix(" . $db["socket"] . ")";

		/* we aren't using php-ar's environments, only "development" which
		 * corresponds to whatever HALFMOON_ENV is set to */
		Config::instance()->activerecord->set_connections(array(
			"development" => $db["adapter"] . "://" . $db["username"] . ":"
				. $db["password"] . "@" . $host . "/" . $db["database"]
		));

		/* support old globals for logging */
		if ($GLOBALS["ACTIVERECORD_LOG"]) {
			Config::instance()->activerecord->set_logging(true);
			Config::instance()->activerecord->set_logger(
				$GLOBALS["ACTIVERECORD_LOGGER"]);
		}
	}

	public static function set_session_store($store, $options = array()) {
		switch (strtolower($store)) {
		case "encrypted_cookie":
			require_once(HALFMOON_ROOT
				. "/halfmoon/lib/session_store/encrypted_cookie.php");

			ini_set("session.save_handler", "user");

			/* TODO: warn about suhosin stuff? */

			$session = new \HalfMoon\EncryptedCookieSessionStore(
				$options["encryption_key"]);
			session_set_save_handler(
				array($session, "open"),
				array($session, "close"),
				array($session, "read"),
				array($session, "write"),
				array($session, "destroy"),
				array($session, "gc")
			);

			break;

		case "default":
			ini_set("session.save_handler", "files");
			break;

		default:
			throw new \HalfMoon\HalfMoonException("unknown session store: "
				. $store);
		}
	}

	public static function set_exception_notification_recipient($recipient) {
		Config::instance()->exception_notification_recipient = $recipient;
	}

	public static function set_exception_notification_subject($subject) {
		Config::instance()->exception_notification_subject = $subject;
	}
}

?>
