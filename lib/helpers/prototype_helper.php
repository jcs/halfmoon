<?php
/*
	prototype javascript helpers
*/

namespace HalfMoon;

class PrototypeHelper extends FormHelper {
	public function button_to_function($label, $options = array()) {
		if (!is_array($options))
			$options = array("onclick" => $options);

		if (!isset($options["html_options"]))
			$options["html_options"] = array();

		if (!isset($options["html_options"]["type"]))
			$options["html_options"]["type"] = "button";

		return "<input"
			. " value=\"" . $label . "\""
			. " onclick=\"" . $options["onclick"] . "\""
			. HtmlHelper::options_for_link($options["html_options"])
			. " />";
	}

	public function form_remote_tag($options = array(),
	\Closure $form_content) {
		return $this->output_remote_form_around_closure($options["url"],
			$options, $form_content);
	}

	public function link_to_remote($text, $options = array(),
	$html_options = array()) {
		return $this->link_to_function($text, $this->remote_function($options),
			$html_options);
	}

	public function link_to_function($text, $javascript, $options = array()) {
		if (isset($options["onclick"]))
			$options["onclick"] .= "; ";
		else
			$options["onclick"] = "";

		$options["onclick"] .= $javascript . "; return false;";

		if (!isset($options["href"]))
			$options["href"] = "#";

		return "<a href=\"" . $options["href"] . "\""
			. HtmlHelper::options_for_link($options) . ">" . $text . "</a>";
	}

	public function options_for_ajax($options) {
		$js_options = array();

		$js_options["asynchronous"] = (bool)(isset($options["type"]) &&
			$options["type"] == "synchronous");

		if (isset($options["method"]))
			$js_options["method"] = "'" . $options["method"] . "'";

		if (isset($options["position"]))
			$js_options["insertion"] = "'" . strtolower($options["position"])
				. "'";

		if (isset($options["script"]))
			$js_options["evalScripts"] = true;

		if (isset($options["form"]))
			$js_options["parameters"] = "Form.serialize(this)";
		elseif (isset($options["submit"]))
			$js_options["parameters"] = "Form.serialize('" . $options["submit"]
				. "')";
		elseif (isset($options["with"]))
			$js_options["parameters"] = $options["with"];

		if (isset($js_options["parameters"]))
			$js_options["parameters"] .= " + '&";
		else
			$js_options["parameters"] = "'";

		$js_options["parameters"] .= "authenticity_token=' + "
			. "encodeURIComponent('"
			. $this->controller->form_authenticity_token() . "')";

		/* support onCreate, onSuccess, etc. */
		foreach ($options as $k => $v)
			if (preg_match("/^on.+$/", $k))
				$js_options[$k] = $v;

		return $this->options_for_javascript($js_options);
	}

	public function options_for_javascript($options) {
		if (!array_keys($options))
			return "{}";
		else {
			$t = array_map(function($k) use ($options) {
				$o = $k . ":";

				if ($options[$k] === true)
					$o .= "true";
				elseif ($options[$k] === false)
					$o .= "false";
				else
					$o .= $options[$k];

				return $o;
			}, array_keys($options));

			sort($t);

			return "{" . join(", ", $t) . "}";
		}
	}

	/* the guts of remote_form_for() and form_remote_tag() */
	protected function output_remote_form_around_closure($url_or_obj,
	$options = array(), \Closure $form_content) {
		if (!isset($options["html"]))
			$options["html"] = array();

		if (isset($options["html"]["onsubmit"]))
			$options["html"]["onsubmit"] .= "; ";
		else
			$options["html"]["onsubmit"] = "";

		$options["form"] = true;

		$options["html"]["onsubmit"] .= $this->remote_function($options)
			. "; return false;";

		return $this->output_form_around_closure($url_or_obj, $options["html"],
			$form_content);
	}

	public function remote_form_for($obj, $options = array(),
	\Closure $form_content) {
		if (!$obj)
			throw new HalfMoonException("invalid object passed to "
				. "remote_form_for()");

		$this->form_object = $obj;

		return $this->output_remote_form_around_closure($options["url"],
			$options, $form_content);
	}

	public function remote_function($options) {
		$javascript_options = $this->options_for_ajax($options);

		$update = "";
		if (isset($options["update"]) && is_array($options["update"])) {
			$update = array();
			if (isset($options["update"]["success"]))
				array_push($update, "success:'" . $options["update"]["success"]
					. "'");
			if (isset($options["update"]["failure"]))
				array_push($update, "failure:'" . $options["update"]["failure"]
					. "'");
			$update = "{" . join(",", $update) . "}";
		}

		elseif (isset($options["update"]))
			$update = "'" . $options["update"] . "'";

		$function = "new Ajax." . ($update == "" ? "Request("
			: "Updater(" . $update . ", ");

		$function .= "'" . HtmlHelper::link_from_obj_or_string($options["url"])
			. "', " . $javascript_options . ")";

		if (isset($options["before"]))
			$function = $options["before"] . "; " . $function;

		if (isset($options["after"]))
			$function = $function . "; " . $options["after"];

		if (isset($options["condition"]))
			$function = "if (" . $options["condition"] . ") { " . $function
				. "; }";

		if (isset($options["confirm"]))
			$function = "if (confirm('" . $options["confirm"] . "')) { "
				. $function . "; }";

		return $function;
	}
}

?>
