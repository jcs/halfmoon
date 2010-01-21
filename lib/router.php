<?php
/*
	URL router
*/

namespace HalfMoon;

use Closure;

class Router extends Singleton {
	public $routes = array();
	public $rootRoutes = array();

    public static function initialize(Closure $initializer) {
        $initializer(parent::instance());
	}

	public function addRoute($route) {
		if (!is_array($route))
			throw new HalfmoonException("invalid route of "
				. var_export($route));

		array_push($this->routes, $route);
	}

	public function addRootRoute($route) {
		if (!is_array($route))
			throw new HalfmoonException("invalid root route of "
				. var_export($route));

		/* only one root route can match a particular condition */
		foreach ($this->rootRoutes as $rr)
			if (!array_diff_assoc((array)$rr, (array)$route["conditions"]))
				throw new HalfmoonException("cannot add second root route "
					. "with no conditions: " . var_export($route));

		array_push($this->rootRoutes, $route);
	}

	public function routeRequest($url, $params, $hostname) {
		/* strip leading and trailing slashes, then again in case some were
		 * hiding */
		$url = trim(preg_replace("/^\/*/", "", preg_replace("/\/$/", "",
			trim($url))));

		$url_pieces = explode("/", $url);

		/* store some special variables in $params */
		$params["hostname"] = $hostname;

		/* find and take the first matching route, storing route components in
		 * $params */
		if ($url == "") {
			if (!count($this->rootRoutes))
				throw new HalfmoonException("no root route defined");

			foreach ($this->rootRoutes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if ($route["conditions"]["hostname"])
					if (!strcasecmp_or_preg_match($route["conditions"]["hostname"],
					$hostname))
						continue;

				return $this->takeRoute($route, $url, $params);
			}
		} else {
			foreach ($this->routes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if ($route["conditions"]["hostname"])
					if (!strcasecmp_or_preg_match($route["conditions"]["hostname"],
					$hostname))
						continue;

				/* trim slashes from route definition and bust it up into
				 * components */
				$route_pieces = explode("/", trim(preg_replace("/^\/*/", "",
					preg_replace("/\/$/", "", trim($route["url"])))));

				$match = true;
				for ($x = 0; $x < count($route_pieces); $x++) {
					/* look for a condition */
					if (preg_match("/^:(.+)$/", $route_pieces[$x], $m)) {
						$reg_or_string = A((array)$route["conditions"], $m[1]);

						if ($reg_or_string &&
						!strcasecmp_or_preg_match($reg_or_string,
						$url_pieces[$x]))
							$match = false;
						else
							/* store this named parameter (e.g. "/:blah" route
							 * on a url of "/hi" defines $route["blah"] to be
							 * "hi") */
							$route[$m[1]] = $url_pieces[$x];
					}

					/* else it must match exactly (case-insensitively) */
					elseif (strcasecmp($route_pieces[$x], $url_pieces[$x]) != 0)
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
		}

		/* still here, no routes matched */
		throw new RoutingException("no route for url \"" . $url . "\"");
	}

	public function takeRoute($route, $url, $params) {
		/* we need at least a controller */
		if ($route["controller"] == "")
			throw new HalfmoonException("no controller specified");

		/* but we can deal with no action by calling the index action */
		if ($route["action"] == "")
			$route["action"] = "index";

		/* store get and post vars in $params first according to php's
		 * variables_order setting (EGPCS by default) */
		foreach (str_split(ini_get("variables_order")) as $vtype) {
			$varray = null;

			switch (strtoupper($vtype)) {
			case "P":
				$varray = $_POST;
				break;
			case "G":
				$varray = $_GET;
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
