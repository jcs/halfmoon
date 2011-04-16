<?php

require(__DIR__ . "/../lib/halfmoon.php");

class OptionsHashTest extends PHPUnit_Framework_TestCase {
    public function testVerifyMethod() {
		$options = array(
			array(
				"only" => array("destroy", "update"),
				"method" => "post",
				"redirect_to" => "/admin/assets/"
			),
			array(
				"only" => "index",
				"method" => "get",
			),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"destroy", $options);
		$this->assertEquals(
			array(array("method" => "post", "redirect_to" => "/admin/assets/")),
			$ret
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"blah", $options);
		$this->assertEquals(array(), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array(array("method" => "get")), $ret);
    }

	public function testLayout() {
		$options = array(
			"ajax" => array("only" => "/^stub_/"),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"stub_blah", $options);
		$this->assertEquals(array("ajax"), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array(), $ret);


		$options = array(
			"normal" => array("except" => "/^stub_/"),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"stub_blah", $options);
		$this->assertEquals(array(), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array("normal"), $ret);


		$options = array(
			"false" => array("only" => array("stub", "blah")),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"stub", $options);
		$this->assertEquals(array("false"), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"stub2", $options);
		$this->assertEquals(array(), $ret);
	}

	public function testSingle() {
		$options = array("some_filter");

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"some_action", $options);
		$this->assertEquals(array("some_filter"), $ret);


		$options = "somethingelse";

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array("somethingelse"), $ret);
	}

	public function testFilters() {
		$options = "some_other_filter";

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"some_action", $options);
		$this->assertEquals(array("some_other_filter"), $ret);

		$options = array(
			"some_filter",
			"some_other_filter",
			"but_not_this_filter" => array("only" => array("/^bl.h$/", "flot")),
			"and_this_filter" => array("except" => "blah"),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"some_action", $options);
		$this->assertEquals(array("some_filter", "some_other_filter",
			"and_this_filter"), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"blah", $options);
		$this->assertEquals(array("some_filter", "some_other_filter",
			"but_not_this_filter"), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"flot", $options);
		$this->assertEquals(array("some_filter", "some_other_filter",
			"but_not_this_filter", "and_this_filter"), $ret);
	}

	public function testSessions() {
		$options = array(
			"on" => array("only" => array("login")),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"login", $options);
		$this->assertEquals(array("on"), $ret);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array(), $ret);


		$options = array(
			"off",
			"on" => array("only" => array("login")),
		);

		$ret = \HalfMoon\Utils::options_for_key_from_options_hash(
			"index", $options);
		$this->assertEquals(array("off"), $ret);
	}

	/* this is used in protect_from_forgery and the like */
	public function testBooleanOption() {
		$options = array("only" => array("login"));

		$ret = \HalfMoon\Utils::option_applies_for_key("login", $options);
		$this->assertTrue($ret);

		$ret = \HalfMoon\Utils::option_applies_for_key("blah", $options);
		$this->assertFalse($ret);
	}
}

?>
