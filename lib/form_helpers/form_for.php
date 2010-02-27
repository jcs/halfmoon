<?php
/*
	build a <form> based on an object
*/

namespace HalfMoon;

class FormHelper extends FormTagHelper {
	public $form_object = null;

	public function form_for($obj, $url_or_obj, $options = array(),
	\Closure $form_content) {
		if (!$obj)
			throw new HalfMoonException("invalid object passed to form_for()");

		$this->form_object = $obj;

		return $this->output_form_around_closure($url_or_obj, $options,
			$form_content);
	}

	/* the prefix to use for class and id names for fields */
	protected function form_prefix() {
		return strtolower(get_class($this->form_object));
	}

	/* provide an html object id to use for a given field name */
	protected function prefixed_field_id($field) {
		return $this->form_prefix() . "_" . $field;
	}

	/* provide an html object name to use for a given field name */
	protected function prefixed_field_name($field) {
		return $this->form_prefix() . "[" . $field . "]";
	}

	/* create an <input type="checkbox"> with a hidden input field to detect
	 * when the checkbox has been unchecked (see rails docs for check_box, but
	 * since php presents $_GET and $_POST with the last seen value, we have to
	 * reverse the order of the checkbox and hidden input field) */
	public function check_box($field, $options = array(), $checked_value = 1,
	$unchecked_value = 0) {
		return "<input"
			. " type=\"hidden\" "
			. " name=\"" . $this->prefixed_field_name($field) . "\""
			. " value=\"" . h($unchecked_value) . "\""
			. " />"
			. $this->wrap_field_with_errors($field,
				$this->check_box_tag($field, $checked_value, 
					(bool)$this->form_object->$field, $options)
			);
	}

	/* create an <input> file upload field */
	public function file_field($field, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->file_field_tag($field, $options)
		);
	}

	/* create a hidden <input> field */
	public function hidden_field($field, $options = array()) {
		return $this->hidden_field_tag($field, $this->form_object->$field,
			$options);
	}

	/* create a <label> that references a field */
	public function label($column, $caption = null, $options = array()) {
		return $this->label_tag($column, $caption, $options);
	}

	/* create an <input> password field, *not including any value* */
	public function password_field($field, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->password_field_tag($field, $value = null, $options)
		);
	}

	/* create an <input> radio button */
	public function radio_button($field, $value, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->radio_button_tag($field, $value,
				($this->form_object->$field == $value), $options)
		);
	}

	/* create a <select> box with options */
	public function select($field, $choices, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->select_tag($field, $choices, $this->form_object->$field,
				$options)
		);
	}

	/* create an <input> submit button */
	public function submit_button($value = "Submit Changes",
	$options = array()) {
		return $this->submit_tag($value, $options);
	}

	/* create a <textarea> field */
	public function text_area($field, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->text_area_tag($field, $this->form_object->$field,
				$options)
		);
	}

	/* create an <input> text field */
	public function text_field($field, $options = array()) {
		return $this->wrap_field_with_errors($field,
			$this->text_field_tag($field, $this->form_object->$field,
				$options)
		);
	}
}

?>
