<?php

require(__DIR__ . "/../lib/halfmoon.php");

class RouterTest extends PHPUnit_Framework_TestCase {
	public function testSetupRoutes() {
		HalfMoon\Router::clearRoutes();

		$this->assertEquals(0, count(HalfMoon\Router::getRoutes()));
		$this->assertEquals(0, count(HalfMoon\Router::getRootRoutes()));

		$added_routes = 0;
		$added_root_routes = 0;

		HalfMoon\Router::addRoute(array(
			"url" => "logout",
			"controller" => "login",
			"action" => "logout2",
			"conditions" => array(
				"hostname" => "/^www(\d+)\.example\.(com|net)$/i"),
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => "logout",
			"controller" => "login",
			"action" => "logout",
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => "*globby",
			"controller" => "login",
			"action" => "globtest",
			"conditions" => array(
				"globby" => "/^globte.t$/"
			),
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => "stub/:controller/:action/:id",
			"action" => "stub_:action",
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => "zero_test/:id",
			"controller" => "zero_test",
			"action" => "show",
			"conditions" => array("id" => "/^[0-9]+$/"),
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => ":tag/:message",
			"controller" => "messages",
			"action" => "show_tagged",
			"conditions" => array("tag" => "/^[A-Za-z0-9_\-]+$/",
				"message" => "/^\d+$/")
		));
		$added_routes++;

		HalfMoon\Router::addRoute(array(
			"url" => ":controller/:action/*globbed",
			"conditions" => array("controller" => "someglob",
				"action" => "/^[a-z]+$/"),
		));
		$added_routes++;


		HalfMoon\Router::addRootRoute(array(
			"controller" => "root2",
			"conditions" => array("hostname" => "www.example2.com"),
		));
		$added_root_routes++;

		HalfMoon\Router::addRootRoute(array(
			"controller" => "root",
		));
		$added_root_routes++;


		$this->assertEquals($added_routes,
			count(HalfMoon\Router::getRoutes()));
		$this->assertEquals($added_root_routes,
			count(HalfMoon\Router::getRootRoutes()));
	}

	/**
	 * @depends testSetupRoutes
	 */
    public function testBasicRouteParsing() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/logout"));

		$this->assertEquals("login", $route["controller"]);
		$this->assertEquals("logout", $route["action"]);
		$this->assertEmpty($route["id"]);
    }

	/**
	 * @depends testSetupRoutes
	 */
    public function testWildcardRouting() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/stub/something/blah"));

		$this->assertEquals("something", $route["controller"]);
		$this->assertEquals("stub_blah", $route["action"]);
		$this->assertEmpty($route["id"]);

		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/stub/something"));

		$this->assertEquals("something", $route["controller"]);
		$this->assertEquals("stub_index", $route["action"]);
		$this->assertEmpty($route["id"]);
    }

	/**
	 * @depends testSetupRoutes
	 */
    public function testConditionsMatching() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/matchingtag/50"));

		$this->assertEquals("messages", $route["controller"]);
		$this->assertEquals("show_tagged", $route["action"]);
		$this->assertEmpty($route["id"]);
	}

	/**
	 * @depends testSetupRoutes
     * @expectedException HalfMoon\RoutingException
	 */
    public function testConditionsFail() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/matchingtag/50asdf"));
	}

	/**
	 * @depends testSetupRoutes
	 */
    public function testGlobRouting() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/someglob/blah/this/is/a/test"));

		$this->assertEquals("someglob", $route["controller"]);
		$this->assertEquals("blah", $route["action"]);
		$this->assertEquals("this/is/a/test", $route["globbed"]);

		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/globtest/"));

		$this->assertEquals("globtest", $route["globby"]);
		$this->assertEquals("login", $route["controller"]);
		$this->assertEquals("globtest", $route["action"]);
	}

	/**
	 * @depends testSetupRoutes
	 */
    public function testRoutingWithHostname() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("http://www5.example.com/logout"));

		$this->assertEquals("login", $route["controller"]);
		$this->assertEquals("logout2", $route["action"]);
		$this->assertEmpty($route["id"]);
    }

	/**
	 * @depends testSetupRoutes
	 */
    public function testRootRouting() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("/"));

		$this->assertEquals("root", $route["controller"]);
		$this->assertEquals("index", $route["action"]);
		$this->assertEmpty($route["id"]);
    }

	/**
	 * @depends testSetupRoutes
	 */
    public function testRootRoutingWithHostname() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("http://www.example2.com/"));

		$this->assertEquals("root2", $route["controller"]);
		$this->assertEquals("index", $route["action"]);
		$this->assertEmpty($route["id"]);
    }

	/**
	 * @depends testSetupRoutes
	 */
    public function testZeroRouting() {
		$route = HalfMoon\Router::routeRequest(
			$this->request_for("http://www.example2.com/zero_test/0"));

		$this->assertEquals("zero_test", $route["controller"]);
		$this->assertEquals("show", $route["action"]);
		$this->assertEquals("0", $route["id"]);
    }


	private function request_for($url) {
		if (preg_match("/^\//", $url))
			$url = "http://www.example.com" . $url;

		$req = new HalfMoon\Request($url, array(), array(), array(),
			microtime(true));
		return $req;
	}
}

?>
