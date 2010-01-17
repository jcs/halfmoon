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
	public function form_for($obj, $url, $options = array(),
	\Closure $form_content) {
		$this->form_object = $obj;

		$method = ($options["method"] ? $options["method"] : "post");

		?>
		<form method="<?= $method ?>" action="<?= $url ?>">
		<?= to_s($this, $form_content) ?>
		</form>
		<?

		return self;
	}

	private function form_prefix() {
		return strtolower(get_class($this->form_object));
	}

	/* create a <label> that references a real column */
	public function label($column, $caption) {
		return "<label for=\"" . $this->form_prefix() . "_" . $column . "\">"
			. $caption . "</label>";
	}

	/* create an <input> password field, *not including any value* */
	public function password_field($field, $options = array()) {
		$type = ($options["type"] ? $options["type"] : "password");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return "<input type=\"" . $type . "\""
			. " id=\"" . $this->form_prefix() . "_" . $field . "\""
			. " name=\"" . $this->form_prefix() . "[" . $field .  "]\""
			. $opts_s
			. " />";
	}

	/* create an <input> submit button */
	public function submit_tag($value = "Submit Changes", $options = array()) {
		return "<input type=\"submit\" name=\"commit\" value=\"" . $value
			. "\" />";
	}

	/* create an <input> text field */
	public function text_field($field, $options = array()) {
		$type = ($options["type"] ? $options["type"] : "text");

		$opts_s = "";
		foreach ($options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return "<input type=\"" . $type . "\""
			. " id=\"" . $this->form_prefix() . "_" . $field . "\""
			. " name=\"" . $this->form_prefix() . "[" . $field .  "]\""
			. " value=\"" . h($this->form_object->$field) . "\""
			. $opts_s
			. " />";
	}
}

?>
