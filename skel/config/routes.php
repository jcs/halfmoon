<?php
/*
	url-to-controller mapping file.  first-matching route wins.

	a specific route example:

		HalfMoon\Router::instance()->addRoute(array(
			"url" => "posts/:id",
			"controller" => "posts",
			"action" => "show",
			"conditions" => array("id" => '/^\d+$/'),
		));

	a root route to match "/"
	
		HalfMoon\Router::instance()->rootRoute = array(
			"controller" => "posts"
		);
*/

/* generic catch-all route to match everything else */
HalfMoon\Router::instance()->addRoute(array(
	"url" => ":controller/:action/:id",
));

?>
