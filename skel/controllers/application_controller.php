<?php
/*
	application controller from which all other controllers will extend.  use
	this for site-wide functions like authentication, before_filters, etc.
*/

class ApplicationController extends HalfMoon\ApplicationController {
	/* sessions are off by default to allow caching */
	static $session = "off";
}

?>
