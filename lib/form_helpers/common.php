<?php
/*
	<form>-building helper functions
*/

namespace HalfMoon;

class FormHelperCommon {
	public $controller = null;

	/* the guts of form_for() and form_tag() */
	protected function output_form_around_closure($url_or_obj,
	$options = array(), \Closure $form_content) {
		$this->controller = Utils::current_controller();

		/* TODO: always put $controller into the closure scope */

		if (!$options["method"])
			$options["method"] = "post";

		print "<form"
			. $this->options_to_s($options)
			. " action=\"" . link_from_obj_or_string($url_or_obj) . "\""
			. ">";

		if (strtolower($options["method"]) != "get")
			print "<input"
				. " name=\"authenticity_token\""
				. " type=\"hidden\""
				. " value=\"" . h($this->controller->form_authenticity_token())
					. "\""
				. " />";

		print Utils::to_s($this, $form_content);

		print "</form>";

		return self;
	}

	/* generate <option> tags for an array of options for a <select> */
	protected function options_for_select($choices, $selected) {
		$str = "";
		$is_assoc = Utils::is_assoc($choices);

		foreach ($choices as $key => $val)
			$str .= "<option value=\"" . h($is_assoc ? $key : $val) . "\""
				. ($selected === ($is_assoc ? $key : $val) ? " selected" : "")
				. ">" . h($val) . "</option>";

		return $str;
	}

	/* convert an array of options to html element options */
	protected function options_to_s($options) {
		if (!is_array($options))
			throw new HalfMoonException("invalid argument passed");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return $opts_s;
	}

	/* for each attribute in a form_for() object that has errors, output a div
	 * around it that should be styled to stand out */
	protected function wrap_field_with_errors($field, $html) {
		if ($this->form_object->errors &&
		$this->form_object->errors->on($field))
			return "<div class=\"fieldWithErrors\">" . $html . "</div>";
		else
			return $html;
	}

	/* just a convenience forward to the global button_to() */
	public function button_to() {
		return call_user_func_array("button_to", func_get_args());
	}

	/* just a convenience forward to the global link_to() */
	public function link_to() {
		return call_user_func_array("link_to", func_get_args());
	}
}

?>
