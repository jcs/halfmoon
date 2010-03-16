<?php
/*
	global helpers available everywhere, not an extension of Helper
*/

/* alias htmlspecialchars() to something smaller */
function h($text) {
	return htmlspecialchars($text);
}

?>
