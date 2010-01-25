<?php

namespace HalfMoon;

class ApplicationController {
	/* array of methods to call before processing any actions, bailing if any
	 * return false */
	static $before_filter = array();

	/* array of arrays to verify before processing any actions
	 * e.g. $verify = array(
	 *          array("method" => "post",
	 *              "only" => array("login"),
	 *              "redirect_to" => "/",
	 *          ),
	 *          array("method" => "get",
	 *          ...
	 */
	static $verify = array();

	/* specify a different layout than controller name or application */
	static $layout = array();

	public $params = array();
	public $locals = array();

	private $did_render = false;
	private $did_layout = false;

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
		while (ob_end_clean())
			;

		header("Location: " . $link);

		/* and bail */
		exit;
	}

	/* render a partial view, an action template, text, etc. */
	public function render($template, $vars = array()) {
		/* render(array("partial" => "somedir/file"), array("v" => $v)) */

		if ($template["status"])
			header($_SERVER["SERVER_PROTOCOL"] . " " . $template["status"]);

		/* if we want to override the layout, do it now */
		if (array_key_exists("layout", $template))
			$this::$layout = $template["layout"];

		/* just render text */
		if (is_array($template) && array_key_exists("text", $template))
			print $template["text"];

		/* assume we're dealing with files */
		else {
			$tf = "";

			/* render a partial template */
			if (is_array($template) && $template["partial"])
				$tf = $template["partial"];

			/* render an action template */
			elseif (is_array($template) && $template["action"])
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
					current_controller())) . "/" . $tf;

			/* partial template files start with _ */
			if (is_array($template) && $template["partial"])
				$tf = dirname($tf) . "/_" . basename($tf);

			if (file_exists($full_file = HALFMOON_ROOT . "/views/"
			. $tf . ".phtml"))
				$this->_really_render_file($full_file, $vars);
			elseif (file_exists($full_file = HALFMOON_ROOT . "/views/"
			. $tf . ".pxml")) {
				/* TODO: check for an existing content-type already set by the
				 * user */
				header("Content-type: application/xml");
				$this->_really_render_file($full_file, $vars);
			} else
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
				error_log("tried to redefine \$" . $k . " passed from "
					. "controller");
				continue;
			}

			$$k = $v;
		}

		/* and any passed as locals to the render() function */
		foreach ($__vars as $k => $v) {
			if (in_array($k, $__special_vars)) {
				error_log("tried to redefine \$" . $k . " passed from "
					. "render() call");
				continue;
			}

			$$k = $v;
		}

		/* define $controller where $this can't be used */
		$controller = $this;

		require($__file);
	}

	/* the main entry point for the controller, sent by the router */
	public function render_action($action) {
		session_start();

		/* verify any options requiring verification */
		foreach ((array)$this::$verify as $verification) {
			/* if this action isn't in the include list, skip */
			if ($verification["only"] &&
			!in_array($action, (array)$verification["only"]))
				continue;

			/* if this action is exempted, skip */
			if ($verification["except"] &&
			in_array($action, (array)$verification["except"]))
				continue;

			/* if the method passed from the server matches, skip */
			if ($verification["method"] &&
			(strtolower($_SERVER["REQUEST_METHOD"]) ==
			strtolower($verification["method"])))
				continue;

			/* still here, do any actions */
			if ($verification["redirect_to"])
				return redirect_to($verification["redirect_to"]);
		}

		/* call any before_filters first, bailing if any return false */
		foreach ((array)$this::$before_filter as $filter) {
			if (!method_exists($this, $filter))
				throw new RenderException("before_filter \"" . $filter . "\" "
					. "function does not exist");

			if (!call_user_func_array(array($this, $filter), array()))
				return false;
		}

		ob_start();

		/* we only want to allow calling public methods in controllers, to
		 * avoid users getting directly to before_filters and other utility
		 * functions */
		if (!in_array($action, get_public_class_methods($this)))
			throw new RenderException("controller \"" . get_class($this)
				. "\" does not have an action \"" . $action . "\"");

		call_user_func_array(array($this, $action), array());

		if ($this->did_render)
			ob_end_flush();
		else
			$this->render(array("action" => $this->params["controller"] . "/"
				. $action), $this->locals);

		if (!$this->did_layout)
			$this->render_layout();
	}

	/* capture the output of everything rendered and put it within the layout */
	public function render_layout() {
		$content_for_layout = ob_get_contents();
		ob_end_clean();

		/* if we don't want a layout at all, just print the content */
		if (isset($this::$layout) && $this::$layout === false) {
			print $content_for_layout;
			return;
		}

		if (count((array)$this::$layout)) {
			/* check for options */
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
		if (!$layout && $this->params["controller"] &&
		file_exists(HALFMOON_ROOT . "/views/layouts/" . $this->params["controller"]
		. ".phtml"))
			$layout = $this->params["controller"];

		/* otherwise, default to "application" */
		if (!$layout)
			$layout = "application";

		$this->did_layout = true;

		if (file_exists(HALFMOON_ROOT . "/views/layouts/" . $layout .
		".phtml"))
			require(HALFMOON_ROOT . "/views/layouts/" . $layout . ".phtml");
		else
			print $content_for_layout;
	}
}

?>
