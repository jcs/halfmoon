<?php
/*
	URL router
*/

namespace HalfMoon;

use Closure;

class Router extends Singleton {
	private $routes = array();
	private $rootRoutes = array();

	static $DEFAULT_ACTION = "index";

	public static function initialize(Closure $initializer) {
		$initializer(parent::instance());
	}

	public static function addRoute($route) {
		if (!is_array($route))
			throw new HalfMoonException("invalid route of "
				. var_export($route));

		array_push(parent::instance()->routes, $route);
	}

	public static function addRootRoute($route) {
		if (!is_array($route))
			throw new HalfMoonException("invalid root route of "
				. var_export($route, true));

		/* only one root route can have no conditions, and any additional root
		 * routes must have different conditions */
		$c = array_key_exists("conditions", $route) ? $route["conditions"] :
			array();
		foreach (parent::instance()->rootRoutes as $rr) {
			$rrc = array_key_exists("conditions", $rr) ? $rr["conditions"] :
				array();

			if ((empty($c) && empty($rrc)) ||
			(!empty($c) && !empty($rrc) && !array_diff_assoc($c, $rrc)))
				throw new HalfMoonException("cannot add second root route "
					. "with no conditions: " . var_export($route, true)
					. " with existing root route: "
					. var_export($rr, true) . ")");
		}

		array_push(parent::instance()->rootRoutes, $route);
	}

	public static function clearRoutes() {
		parent::instance()->routes = array();
		parent::instance()->rootRoutes = array();
	}

	public static function getRoutes() {
		return parent::instance()->routes;
	}

	public static function getRootRoutes() {
		return parent::instance()->rootRoutes;
	}

	public static function routeRequest($request) {
		$path_pieces = array_map(function($piece) {
			return urldecode($piece);
		}, explode("/", $request->path));

		$chosen_route = null;

		/* find and take the first matching route, storing route components in
		 * $params */
		if ($request->path == "") {
			if (empty(parent::instance()->rootRoutes))
				throw new RoutingException("no root route defined");

			foreach (parent::instance()->rootRoutes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if (isset($route["conditions"]["hostname"]))
					if (!Utils::strcasecmp_or_preg_match(
					$route["conditions"]["hostname"], $request->host))
						continue;

				$chosen_route = $route;
				break;
			}
		} else {
			foreach (parent::instance()->routes as $route) {
				/* verify virtual host matches if there's a condition on it */
				if (isset($route["conditions"]["hostname"]))
					if (!Utils::strcasecmp_or_preg_match(
					$route["conditions"]["hostname"], $request->host))
						continue;

				/* trim slashes from route definition and bust it up into
				 * components */
				$route_pieces = explode("/", trim(preg_replace("/^\/*/", "",
					preg_replace("/\/$/", "", trim($route["url"])))));

				$match = true;
				$did_glob = false;
				for ($x = 0; $x < count($route_pieces); $x++) {
					/* look for a condition */
					if (preg_match("/^:(.+)$/", $route_pieces[$x], $m)) {
						$regex_or_string = isset($route["conditions"]) ?
							@Utils::A((array)$route["conditions"], $m[1]) :
							NULL;

						/* if the corresponding path piece isn't there and
						 * there is either no condition, or a condition that
						 * matches against a blank string, it's ok.  this lets
						 * a route of "controller/:action/:id" match when the
						 * path is just "controller", assigning $DEFAULT_ACTION
						 * to :action and nothing to :id */

						if ($regex_or_string == NULL ||
						Utils::strcasecmp_or_preg_match($regex_or_string,
						Utils::is_blank($path_pieces[$x]) ? "" :
						$path_pieces[$x])) {
							if (isset($route[$m[1]]) &&
							preg_match("/^(.*):(.+)$/", $route[$m[1]], $n))
								/* route has a set parameter, but it wants to
								 * include the matching piece from the path in
								 * its parameter */
								$route[$m[1]] = $n[1] .
									(isset($path_pieces[$x]) ?
									$path_pieces[$x] :
									static::$DEFAULT_ACTION);
							else
								/* store this named parameter (e.g. "/:blah"
								 * route on a path of "/hi" defines
								 * $route["blah"] to be "hi") */
								$route[$m[1]] = @$path_pieces[$x];
						} else
							$match = false;
					}

					/* look for a glob condition */
					elseif (preg_match("/^\*(.+)$/", $route_pieces[$x], $m)) {
						$regex_or_string = isset($route["conditions"]) ?
							@Utils::A((array)$route["conditions"], $m[1]) :
							NULL;

						/* concatenate the rest of the path as this one param */
						$u = "";
						for ($j = $x; $j < count($path_pieces); $j++)
							$u .= ($u == "" ? "" : "/") . $path_pieces[$j];

						if ($regex_or_string == NULL ||
						Utils::strcasecmp_or_preg_match($regex_or_string, $u)) {
							$route[$m[1]] = $u;
							$did_glob = true;
						}
						else
							$match = false;

						break;
					}

					/* else it must match exactly (case-insensitively) */
					elseif (@strcasecmp($route_pieces[$x], $path_pieces[$x])
					!= 0)
						$match = false;

					if (!$match)
						break;
				}

				if ($match && !$did_glob && count($path_pieces) >
				count($route_pieces)) {
					/* path was /controller/action/id/something/else but this
					 * route only matched /controller/action/id, consider this
					 * a non-match */
					$match = false;
				}

				if ($match) {
					/* we need at least a valid controller */
					if ($route["controller"] == "")
						continue;

					/* note that we pass the action to the controller even if it
					 * doesn't exist, that way at least the backtrace will show
					 * what controller we resolved it to */

					$chosen_route = $route;
					break;
				}
			}
		}

		if (!$chosen_route)
			throw new RoutingException("no route for url \"" . $request->path
				. "\"");

		/* we need at least a controller */
		if ($chosen_route["controller"] == "")
			throw new RoutingException("no controller specified");

		/* and a valid controller name */
		if (!preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*$/",
		$chosen_route["controller"]))
			throw new RoutingException("invalid controller matched");

		/* but we can deal with no action by calling the index action */
		if (!isset($chosen_route["action"]) || $chosen_route["action"] == "")
			$chosen_route["action"] = static::$DEFAULT_ACTION;

		return $chosen_route;
	}

	public static function takeRouteForRequest($request) {
		$route = parent::instance()->routeRequest($request);
		return static::takeRoute($route, $request);
	}

	public static function takeRoute($route, $request) {
		/* store the parameters named in the route with data from the url,
		 * overriding anything passed by the user as get/post */
		foreach ($route as $k => $v)
			$request->params[$k] = $v;

		/* camel_case -> CamelCaseController */
		$c = str_replace(" ", "",
			ucwords(str_replace("_", " ", $route["controller"])))
			. "Controller";

		if (!class_exists($c))
			throw new RoutingException("controller " . $c . " does not exist");

		/* log some basic information */
		if (Config::log_level_at_least("full"))
			Log::info("Processing " . $c . "::" . $route["action"] . " (for "
				. $request->remote_ip() . ") [" . $request->request_method()
				. "]");

		$request->start_times["app"] = microtime(true);

		$controller = new $c($request);
		$controller->render_action($route["action"]);
	}
}

?>
