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
			throw new HalfMoonException("invalid route of "
				. var_export($route));

		array_push($this->routes, $route);
	}

	public function addRootRoute($route) {
		if (!is_array($route))
			throw new HalfMoonException("invalid root route of "
				. var_export($route, true));

		/* only one root route can match a particular condition */
		foreach ($this->rootRoutes as $rr)
			if (!array_diff_assoc((array)$rr, (array)$route["conditions"]))
				throw new HalfMoonException("cannot add second root route "
					. "with no conditions: " . var_export($route, true));

		array_push($this->rootRoutes, $route);
	}

	public function routeRequest($request) {
		$path_pieces = explode("/", $request->path);

		/* find and take the first matching route, storing route components in
		 * $params */
		if ($request->path == "") {
			if (!count($this->rootRoutes))
				throw new HalfMoonException("no root route defined");

			foreach ($this->rootRoutes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if (isset($route["conditions"]["hostname"]))
					if (!Utils::strcasecmp_or_preg_match(
					$route["conditions"]["hostname"], $request->hostname))
						continue;

				return $this->takeRoute($route, $request);
			}
		} else {
			foreach ($this->routes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if (isset($route["conditions"]["hostname"]))
					if (!Utils::strcasecmp_or_preg_match(
					$route["conditions"]["hostname"], $request->hostname))
						continue;

				/* trim slashes from route definition and bust it up into
				 * components */
				$route_pieces = explode("/", trim(preg_replace("/^\/*/", "",
					preg_replace("/\/$/", "", trim($route["url"])))));

				$match = true;
				for ($x = 0; $x < count($route_pieces); $x++) {
					/* look for a condition */
					if (preg_match("/^:(.+)$/", $route_pieces[$x], $m)) {
						$regex_or_string = isset($route["conditions"]) ?
							@Utils::A((array)$route["conditions"], $m[1]) :
							NULL;

						/* if the corresponding path piece isn't there and
						 * there is either no condition, or a condition that
						 * matches against a blank string, it's ok.  this lets
						 * controller/:action/:id match when the route is just
						 * "controller", assigning :action and :id to nothing */

						if ($regex_or_string == NULL ||
						Utils::strcasecmp_or_preg_match($regex_or_string,
						$path_pieces[$x]))
							/* store this named parameter (e.g. "/:blah" route
							 * on a path of "/hi" defines $route["blah"] to be
							 * "hi") */
							$route[$m[1]] = @$path_pieces[$x];
						else
							$match = false;
					}
					
					/* look for a glob condition */
					elseif (preg_match("/^\*(.+)$/", $route_pieces[$x], $m)) {
						/* concatenate the rest of the path as this one param */
						$u = "";
						for ($j = $x; $j < count($path_pieces); $j++)
							$u .= ($u == "" ? "" : "/") . $path_pieces[$j];

						$route[$m[1]] = $u;

						break;
					}

					/* else it must match exactly (case-insensitively) */
					elseif (@strcasecmp($route_pieces[$x], $path_pieces[$x])
					!= 0)
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

					return $this->takeRoute($route, $request);
				}
			}
		}

		/* still here, no routes matched */
		throw new RoutingException("no route for url \"" . $request->path . "\"");
	}

	public function takeRoute($route, $request) {
		/* we need at least a controller */
		if ($route["controller"] == "")
			throw new HalfMoonException("no controller specified");

		/* but we can deal with no action by calling the index action */
		if (!isset($route["action"]) || $route["action"] == "")
			$route["action"] = "index";

		/* store the parameters named in the route with data from the url,
		 * overriding anything passed by the user as get/post */
		foreach ($route as $k => $v)
			$request->params[$k] = $v;

		$c = ucfirst($route["controller"]) . "Controller";

		/* log some basic information */
		Log::info("Processing " . $c . "::" . $route["action"] . " (for "
			. $request->remote_ip() . ") [" . $request->request_method()
			. "]");

		$controller = new $c($request);
		$controller->render_action($route["action"], array());
	}
}

?>
