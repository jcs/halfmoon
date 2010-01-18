<?php
/*
	helper functions available everywhere
*/

/* print the errors stored in the session and then reset the array */
function flash_errors() {
	$html = "";

	if (count((array)$_SESSION["flash_errors"])) {
		$html = "<div class=\"flash-error\">"
			. implode("<br />\n", (array)$_SESSION["flash_errors"])
			. "</div>";

		/* clear out for the next view */
		$_SESSION["flash_errors"] = array();
	}

	return $html;
}

/* print the notices stored in the session and then reset the array */
function flash_notices() {
	$html = "";

	if (count((array)$_SESSION["flash_notices"])) {
		$html = "<div class=\"flash-notice\">"
			. implode("<br />\n", (array)$_SESSION["flash_notices"])
			. "</div>";

		/* clear out for the next view */
		$_SESSION["flash_notices"] = array();
	}

	return $html;
}

function form_for() {
	return call_user_func_array(array(new HalfMoon\FormHelper, "form_for"),
		func_get_args());
}

/* alias htmlspecialchars() to something smaller */
function h($text) {
	return htmlspecialchars($text);
}

/* create a link to a javascript file, appending its modification time to force
 * clients to reload it when it's modified */
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

/* create an <a href> tag for a given url or object (defaulting to
 * the (table)/show/(id) */
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

/* cancel all buffered output, send a location: header, and exit */
function redirect_to($obj_or_link) {
	/* passed an object, redirect to its show url */
	if (is_object($obj_or_link)) {
		$class = get_class($obj_or_link);
		$link_dest = $class::table()->table . "/show/" . $obj_or_link->id;
	}

	/* passed an array, figure out what to do */
	elseif (is_array($obj_or_link)) {
		$link_dest = "/";

		if ($obj_or_link["controller"])
			$link_dest .= $obj_or_link["controller"];
		else
			$link_dest .= strtolower(preg_replace("/Controller$/", "",
				current_controller()));

		if ($obj_or_link["action"]) {
			$link_dest .= "/" . $obj_or_link["action"];

			if ($obj_or_link["id"])
				$link_dest .= "/" . $obj_or_link["id"];
		}

		$url_params = "";
		foreach ($obj_or_link as $k => $v) {
			if (in_array($k, array("controller", "action", "id")))
				continue;

			$url_params .= ($url_params == "" ? "" : "&") . urlencode($k)
				. "=" . urlencode($v);
		}

		if ($url_params != "")
			$link_dest .= "?" . $url_params;
	}

	/* assume we were passed a url */
	else
		$link_dest = $obj_or_link;

	/* prevent any content from getting to the user */
	ob_end_clean();

	header("Location: " . $link_dest);

	/* and bail */
	exit;
}

/* create a link to a css file, appending its modification time to force
 * clients to reload it when it's modified */
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

?>
