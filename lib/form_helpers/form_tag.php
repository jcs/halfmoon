<?php
/*
	<form>-building helper functions
*/

namespace HalfMoon;

class FormTagHelper extends FormHelperCommon {
	/* provide an html object id to use for a given field name */
	protected function prefixed_field_id($field) {
		return $field;
	}

	/* provide an html object name to use for a given field name */
	protected function prefixed_field_name($field) {
		return $field;
	}

	public function form_tag($url_or_obj, $options = array(),
	\Closure $form_content) {
		return $this->output_form_around_closure($url_or_obj, $options,
			$form_content);
	}

	/* create a checkbox <input> */
	public function check_box_tag($field, $value = 1, $checked = false,
	$options = array()) {
		return "<input type=\"checkbox\""
			. " id=\"" . $this->prefixed_field_id($field) . "\""
			. " name=\"" . $this->prefixed_field_name($field) . "]\""
			. " value=\"" . h($value) . "\""
			. ($checked ? " checked=\"checked\"" : "")
			. $this->options_to_s($options)
			. " />";
	}

	/* create an <input> file upload field */
	public function file_field_tag($field, $options = array()) {
		$options["type"] = "file";

		return $this->text_field_tag($field, null, $options);
	}

	/* create a <label> that references a field */
	public function label_tag($column, $caption = null, $options = array()) {
		if (is_null($caption))
			$caption = $column;

		return "<label"
			. " for=\"" . $this->prefixed_field_id($column) . "\""
			. $this->options_to_s($options)
			. ">"
			. $caption
			. "</label>";
	}

	/* create an <input> password field */
	public function password_field_tag($field = "password", $value = null,
	$options = array()) {
		$options["type"] = "password";

		return $this->text_field_tag($field, $value = null, $options);
	}

	/* create an <input> radio button */
	public function radio_button_tag($field, $value, $checked = false,
	$options = array()) {
		return "<input type=\"radio\""
			. " id=\"" . $this->prefixed_field_id($field) . "\""
			. " name=\"" . $this->prefixed_field_name($field) . "]\""
			. " value=\"" . h($value) . "\""
			. ($checked ? " checked=\"checked\"" : "")
			. $this->options_to_s($options)
			. " />";
	}

	/* create a <select> box with options */
	public function select_tag($field, $choices, $selected = null,
	$options = array()) {
		return "<select"
			. " id=\"" . $this->prefixed_field_id($field) . "\""
			. " name=\"" . $this->prefixed_field_name($field) . "\""
			. $this->options_to_s($options)
			. ">"
			. $this->options_for_select($choices, $selected)
			. "</select>";
	}

	/* create an <input> submit button */
	public function submit_tag($value = "Submit Changes", $options = array()) {
		if ($options["name"]) {
			$name = $options["name"];
			unset($options["name"]);
		} else
			$name = "commit";

		return "<input"
			. " type=\"submit\""
			. " name=\"" . $name . "\""
			. " value=\"" . $value . "\""
			. $this->options_to_s($options)
			. " />";
	}

	/* create a <textarea> field */
	public function text_area_tag($field, $content, $options = array()) {
		if ($options["size"]) {
			list($options["cols"], $options["rows"]) = explode("x",
				$options["size"], 2);

			unset($options["size"]);
		}

		return "<textarea "
			. " id=\"" . $this->prefixed_field_id($field) . "\""
			. " name=\"" . $this->prefixed_field_name($field) . "\""
			. $this->options_to_s($options)
			. ">"
			. h($content)
			. "</textarea>";
	}

	/* create an <input> text field */
	public function text_field_tag($field, $value, $options = array()) {
		$type = ($options["type"] ? $options["type"] : "text");

		return "<input"
			. " type=\"" . $type . "\""
			. " id=\"" . $this->prefixed_field_id($field) . "\""
			. " name=\"" . $this->prefixed_field_name($field) . "\""
			. " value=\"" . h($value) . "\""
			. $this->options_to_s($options)
			. " />";
	}
}

?>
