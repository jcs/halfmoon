<?php

require(__DIR__ . "/../lib/halfmoon.php");

HalfMoon\Config::set_session_store("encrypted_cookie",
	array("encryption_key" => str_repeat("0", 32)));

class EncryptedCookieTest extends PHPUnit_Framework_TestCase {
	static $KEY = "ef55ede724792b59a04887f7956db4be";
	static $COOKIE = "_4m_session";

	public function setupSS() {
		$this->ss = new HalfMoon\EncryptedCookieSessionStore(static::$KEY);
		$this->ss->open("", static::$COOKIE);
	}

	public function testCookieEncryptionAndDecryption() {
		for ($z = 0; $z < 5000; $z++) {
			$this->setupSS();

			$ki = rand(20, 40);
			for ($k = "", $x = 0; $x++ < $ki; $k .= bin2hex(chr(mt_rand(0,255))))
				;

			$vi = rand(20, 500);
			for ($v = "", $x = 0; $x++ < $vi; $v .= bin2hex(chr(mt_rand(0,255))))
				;

			$data = var_export(array($k, $v), true);
			$this->ss->write("", $data);

			$this->setupSS();
			$dec_data = $this->ss->read("");
			$this->assertEquals($data, $dec_data);
		}
	}
}

?>
