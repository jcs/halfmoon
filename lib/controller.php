<?php

namespace HalfMoon;

class ApplicationController {
	static $before_filter = array();

	public $params = array();
	public $locals = array();

	private $did_render = false;

	/* turn local class variables into $variables when rendering views */
	public function __set($name, $value) {
		$this->locals[$name] = $value;
	}

	/* render(array("partial" => "somedir/file"), array("v" => $v)) */
	public function render($template, $vars = array()) {
		if (is_array($template) && $template["partial"])
			$template_file = dirname($template["partial"]) . "/_"
				. basename($template["partial"]);

		elseif (is_array($template) && $template["action"])
			$template_file = $template["action"];

		elseif (is_array($template))
			$template_file = join("", array_values($template));

		else
			$template_file = $template;

		if (file_exists($full_file = HALFMOON_ROOT . "/views/" . $template_file
		. ".phtml")) {
			/* import the keys/values into the namespace of this partial */
			foreach ($vars as $vname => $vdata)
				$$vname = $vdata;

			require($full_file);
		} else
			die("no template file " . $full_file);

		$this->did_render = true;
	}

	/* the main entry point for the controller, sent by the router */
	public function render_action($action) {
		session_start();

		/* call any before_filters first, bailing if any return false */
		foreach ((array)$this::$before_filter as $filter) {
			if (!method_exists($this, $filter))
				die("before_filter \"" . $filter . "\" function does not "
					. "exist");

			if (!call_user_func_array(array($this, $filter), array()))
				return false;
		}

        ob_start();

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
