<?php
/*
	helper functions available everywhere
*/

/* like link_to but use a button */
function button_to($text, $obj_or_url, $options = array()) {
	if (!$options["method"])
		$options["method"] = "post";

	return "<input type=\"button\" value=\"" . $text . "\" "
		. options_for_link($options, link_from_obj_or_string($obj_or_url))
		. " />";
}

/* summarize errors for an activerecord object */
function error_messages_for($obj) {
	if ($obj->errors && count($obj->errors)) {
		$obj_name = ActiveRecord\Utils::singularize(strtolower(
			get_class($obj)));

		if (ActiveRecord\Utils::pluralize_if(2, $obj_name) == "2 " . $obj_name)
		  $obj_name = "these " . $obj_name;
		else
		  $obj_name = "this " . $obj_name;

		$html = "<p>"
			. "<div class=\"flash-error\">"
			. "<strong>" . count($obj->errors) . " "
			. ActiveRecord\Utils::pluralize_if(count($obj->errors),
				"error")
			. " prohibited " . $obj_name . " from being "
			. ($obj->is_new_record() ? "created" : "saved") . ":</strong>"
			. "<br />\n";

		foreach ($obj->errors as $err) {
			$html .= $err . "<br />";
		}

		$html .= "</div>"
			. "</p>";

		return $html;
	}
}

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

/* return a url string from an object/array or a url string */
function link_from_obj_or_string($thing) {
	$link = "";

	/* passed an object, redirect to its show url */
	if (is_object($thing)) {
		$class = get_class($thing);
		$link = "/" . $class::table()->table . "/show/" . $thing->id;
	}

	/* passed an array, figure out what to do */
	elseif (is_array($thing)) {
		$link = "/";

		if ($thing["controller"])
			$link .= $thing["controller"];
		else
			$link .= strtolower(preg_replace("/Controller$/", "",
				current_controller()));

		if ($thing["action"]) {
			$link .= "/" . $thing["action"];

			if ($thing["id"])
				$link .= "/" . $thing["id"];
		}

		/* anything else in the array is assumed to be passed as get args */
		$url_params = "";
		foreach ($thing as $k => $v) {
			if (in_array($k, array("controller", "action", "id")))
				continue;

			$url_params .= ($url_params == "" ? "" : "&") . urlencode($k)
				. "=" . urlencode($v);
		}

		if ($url_params != "")
			$link .= "?" . $url_params;
	}

	/* assume we were passed a url */
	else
		$link = $thing;
	
	return $link;
}

/* create an <a href> tag for a given url or object (defaulting to
 * the (table)/show/(id) */
function link_to($text, $obj_or_url, $options = array()) {
	return "<a href=\"" . link_from_obj_or_string($obj_or_url) . "\""
		. options_for_link($options) . ">" . $text . "</a>";
}

function options_for_link($options = array(), $button_target = null) {
	$opts_s = "";

	if ($options["confirm"] && is_bool($options["confirm"]))
		$options["confirm"] = "Are you sure?";

	if ($options["method"]) {
		$opts_s .= " onclick=\"";

		if ($options["confirm"])
			$opts_s .= "if (confirm('" . $options["confirm"] . "')) ";

		$opts_s .= "{ var f = document.createElement('form'); "
			. "f.style.display = 'none'; "
			. "this.parentNode.appendChild(f); "
			. "f.method = '" . $options["method"] . "'; "
			. "f.action = " . ($button_target ? "'" . $button_target . "'" :
				"this.href") . "; "
			. "f.submit(); }; return false;\"";

		unset($options["confirm"]);
		unset($options["method"]);
	}

	if ($options["confirm"]) {
		$opts_s .= " onclick=\"return confirm('" . $options["confirm"]
			. "');\"";
		unset($options["confirm"]);
	}

	if ($options["popup"]) {
		$opts_s .= " onclick=\"window.open(this.href); return false;\"";
		unset($options["popup"]);
	}

	foreach ($options as $k => $v)
		$opts_s .= " " . $k . "=\"" . $v . "\"";

	return $opts_s;
}

/* cancel all buffered output, send a location: header, and exit */
function redirect_to($obj_or_url) {
	$link = link_from_obj_or_string($obj_or_url);

	/* prevent any content from getting to the user */
	while (ob_end_clean())
		;

	header("Location: " . $link);

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
