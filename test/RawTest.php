<?php

require(__DIR__ . "/../lib/halfmoon.php");

class RawTest extends PHPUnit_Framework_TestCase {
	public function testRaw() {
		$str = "<b>this is some text</b>";

		$this->assertEquals("&lt;b&gt;this is some text&lt;/b&gt;",
			h($str));

		$this->assertEquals("<b>this is some text</b>", (string)raw($str));
	}
}

?>
