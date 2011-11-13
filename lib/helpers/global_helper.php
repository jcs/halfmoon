<?php
/*
	global helpers available everywhere, not an extension of Helper
*/

/* alias htmlspecialchars() to something smaller */
function h($text) {
	return htmlspecialchars($text);
}

function array_last(&$array) {
	if (empty($array))
		return NULL;
	else
		return $array[count($array) - 1];
}

/* html helpers escape html with h() everywhere, but for instances where raw
 * html needs to be printed, send it with raw("text") and it will not get
 * escaped */
class Raw {
	public $raw;
	public function __construct($text) {
		$this->raw = $text;
	}
	public function __toString() {
		return $this->raw;
	}
}

/* return a Raw object */
function raw($text) {
	return new Raw($text);
}

function raw_or_h($text) {
	if (is_object($text) && get_class($text) == "Raw")
		return (string)$text;
	else
		return h($text);
}

?>
