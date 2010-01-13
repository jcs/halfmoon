<?php
/*
	URL router
*/

namespace HalfMoon;
use Closure;

class Router extends Singleton {
	public $routes = array();
	public $rootRoute = array();

    public static function initialize(Closure $initializer) {
        $initializer(parent::instance());
	}

	public function addRoute($route) {
		if (!is_array($route))
			die("invalid route of " . var_export($route));

		array_push($this->routes, $route);
	}

	public function routeRequest($url, $params) {
		/* strip leading and trailing slashes */
		$url = trim($url);
		$url = preg_replace("/^\/*/", "", preg_replace("/\/$/", "", $url));

		/* trim again just in case some were hiding */
		$url = trim($url);

		if ($url == "") {
			if (!$this->rootRoute)
				die("no root route defined");

			return $this->takeRoute($this->rootRoute, $url, $params);
		}

		$url_pieces = split("/", $url);

		foreach ($this->routes as $route) {
			$route_pieces = split("/", $route["url"]);

			/* can't possibly match, skip over it */
			if (count($route_pieces) != count($url_pieces))
				continue;

			$controller = $action = $id = "";

			$match = true;
			for ($x = 0; $x < count($route_pieces); $x++) {
				/* look for a condition */
				if (preg_match("/^:(.+)$/", $route_pieces[$x], $m)) {
					$reg = A((array)$route["conditions"], $m[1]);

					if ($reg && !preg_match($reg, $url_pieces[$x]))
						$match = false;
					else
						$$m[1] = $url_pieces[$x];
				}

				/* else it must match exactly */
				elseif ($route_pieces[$x] != $url_pieces[$x])
					$match = false;

				if (!$match)
					break;
			}

			if ($match) {
				if ($route["controller"])
					$controller = $route["controller"];

				if ($route["action"])
					$action = $route["action"];

				if ($route["id"])
					$id = $route["id"];

				if ($controller == "" || $action == "" || $id == "")
					die("matched but missing a component?");
				else
					return $this->takeRoute(array("controller" => $controller,
						"action" => $action, "id" => $id), $url, $params);
			}
		}

		die("can't understand url " . $url);
	}

	public function takeRoute($route, $url, $params) {
		if ($route["action"] == "")
			$route["action"] = "index";

		if ($route["controller"] == "")
			die("no controller specified");

		foreach ($route as $k => $v)
			$params[$k] = $v;

		$c = ucfirst($route["controller"]) . "Controller";
		$controller = new $c;
		$controller->params = $params;
		$controller->render_action($route["action"], array());
	}
}
