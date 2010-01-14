<?php
/*
	helper functions available everywhere
*/

function A($data, $index) {
	return $data[$index];
}

/* print_r() to the error log */
function error_log_r($param) {
	ob_start();
	print_r($param);
	$lines = explode("\n", ob_get_clean());

	foreach ($lines as $line)
		error_log($line);
}

/* alias htmlspecialchars() to something smaller */
function h($text) {
	return htmlspecialchars($text);
}

function link_to($text, $obj_or_link, $options = array()) {
	$link_options = array();
	$link_dest = "";

	if (is_object($obj_or_link)) {
		$class = get_class($obj_or_link);
		$link_dest = $class::table()->table . "/show/" . $obj_or_link->id;
	} else
		$link_dest = $obj_or_link;

	if ($options["style"])
		array_push($link_options, "style=\"" . $options["style"] . "\"");

	if ($options["class"])
		array_push($link_options, "class=\"" . $options["class"] . "\"");

	if ($options["onclick"])
		array_push($link_options, "onClick=\"" . $options["onclick"] . "\"");

	return "<a href=\"" . $link_dest . "\""
		. (count($link_options) ? " " . join(" ", $link_options) : "")
		. ">" . $text . "</a>";
}

function redirect_to($obj_or_link) {
	if (is_object($obj_or_link)) {
		$class = get_class($obj_or_link);
		$link_dest = $class::table()->table . "/show/" . $obj_or_link->id;
	} else
		$link_dest = $obj_or_link;

	/* prevent any content from getting to the user */
	ob_end_clean();

	header("Location: " . $link_dest);

	/* and bail */
	exit;
}

function stylesheet_link_tag($files) {
	$out = "";

	if (!is_array($files))
		$files = array($files);

	foreach ($files as $file) {
		$file .= ".css";

		$mtime = 0;
		if (file_exists(HALFMOON_ROOT . "/public/stylesheets/" . $file))
			$mtime = filemtime(HALFMOON_ROOT . "/public/stylesheets/" . $file);

		$out .= "<link href=\"/stylesheets/" . $file
			. ($mtime ? "?" . $mtime : "") . "\" media=\"screen\" "
			. "rel=\"stylesheet\" type=\"text/css\"/>\n";
	}

	return $out;
}

function javascript_include_tag($files) {
	$out = "";

	if (!is_array($files))
		$files = array($files);

	foreach ($files as $file) {
		$file .= ".js";

		$mtime = 0;
		if (file_exists(HALFMOON_ROOT . "/public/javascripts/" . $file))
			$mtime = filemtime(HALFMOON_ROOT . "/public/javascripts/" . $file);

		$out .= "<script type=\"text/javascript\" src=\"/javascripts/" . $file
			. ($mtime ? "?" . $mtime : "") . "\"></script>\n";
	}

	return $out;
}

?>
