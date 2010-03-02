<?php

namespace HalfMoon;

class ApplicationController {
	/* array of methods to call before processing any actions, bailing if any
	 * return false
	 * e.g. static $before_filter = array(
	 *			"validate_logged_in_user",
	 *			array("validate_admin", "only" => array("create")),
	 *			...
	 */
	static $before_filter = array();

	/* array of arrays to verify before processing any actions
	 * e.g. static $verify = array(
	 *          array("method" => "post",
	 *              "only" => array("login"),
	 *              "redirect_to" => "/",
	 *          ),
	 *          array("method" => "get",
	 *          ...
	 */
	static $verify = array();

	/* per-controller session options, can be "off", "on", or a per-action
	 * setting like: array("on", "only" => array("foo", "bar")) */
	static $session = "";

	/* specify a different layout than controller name or application */
	static $layout = array();

	/* protect all (or specific actions passed as an array) actions from
	 * forgery */
	static $protect_from_forgery = true;

	public $request = array();
	public $params = array();
	public $locals = array();

	private $did_render = false;
	private $did_layout = false;

	public function __construct($request) {
		$this->request = $request;
		$this->params = &$request->params;
	}

	/* turn local class variables into $variables when rendering views */
	public function __set($name, $value) {
		$this->locals[$name] = $value;
	}

	public function __get($name) {
		return $this->locals[$name];
	}

	/* store an error in the session to be printed on the next view with the
	 * flash_errors() helper */
	public function add_flash_error($string) {
		if (!$_SESSION["flash_errors"])
			$_SESSION["flash_errors"] = array();

		array_push($_SESSION["flash_errors"], $string);
	}

	/* store a notice in the session to be printed on the next view with the
	 * flash_notices() helper */
	public function add_flash_notice($string) {
		if (!$_SESSION["flash_notices"])
			$_SESSION["flash_notices"] = array();

		array_push($_SESSION["flash_notices"], $string);
	}

	/* cancel all buffered output, send a location: header, and exit */
	public function redirect_to($obj_or_url) {
		$link = link_from_obj_or_string($obj_or_url);

		/* prevent any content from getting to the user */
		while (@ob_end_clean())
			;

		Log::info("Redirected to " . $link);

		header("Location: " . $link);

		/* and bail */
		exit;
	}

	/* render a partial view, an action template, text, etc. */
	public function render($template, $vars = array()) {
		/* render(array("partial" => "somedir/file"), array("v" => $v)) */

		if (isset($template["status"]))
			header($_SERVER["SERVER_PROTOCOL"] . " " . $template["status"]);

		/* if we want to override the layout, do it now */
		if (is_array($template) && array_key_exists("layout", $template))
			$this::$layout = $template["layout"];

		/* just render text */
		if (is_array($template) && array_key_exists("text", $template))
			print $template["text"];

		/* assume we're dealing with files */
		else {
			$tf = "";

			/* render a partial template */
			if (is_array($template) && isset($template["partial"]))
				$tf = $template["partial"];

			/* render an action template */
			elseif (is_array($template) && isset($template["action"]))
				$tf = $template["action"];

			/* just a filename, render it as an action */
			elseif (is_array($template))
				$tf = join("", array_values($template));

			/* just a filename, render it as an action */
			else
				$tf = $template;

			/* if we have no directory, assume it's the in the current
			 * controller's views */
			if (!strpos($tf, "/"))
				$tf = strtolower(preg_replace("/Controller$/", "",
					Utils::current_controller_name())) . "/" . $tf;

			/* partial template files start with _ */
			if (is_array($template) && isset($template["partial"]))
				$tf = dirname($tf) . "/_" . basename($tf);

			if (file_exists($full_file = HALFMOON_ROOT . "/views/"
			. $tf . ".phtml"))
				$this->_really_render_file($full_file, $vars);

			elseif (file_exists($xml_file = HALFMOON_ROOT . "/views/"
			. $tf . ".pxml")) {
				/* TODO: check for an existing content-type already set by the
				 * user */
				header("Content-type: application/xml");
				$this->_really_render_file($xml_file, $vars);
			}
			
			else
				throw new RenderException("no template file " . $full_file);
		}

		$this->did_render = true;
	}

	/* a private function to avoid taining the variable space after the
	 * require() */
	private function _really_render_file($__file, $__vars) {
		/* XXX: should this be checking for more special variable names? */

		$__special_vars = array("__special_vars", "__vars", "__file",
			"controller");

		/* export variables set in the controller to the view */
		foreach ($this->locals as $k => $v) {
			if (in_array($k, $__special_vars)) {
				Log::warn("tried to redefine \$" . $k . " passed from "
					. "controller");
				continue;
			}

			$$k = $v;
		}

		/* and any passed as locals to the render() function */
		foreach ($__vars as $k => $v) {
			if (in_array($k, $__special_vars)) {
				Log::warn("tried to redefine \$" . $k . " passed "
					. "from render() call");
				continue;
			}

			$$k = $v;
		}

		/* define $controller where $this can't be used */
		$controller = $this;

		Log::info("Rendering " . $__file);

		require($__file);
	}

	/* the main entry point for the controller, sent by the router */
	public function render_action($action) {
		$this->enable_or_disable_sessions($action);

		$this->verify_method($action);

		$this->protect_from_forgery($action);

		if (!$this->process_before_filters($action))
			return false;

		ob_start();

		/* we only want to allow calling public methods in controllers, to
		 * avoid users getting directly to before_filters and other utility
		 * functions */
		if (!in_array($action, Utils::get_public_class_methods($this)))
			throw new RenderException("controller \"" . get_class($this)
				. "\" does not have an action \"" . $action . "\"");

		call_user_func_array(array($this, $action), array());

		if (!$this->did_render)
			$this->render(array("action" => $this->params["controller"] . "/"
				. $action), $this->locals);

		if (!$this->did_layout)
			$this->render_layout();
	}

	/* capture the output of everything rendered and put it within the layout */
	public function render_layout() {
		$content_for_layout = ob_get_contents();
		while (@ob_end_clean())
			$content_for_layout = $content_for_layout . ob_get_contents();

		/* if we don't want a layout at all, just print the content */
		if (isset($this::$layout) && $this::$layout === false) {
			print $content_for_layout;
			return;
		}

		$layout = null;

		/* check for an overridden layout and options */
		if (count((array)$this::$layout)) {
			do {
				if (is_array($this::$layout[1])) {
					/* don't override for specific actions */
					if ($this::$layout[1]["except"] && in_array($params["action"],
					(array)$this::$layout[1]["except"]))
						break;

					/* only override for certain actions */
					if ($this::$layout[1]["only"] && !in_array($params["action"],
					(array)$this::$layout[1]["only"]))
						break;
				}

				/* still here, override layout */
				$layout = $this::$layout[0];
			} while (0);
		}

		/* if no specific layout was set, check for a controller-specific one */
		if (!$layout && isset($this->params["controller"]) &&
		file_exists(HALFMOON_ROOT . "/views/layouts/" . $this->params["controller"]
		. ".phtml"))
			$layout = $this->params["controller"];

		/* otherwise, default to "application" */
		if (!$layout)
			$layout = "application";

		$this->did_layout = true;

		if (file_exists(HALFMOON_ROOT . "/views/layouts/" . $layout .
		".phtml")) {
			Log::info("Rendering layout " . $layout);
			require(HALFMOON_ROOT . "/views/layouts/" . $layout . ".phtml");
		} else
			print $content_for_layout;
	}

	/* enable or disable sessions according to $session */
	private function enable_or_disable_sessions($action) {
		if (is_array($this::$session)) {
			if ($this::$session[0] == "on") {
				/* $session = array("on", "only" => array(...)) */
				if (!in_array($action, (array)$this::$session["only"]))
					return;

				/* $session = array("on", "except" => array(...)) */
				elseif (in_array($action, (array)$this::$session["except"]))
					return;
			}

			elseif ($this::$session[0] == "off") {
				/* $session = array("off", "only" => array(...)) */
				if (in_array($action, (array)$this::$session["only"]))
					return;

				/* $session = array("off", "except" => array(...)) */
				elseif (!in_array($action, (array)$this::$session["except"]))
					return;
			}

			else
				 throw new HalfMoonException("invalid option for \$session: "
				 	. var_export($this::$session, true));
		}

		/* just a string of "on" or "off" */
		else {
			if ($this::$session == "off" || $this::$session == "")
				return;
			elseif ($this::$session != "on")
				 throw new HalfMoonException("invalid option for \$session: "
				 	. var_export($this::$session, true));
		}

		/* still here, we want a session */
		session_start();
	}

	/* verify any options requiring verification */
	private function verify_method($action) {
		foreach ((array)$this::$verify as $verification) {
			/* if this action isn't in the include list, skip */
			if (isset($verification["only"]) &&
			!in_array($action, (array)$verification["only"]))
				continue;

			/* if this action is exempted, skip */
			if (isset($verification["except"]) &&
			in_array($action, (array)$verification["except"]))
				continue;

			/* if the method passed from the server matches, skip */
			if (isset($verification["method"]) &&
			(strtolower($_SERVER["REQUEST_METHOD"]) ==
			strtolower($verification["method"])))
				continue;

			/* still here, do any actions */
			if (isset($verification["redirect_to"]))
				return redirect_to($verification["redirect_to"]);
		}
	}

	/* xsrf protection: verify the passed authenticity token for non-GET
	 * requests */
	private function protect_from_forgery($action) {
		if (!$this::$protect_from_forgery)
			return;

		if ($this->request->request_method() == "GET")
			return;

		if (is_array($this::$protect_from_forgery)) {
			/* don't protect for specific actions */
			if (isset($this::$protect_from_forgery["except"]) &&
			in_array($action, (array)$this::$protect_from_forgery["except"]))
				return;

			/* only verify for certain actions */
			if (isset($this::$protect_from_forgery["only"]) &&
			!in_array($action, (array)$this::$protect_from_forgery["only"]))
				return;
		}

		if (@$this->params["authenticity_token"] !=
		$this->form_authenticity_token()) {
			 throw new InvalidAuthenticityToken();
		}
	}

	/* return false if any before_filters return false */
	private function process_before_filters($action) {
		foreach ((array)$this::$before_filter as $filter) {
			if (!is_array($filter))
				$filter = array($filter);

			/* don't filter for specific actions */
			if (isset($filter["except"]) && in_array($action,
			(array)$filter["except"]))
				continue;

			/* only filter for certain actions */
			if (isset($filter["only"]) &&
			!in_array($action, (array)$filter["only"]))
				continue;

			if (!method_exists($this, $filter[0]))
				throw new RenderException("before_filter \"" . $filter[0]
					. "\" function does not exist");

			if (!call_user_func_array(array($this, $filter[0]), array())) {
				Log::info("Filter chain halted as " . $filter[0] . " returned "
					. "false.");

				return false;
			}
		}

		return true;
	}

	public function form_authenticity_token() {
		/* explicitly enable sessions so we can store/retrieve the token */
		@session_start();

		if (@!$_SESSION["_csrf_token"])
			$_SESSION["_csrf_token"] = Utils::random_hash();

		return $_SESSION["_csrf_token"];
	}
}

?>
