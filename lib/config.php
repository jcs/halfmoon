<?php
/*
	configuration
*/

namespace HalfMoon;

class Config extends Singleton {
	private $session_store;

	public function set_session_store($store, $options = array()) {
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
}

?>
