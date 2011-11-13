<?php
/*
	<form>-building helper functions
*/

namespace HalfMoon;

require_once("form_common.php");

class FormTagHelper extends FormHelperCommon {
	/* provide an html object id to use for a given field name */
	public function field_id($field) {
		return preg_replace("/[^a-z0-9]+/i", "_", $field);
	}

	/* provide an html object name to use for a given field name */
	public function field_name($field) {
		return $field;
	}

	/* honor names/ids passed through as options */
	private function set_field_id_and_name($field, $options) {
		if (!isset($options["id"]))
			$options["id"] = $this->field_id($field);

		if (!isset($options["name"]))
			$options["name"] = $this->field_name($field);

		return $options;
	}

	public function form_tag($url_or_obj, $options = array(),
	\Closure $form_content) {
		return $this->output_form_around_closure($url_or_obj, $options,
			$form_content);
	}

	/* create a checkbox <input> */
	public function check_box_tag($field, $value = 1, $checked = false,
	$options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "checkbox";

		if (!isset($options["value"]))
			$options["value"] = $value;

		if ($checked)
			$options["checked"] = "checked";

		return "<input" . $this->options_to_s($options) . " />";
	}

	/* create an <input> file upload field */
	public function file_field_tag($field, $options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "file";

		return $this->text_field_tag($field, null, $options);
	}

	/* create a hidden <input> field */
	public function hidden_field_tag($field, $value, $options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "hidden";

		return $this->text_field_tag($field, $value, $options);
	}

	/* create a <label> that references a field */
	public function label_tag($field, $caption = null, $options = array()) {
		if (!isset($options["id"]))
			$options["id"] = $this->field_id($field) . "_label";

		if (!isset($options["for"]))
			$options["for"] = $this->field_id($field);

		if (is_null($caption)) {
			/* author_id -> "Author", selected_text -> "Selected text" */
			$caption = preg_replace("/_id$/", "", str_replace("_", " ",
				$field));
			$caption = strtoupper(substr($caption, 0, 1)) . substr($caption, 1);
		}

		return "<label" . $this->options_to_s($options) . ">"
			. $caption
			. "</label>";
	}

	/* create an <input> password field */
	public function password_field_tag($field = "password", $value = null,
	$options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "password";

		return $this->text_field_tag($field, $value = null, $options);
	}

	/* create an <input> radio button */
	public function radio_button_tag($field, $value, $checked = false,
	$options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "radio";

		if (!isset($options["value"]))
			$options["value"] = $value;

		if ($checked)
			$options["checked"] = "checked";

		return "<input" . $this->options_to_s($options) . " />";
	}

	/* create a <select> box with options */
	public function select_tag($field, $choices, $selected = null,
	$options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		return "<select" . $this->options_to_s($options) . ">"
			. $this->options_for_select($choices, $selected)
			. "</select>";
	}

	/* create an <input> submit button */
	public function submit_tag($value = "Submit Changes", $options = array()) {
		if (!isset($options["name"]))
			$options["name"] = "commit";

		if (!isset($options["type"]))
			$options["type"] = "submit";

		$options["value"] = $value;

		return "<input" . $this->options_to_s($options) . " />";
	}

	/* create a <textarea> field */
	public function text_area_tag($field, $content = null, $options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (isset($options["size"])) {
			list($options["cols"], $options["rows"]) = explode("x",
				$options["size"], 2);

			unset($options["size"]);
		}

		return "<textarea " . $this->options_to_s($options) . ">"
			. raw_or_h($content)
			. "</textarea>";
	}

	/* create an <input> text field */
	public function text_field_tag($field, $value = null, $options = array()) {
		$options = FormTagHelper::set_field_id_and_name($field, $options);

		if (!isset($options["type"]))
			$options["type"] = "text";

		$options["value"] = $value;

		return "<input" . $this->options_to_s($options) . " />";
	}
}

?>
