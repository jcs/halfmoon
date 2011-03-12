<?php
/*
	build a <form> based on an object
*/

namespace HalfMoon;

require_once("form_common.php");

class FormHelper extends FormTagHelper {
	public function form_for($obj, $url_or_obj, $options = array(),
	\Closure $form_content) {
		if (!$obj)
			throw new HalfMoonException("invalid object passed to form_for()");

		$this->form_object = $obj;

		return $this->output_form_around_closure($url_or_obj, $options,
			$form_content);
	}

	public function form_object_for($obj, $url_or_obj, $options = array(),
	\Closure $form_content) {
		if (!$obj)
			throw new HalfMoonException("invalid object passed to form_for()");

		$this->form_object = $obj;

		$html = Utils::to_s($this, $form_content);

		$this->form_object = null;

		return $html;
	}

	/* the prefix to use for class and id names for fields */
	protected function form_prefix() {
		return strtolower(get_class($this->form_object));
	}

	/* provide an html object id to use for a given field name */
	public function prefixed_field_id($field) {
		return preg_replace("/[^a-z0-9]/", "_",
			$this->form_prefix() . "_" . $field);
	}

	/* provide an html object name to use for a given field name */
	public function prefixed_field_name($field) {
		return $this->form_prefix() . "[" . $field . "]";
	}

	/* return the value of the particular field for the form object */
	protected function value_for_field($field) {
		/* if we have an array-looking name of field[var], use an AR getter of
		 * field(var) */
		if (preg_match("/^(.+)\[([^\]]+)\]$/", $field, $m))
			return $this->form_object->{"get_" . $m[1]}($m[2]);
		else
			return $this->form_object->$field;
	}

	/* honor names/ids passed through as options */
	private function set_field_id_and_name($field, $options) {
		if (!isset($options["id"]))
			$options["id"] = $this->prefixed_field_id($field);

		if (!isset($options["name"]))
			$options["name"] = $this->prefixed_field_name($field);

		return $options;
	}

	/* create an <input type="checkbox"> with a hidden input field to detect
	 * when the checkbox has been unchecked (see rails docs for check_box, but
	 * since php presents $_GET and $_POST with the last seen value, we have to
	 * reverse the order of the checkbox and hidden input field) */
	public function check_box($field, $options = array(), $checked_value = 1,
	$unchecked_value = 0) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "check_box_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return "<input"
			. " type=\"hidden\" "
			. " name=\"" . $options["name"] . "\""
			. " value=\"" . h($unchecked_value) . "\""
			. " />"
			. $this->wrap_field_with_errors($field,
				$this->check_box_tag($field, $checked_value, 
					(bool)$this->value_for_field($field), $options)
			);
	}

	/* create an <input> file upload field */
	public function file_field($field, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "file_field_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->file_field_tag($field, $options)
		);
	}

	/* create a hidden <input> field */
	public function hidden_field($field, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "hidden_field_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->hidden_field_tag($field, $this->value_for_field($field),
			$options);
	}

	/* create a <label> that references a field */
	public function label($field, $caption = null, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "label_tag");

		if (!isset($options["for"]))
			$options["for"] = $this->prefixed_field_id($field);

		return $this->label_tag($field, $caption, $options);
	}

	/* create an <input> password field, *not including any value* */
	public function password_field($field, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "password_field_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->password_field_tag($field, $value = null, $options)
		);
	}

	/* create an <input> radio button */
	public function radio_button($field, $value, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "radio_button_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->radio_button_tag($field, $value,
				($this->value_for_field($field) == $value), $options)
		);
	}

	/* create a <select> box with options */
	public function select($field, $choices, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "select_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->select_tag($field, $choices, $this->value_for_field($field),
				$options)
		);
	}

	/* create an <input> submit button */
	public function submit_button($value = "Submit Changes",
	$options = array()) {
		if (!isset($options["name"]))
			$options["name"] = "commit";

		return $this->submit_tag($value, $options);
	}

	/* create a <textarea> field */
	public function text_area($field, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "text_area_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->text_area_tag($field, $this->value_for_field($field),
				$options)
		);
	}

	/* create an <input> text field */
	public function text_field($field, $options = array()) {
		if (!$this->form_object)
			throw new HalfMoonException("no form object; you probably wanted "
				. "text_field_tag");

		$options = $this->set_field_id_and_name($field, $options);

		return $this->wrap_field_with_errors($field,
			$this->text_field_tag($field, $this->value_for_field($field),
				$options)
		);
	}
}

?>
