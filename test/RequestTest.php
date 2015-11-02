<?php

require(__DIR__ . "/../lib/halfmoon.php");

class RequestTest extends PHPUnit_Framework_TestCase {
	public function testNormalRequest() {
		$req = new \HalfMoon\Request(
			"https://www.example.com/test/blah/?whatever=hello",
			array("whatever" => "hello"), array(), array(), time());

		$this->assertEquals("https", $req->scheme);
		$this->assertEquals("www.example.com", $req->host);
		$this->assertEquals(443, $req->port);
		$this->assertEquals("test/blah", $req->path);
		$this->assertEquals("whatever=hello", $req->query);
	}

	public function testWeakRequest() {
		$req = new \HalfMoon\Request(
			"http://a",
			array(), array(), array(), time());

		$this->assertEquals("http", $req->scheme);
		$this->assertEquals("a", $req->host);
		$this->assertEquals(80, $req->port);
		$this->assertEquals("", $req->path);
		$this->assertEquals("", $req->query);
	}

    public function testMaliciousRequest() {
		$req = new \HalfMoon\Request(
			"http://www.example.com/test/../notreally?test=hello",
			array("test" => "hello"), array(), array(), time());

		$this->assertEquals("notreally", $req->path);

		$req = new \HalfMoon\Request(
			"http://www.example.com/test/////asdf//",
			array("test" => "hello"), array(), array(), time());

		$this->assertEquals("test/asdf", $req->path);
    }
}

?>
