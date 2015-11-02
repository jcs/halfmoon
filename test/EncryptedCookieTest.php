<?php

require(__DIR__ . "/../lib/halfmoon.php");

HalfMoon\Config::set_session_store("encrypted_cookie",
	array("encryption_key" => str_repeat("0", 32)));

class EncryptedCookieTest extends PHPUnit_Framework_TestCase {
	static $str = "australia's darrell lea soft eating liquorice";
	static $key = "3d737148b5d7c1a08e0e92d26f8d020b";
	static $cookie = "test";

	public function setupSS($key, $cookie) {
		$this->ss = new HalfMoon\EncryptedCookieSessionStore($key);
		$this->ss->open("", $cookie);
	}

	public function testCookieEncryptionAndDecryption() {
		for ($z = 0; $z < 5000; $z++) {
			$key = bin2hex(openssl_random_pseudo_bytes(16));
			$this->setupSS($key, "test_" . $z);

			$ki = rand(20, 40);
			for ($k = "", $x = 0; $x++ < $ki; $k .= bin2hex(chr(mt_rand(0,255))))
				;

			$vi = rand(20, 500);
			for ($v = "", $x = 0; $x++ < $vi; $v .= bin2hex(chr(mt_rand(0,255))))
				;

			$data = var_export(array($k, $v), true);
			$this->ss->write("", $data);

			$this->setupSS($key, "test_" . $z);
			$dec_data = $this->ss->read("");
			$this->assertEquals($data, $dec_data);
		}
	}

	public function testExistingDecryption() {
		$this->setupSS(static::$key, static::$cookie);
		$this->ss->write("", static::$str);
		$enc = $_COOKIE[static::$cookie];
		$this->assertEquals(0, preg_match("/liquorice/", $enc));

		$this->setupSS(static::$key, static::$cookie);
		$_COOKIE[static::$cookie] = $enc;
		$this->assertEquals(static::$str, $this->ss->read(""));
	}

	public function testBadKey() {
		$this->setupSS(static::$key, static::$cookie);
		$this->ss->write("", static::$str);
		$enc = $_COOKIE[static::$cookie];

		$this->setupSS(str_replace("3", "4", static::$key), static::$cookie);
		$_COOKIE[static::$cookie] = $enc;
		$this->assertEquals("", $this->ss->read(""));
	}

	/**
	 * @expectedException HalfMoon\InvalidCookieData
	 */
	public function testBadData() {
		$this->setupSS(static::$key, static::$cookie);
		$this->ss->write("", static::$str);
		$enc = $_COOKIE[static::$cookie];

		$this->setupSS(static::$key, static::$cookie);
		$_COOKIE[static::$cookie] = substr($enc, 0, strlen($enc) - 5);
		$this->assertEquals("", $this->ss->read(""));
	}
}

?>
