<?php
/*
	<form>-building helper functions
*/

namespace HalfMoon;

class FormHelperCommon extends Helper {
	public $controller = null;
	public $form_object = null;
	public $html_helper = null;
	public $form_prefix = null;

	public function __construct() {
		$this->html_helper = new HtmlHelper;
	}

	/* the guts of form_for() and form_tag() */
	protected function output_form_around_closure($url_or_obj,
	$options = array(), \Closure $form_content) {
		if (!isset($options["method"]))
			$options["method"] = "post";

		if (!empty($options["multipart"])) {
			unset($options["multipart"]);
			$options["enctype"] = "multipart/form-data";
		}

		if (!empty($options["form_prefix"])) {
			$this->form_prefix = $options["form_prefix"];
			unset($options["form_prefix"]);
		}

		print "<form"
			. $this->options_to_s($options)
			. " action=\""
				. h($this->html_helper->link_from_obj_or_string($url_or_obj))
				. "\""
			. ">";

		if (strtolower($options["method"]) != "get")
			print "<input"
				. " name=\"authenticity_token\""
				. " type=\"hidden\""
				. " value=\"" . h($this->controller->form_authenticity_token())
					. "\""
				. " />";

		print Utils::to_s($this, $form_content, $this->controller);

		print "</form>";

		$this->form_object = null;

		return $this;
	}

	/* generate <option> tags for an array of options for a <select> */
	protected function options_for_select($choices, $selected) {
		$str = "";
		$is_assoc = Utils::is_assoc($choices);
		$array_of_arrays = (bool)(!$is_assoc && is_array($choices[0]));

		foreach ($choices as $key => $val) {
			if ($array_of_arrays) {
				if (Utils::is_assoc($val)) {
					$key = Utils::A(array_keys($val), 0);
					$val = Utils::A(array_values($val), 0);
				} else {
					$key = $val[0];
					$val = $val[1];
				}
			} elseif (!$is_assoc)
				$key = $val;

			if ($selected === $key)
				$is_selected = true;
			elseif (is_numeric($selected) && is_numeric($key) &&
			($selected == $key))
				$is_selected = true;
			/* TODO: handle 0=false, 1=true, true="true", etc. */
			else
				$is_selected = false;

			$str .= "<option value=\"" . raw_or_h($key) . "\""
				. ($is_selected ? " selected" : "")
				. ">" . raw_or_h($val) . "</option>";
		}

		return $str;
	}

	/* convert an array of options to html element options */
	protected function options_to_s($options) {
		if (!is_array($options))
			throw new HalfMoonException("invalid argument passed; expected "
				. "options array");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . raw_or_h($v) . "\"";

		return $opts_s;
	}

	/* generate <option> tags for each identifier in php's timezone list */
	protected function time_zone_options_for_select($selected) {
		$str = "";

		foreach (\DateTimeZone::listIdentifiers() as $tz)
			$str .= "<option value=\"" . $tz . "\""
				. (strtolower($selected) === strtolower($tz) ?
				" selected" : "") . ">" . h($tz) . "</option>";

		return $str;
	}
 
	/* for each attribute in a form_for() object that has errors, output a div
	 * around it that should be styled to stand out */
	protected function wrap_field_with_errors($field, $field_html) {
		if (!$this->form_object)
			throw new HalfMoonExceptions("wrap_field_with_errors called when "
				. "no form object");

		if ($this->form_object->errors &&
		$this->form_object->errors->on($field))
			return "<div class=\"fieldWithErrors\">" . $field_html . "</div>";
		else
			return $field_html;
	}
}

?>
