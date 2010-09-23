<?php
/*
	global helpers available everywhere, not an extension of Helper
*/

/* alias htmlspecialchars() to something smaller */
function h($text) {
	return htmlspecialchars($text);
}

function array_last(&$array) {
	if (count($array))
		return $array[count($array) - 1];
	else
		return NULL;
}

?>
