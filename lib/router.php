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
			throw new RoutingException("invalid route of " . var_export($route));

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
				throw new RoutingException("no root route defined");

			return $this->takeRoute($this->rootRoute, $url, $params);
		}

		$url_pieces = explode("/", $url);

		foreach ($this->routes as $route) {
			$route_pieces = explode("/", $route["url"]);

			$match = true;
			for ($x = 0; $x < count($route_pieces); $x++) {
				/* look for a condition */
				if (preg_match("/^:(.+)$/", $route_pieces[$x], $m)) {
					$reg = A((array)$route["conditions"], $m[1]);

					if ($reg && !preg_match($reg, $url_pieces[$x]))
						$match = false;
					else
						/* store this named parameter (e.g. "/:blah" route on a
						 * url of "/hi" defines $route["blah"] to be "hi") */
						$route[$m[1]] = $url_pieces[$x];
				}

				/* else it must match exactly */
				elseif ($route_pieces[$x] != $url_pieces[$x])
					$match = false;

				if (!$match)
					break;
			}

			if ($match) {
				/* we need at least a valid controller */
				if ($route["controller"] == "" ||
				!class_exists(ucfirst($route["controller"]) . "Controller"))
					continue;

				/* note that we pass the action to the controller even if it
				 * doesn't exist, that way at least the backtrace will show
				 * what controller we resolved it to */

				return $this->takeRoute($route, $url, $params);
			}
		}

		throw new RoutingException("no route for url \"" . $url . "\"");
	}

	public function takeRoute($route, $url, $params) {
		/* we need at least a controller */
		if ($route["controller"] == "")
			throw new RoutingException("no controller specified");

		/* but we can deal with no action by calling the index action */
		if ($route["action"] == "")
			$route["action"] = "index";

		/* store get and post vars in $params first according to php's
		 * variables_order setting (EGPCS by default) */
		foreach (str_split(ini_get("variables_order")) as $vtype) {
			$varray = null;

			switch (strtoupper($vtype)) {
			case "P":
				$varray = &$_POST;
				break;
			case "G":
				$varray = &$_GET;
				break;
			}

			if ($varray)
				foreach ($varray as $k => $v)
					$params[$k] = $v;
		}

		/* then store the parameters named in the route with data from the url,
		 * overriding anything passed by the user as get/post */
		foreach ($route as $k => $v)
			$params[$k] = $v;

		$c = ucfirst($route["controller"]) . "Controller";
		$controller = new $c;
		$controller->params = $params;
		$controller->render_action($route["action"], array());
	}
}
