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

	public $params = array();
	public $locals = array();

	private $did_render = false;

	/* turn local class variables into $variables when rendering views */
	public function __set($name, $value) {
		$this->locals[$name] = $value;
	}

	public function add_flash_error($string) {
		if (!$_SESSION["flash_errors"])
			$_SESSION["flash_errors"] = array();

		array_push($_SESSION["flash_errors"], $string);
	}

	public function add_flash_notice($string) {
		if (!$_SESSION["flash_notices"])
			$_SESSION["flash_notices"] = array();

		array_push($_SESSION["flash_notices"], $string);
	}

	/* render(array("partial" => "somedir/file"), array("v" => $v)) */
	public function render($template, $vars = array()) {
		/* just render text */
		if (is_array($template) && $template["text"])
			print $template["text"];

		/* assume we're dealing with files */
		else {
			$template_file = "";

			/* render a partial template */
			if (is_array($template) && $template["partial"])
				$template_file = dirname($template["partial"]) . "/_"
					. basename($template["partial"]);

			/* render an action template */
			elseif (is_array($template) && $template["action"])
				$template_file = $template["action"];

			/* just a filename, render it as an action */
			elseif (is_array($template))
				$template_file = join("", array_values($template));

			/* just a filename, render it as an action */
			else
				$template_file = $template;

			if (file_exists($full_file = HALFMOON_ROOT . "/views/"
			. $template_file . ".phtml")) {
				/* import the keys/values into the namespace of this partial */
				foreach ($vars as $vname => $vdata)
					$$vname = $vdata;

				require($full_file);
			} else
				throw new RenderException("no template file " . $full_file);
		}

		$this->did_render = true;
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

		if (!method_exists($this, $action))
			throw new RenderException("controller \"" . get_class($this)
				. "\" does not have an action \"" . $action . "\"");

		call_user_func_array(array($this, $action), array());

		if ($this->did_render)
			ob_end_flush();
		else
			$this->render_with_layout($action);
	}

	/* capture the output of all the partials and render it with the layout */
	public function render_with_layout($action) {
		$this->render(array("action" => $this->params["controller"] . "/"
			. $action), $this->locals);

		$content_for_layout = ob_get_contents();
		ob_end_clean();

		$layout = "application";
		if ($params["controller"] && file_exists(HALFMOON_ROOT . "/views/layouts/"
		. $params["controller"] . ".phtml"))
			$layout = $params["controller"];

		require(HALFMOON_ROOT . "/views/layouts/" . $layout . ".phtml");
	}
}

?>
