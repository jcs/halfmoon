<?php
/*
	url-to-controller mapping file.  first-matching route wins.
	
	for empty urls ("/"), only root routes (added with addRootRoute) will be
	matched against.

	a specific route example to only match ids that are valid numbers:

		HalfMoon\Router::addRoute(array(
			"url" => "posts/:id",
			"controller" => "posts",
			"action" => "show",
			"conditions" => array("id" => '/^\d+$/'),
		));

	a root route to match "/":

		HalfMoon\Router::addRootRoute(array(
			"controller" => "posts"
		));

	another root route on a specific virtual host to map to a different action
	(this would have to be defined before the previous root route, since the
	previous one has no conditions and would match all root urls):

		HalfMoon\Router::addRootRoute(array(
			"controller" => "posts",
			"action" => "devindex",
			"conditions" => array("hostname" => "dev"),
		));
*/

/* generic catch-all route to match everything else */
HalfMoon\Router::addRoute(array(
	"url" => ":controller/:action/:id",
));

?>
