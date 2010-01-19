<?php
/*
	helper functions available everywhere
*/

namespace HalfMoon;

class FormHelper {
	public $form_object = null;

	/* output a <form>..</form> wrapped around a closure, to which this
	 * FormHelper object is passed, which should output the actual form
	 * content, using things like <?= $f->text_field(...) ?> */
	public function form_for($obj, $url_or_obj, $options = array(),
	\Closure $form_content) {
		if (!$obj)
			throw new HalfmoonException("invalid object passed to form_for()");

		$this->form_object = $obj;

		if (!$options["method"])
			$options["method"] = "post";

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		?>
		<form <?= $opts_s ?> action="<?= link_from_obj_or_string($url_or_obj) ?>">
		<?= to_s($this, $form_content); ?>
		</form>
		<?

		return self;
	}

	private function form_prefix() {
		return strtolower(get_class($this->form_object));
	}

	/* just a convenience forward to the global button_to() */
	public function button_to() {
		return call_user_func_array("button_to", func_get_args());
	}

	/* create a <label> that references a real column */
	public function label($column, $caption, $options = array()) {
		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return "<label for=\"" . $this->form_prefix() . "_" . $column . "\""
			. $opts_s . ">" . $caption . "</label>";
	}

	/* just a convenience forward to the global link_to() */
	public function link_to() {
		return call_user_func_array("link_to", func_get_args());
	}

	/* create an <input> password field, *not including any value* */
	public function password_field($field, $options = array()) {
		$type = ($options["type"] ? $options["type"] : "password");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return $this->wrap_field_with_errors($field,
			"<input type=\"" . $type . "\""
			. " id=\"" . $this->form_prefix() . "_" . $field . "\""
			. " name=\"" . $this->form_prefix() . "[" . $field .  "]\""
			. $opts_s
			. " />");
	}

	/* create an <input> submit button */
	public function submit_tag($value = "Submit Changes", $options = array()) {
		return "<input type=\"submit\" name=\"commit\" value=\"" . $value
			. "\" />";
	}

	/* create a <textarea> field */
	public function text_area($field, $options = array()) {
		if ($options["size"]) {
			list($options["cols"], $options["rows"]) = explode("x",
				$options["size"], 2);

			unset($options["size"]);
		}

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return $this->wrap_field_with_errors($field,
			"<textarea "
			. " id=\"" . $this->form_prefix() . "_" . $field . "\""
			. " name=\"" . $this->form_prefix() . "[" . $field .  "]\""
			. $opts_s
			. ">" . h($this->form_object->$field) . "</textarea>");
	}

	/* create an <input> text field */
	public function text_field($field, $options = array()) {
		$type = ($options["type"] ? $options["type"] : "text");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return $this->wrap_field_with_errors($field,
			"<input type=\"" . $type . "\""
			. " id=\"" . $this->form_prefix() . "_" . $field . "\""
			. " name=\"" . $this->form_prefix() . "[" . $field .  "]\""
			. " value=\"" . h($this->form_object->$field) . "\""
			. $opts_s
			. " />");
	}

	private function wrap_field_with_errors($field, $html) {
		if ($this->form_object->errors &&
		$this->form_object->errors->on($field))
			return "<div class=\"fieldWithErrors\">" . $html . "</div>";
		else
			return $html;
	}
}

?>
