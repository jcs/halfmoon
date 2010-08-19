<?php
/*
 * secure cookie-based session storage, based on the EncryptedCookieStore rails
 * plugin (http://github.com/FooBarWidget/encrypted_cookie_store)
 *
 * Copyright (c) 2010 joshua stein <jcs@jcs.org>
 *
 * process of storing session data with a given key:
 * 1. create a random IV
 * 2. encrypt the IV with the key in 128-bit AES in ECB mode
 * 3. create a SHA1 HMAC (with the key) of the session data
 * 4. encrypt the HMAC and session data together with the key in 256-bit AES in
 *    CFB mode
 * 5. store the base64-encoded encrypted IV and encrypted HMAC+data as a cookie
 *
 * to read the encrypted data on the next visit:
 * 1. base64-decode the IV and data
 * 2. decrypt the IV with the key
 * 3. decrypt the HMAC+data with the key and decrypted IV
 * 4. verify that the HMAC of the decrypted data matches the decrypted HMAC
 * 5. return the plaintext session data
 *
 */

namespace HalfMoon;

class EncryptedCookieSessionStore {
	/* the most amount of data we can store in the cookie (post-encryption) */
	static $MAX_COOKIE_LENGTH = 4096;

	private $cookie_name = "";
	private $key = null;
	private $data = array();

	public function __construct($key) {
		if (!function_exists("mcrypt_encrypt"))
			throw new \HalfMoon\HalfMoonException("mcrypt extension not "
				. "installed");
		if (strlen($key) != 32)
			throw new \HalfMoon\HalfMoonException("cookie encryption key must "
				. "be 32 characters long");

		$this->key = pack("H*", $key);
	}

	public function open($savepath, $name) {
		$this->cookie_name = $name;

		return true;
    }

    public function read($id) {
		list($e_iv, $e_data) = explode("--", $_COOKIE[$this->cookie_name], 2);

		if (strlen($e_iv) && strlen($e_data)) {
			$e_iv = base64_decode($e_iv);
			$e_data = base64_decode($e_data);

			$iv = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->key, $e_iv,
				MCRYPT_MODE_ECB);

			$data_and_hmac = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->key,
				$e_data, MCRYPT_MODE_CFB, $iv);

			list($hmac, $data) = explode("--", $data_and_hmac, 2);

			if (!strlen($hmac) || !strlen($data))
				throw new \HalfMoon\InvalidCookieData("no HMAC or data");

			if (hash_hmac("sha1", $data, $this->key, $raw = true) !== $hmac)
				throw new \HalfMoon\InvalidCookieData("invalid HMAC");

			return $data;
		}

		return "";
	}

    public function write($id, $data) {
		/* generate random iv for aes-256-cfb */
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256,
			MCRYPT_MODE_CFB), MCRYPT_RAND);

		/* encrypt the iv with aes-128-ecb */
		$e_iv = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->key, $iv,
			MCRYPT_MODE_ECB);

		$hmac = hash_hmac("sha1", $data, $this->key, $raw_output = true);

		/* encrypt the hmac and data with aes-256-cfb, using the random iv */
		$e_data = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->key,
			$hmac . "--" . $data, MCRYPT_MODE_CFB, $iv);

		$cookie = base64_encode($e_iv) . "--" . base64_encode($e_data);

		if (strlen($cookie) > static::$MAX_COOKIE_LENGTH)
			throw new \HalfMoon\InvalidCookieData("cookie data too long ("
				. strlen($cookie) . " > " . static::$MAX_COOKIE_LENGTH . ")");

		setcookie(
			$this->cookie_name,
			$cookie,
			time() + ini_get("session.cookie_lifetime"),
			ini_get("session.cookie_path"),
			ini_get("session.cookie_domain"),
			ini_get("session.cookie_secure"),
			ini_get("session.cookie_httponly")
		);

		return true;
	}

    public function destroy($id) {
		return true;
	}

    public function gc($maxlife) {
		return true;
	}

    public function close() {
		return true;
	}
}

?>