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

/* reports the approximate distance between two times */
function distance_of_time_in_words($from_time, $to_time = 0, $include_seconds =
false) {
	if (get_class($from_time) != "DateTime")
		$from_time = new DateTime($from_time);
	
	if (get_class($to_time) != "DateTime")
		$to_time = new DateTime($to_time);

	$seconds_diff = intval($to_time->format("U")) -
		intval($from_time->format("U"));

	$distance_in_minutes = round(abs($seconds_diff / 60));
	$distance_in_seconds = round(abs($secons_diff));

	if (HalfMoon\Utils::is_or_between($distance_in_minutes, array(0, 1))) {
		if (!$include_seconds)
			return ($distance_in_minutes == 0 ? "less than 1 minute" :
				$distance_in_minutes . " minute"
				. ($distance_in_minutes == 1 ? "" : "s"));

		if (HalfMoon\Utils::is_or_between($distance_in_seconds, array(0, 4)))
			return "less than 5 seconds";
		elseif (HalfMoon\Utils::is_or_between($distance_in_seconds, array(5, 9)))
			return "less than 10 seconds";
		elseif (HalfMoon\Utils::is_or_between($distance_in_seconds, array(10, 19)))
			return "less than 20 seconds";
		elseif (HalfMoon\Utils::is_or_between($distance_in_seconds, array(20, 39)))
			return "less than half a minute";
		else
			return "1 minute";
	}

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(2, 44)))
		return $distance_in_minutes . " minutes";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(45, 89)))
		return "about 1 hour";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(90, 1439)))
		return "about " . round($distance_in_minutes / 60) . " hours";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(1440, 2879)))
		return "about 1 day";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(2880, 43199)))
		return "about " . round($distance_in_minutes / 1440) . " days";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(43200, 86399)))
		return "about 1 month";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(86400, 525599)))
		return "about " . round($distance_in_minutes / 43200) . " months";

	elseif (HalfMoon\Utils::is_or_between($distance_in_minutes, array(525600, 1051199)))
		return "about 1 year";

	else
		return "over " . round($distance_in_minutes / 525600) . " years";
}

/* summarize errors for an activerecord object */
function error_messages_for($obj) {
	if ($obj->errors && $obj->errors->size()) {
		$obj_name = ActiveRecord\Utils::singularize(strtolower(
			get_class($obj)));

		if (ActiveRecord\Utils::pluralize_if(2, $obj_name) == "2 " . $obj_name)
		  $obj_name = "these " . $obj_name;
		else
		  $obj_name = "this " . $obj_name;

		$html = "<p>"
			. "<div class=\"flash-error\">"
			. "<strong>" . $obj->errors->size() . " "
			. ActiveRecord\Utils::pluralize_if($obj->errors->size(),
				"error")
			. " prohibited " . $obj_name . " from being "
			. ($obj->is_new_record() ? "created" : "saved") . ":</strong>"
			. "<br />\n";

		foreach ($obj->errors as $err)
			$html .= $err . "<br />";

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
				current_controller_name()));

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
			. "f.method = '" . $options["method"] . "'; "
			. "f.action = " . ($button_target ? "'" . $button_target . "'" :
				"this.href") . "; "
			. "this.parentNode.appendChild(f); ";

		if (strtolower($options["method"]) != "get")
			$opts_s .= "var t = document.createElement('input'); "
				. "t.type = 'hidden'; t.name = 'authenticity_token'; "
				. "t.value = '" . h(HalfMoon\Utils::current_controller()->
					form_authenticity_token())
				. "'; f.appendChild(t); ";

		$opts_s .= "f.submit(); }; return false;\"";

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

/* create a link to a css file, appending its modification time to force
 * clients to reload it when it's modified */
function stylesheet_link_tag($files, $options = array()) {
	$out = "";

	if (!is_array($files))
		$files = array($files);

	foreach ($files as $file) {
		$file .= ".css";

		$mtime = 0;
		if (file_exists(HALFMOON_ROOT . "/public/stylesheets/" . $file))
			$mtime = filemtime(HALFMOON_ROOT . "/public/stylesheets/" . $file);

		if ($options["xml"])
			$out .= "<?xml-stylesheet type=\"text/css\" href=\"/stylesheets/"
				. $file . ($mtime ? "?" . $mtime : "") . "\" ?>\n";
		else
			$out .= "<link href=\"/stylesheets/" . $file
				. ($mtime ? "?" . $mtime : "") . "\" media=\"screen\" "
				. "rel=\"stylesheet\" type=\"text/css\"/>\n";
	}

	return $out;
}

/* like distance_of_time_in_words, but where to_time is fixed to now */
function time_ago_in_words($from_time, $include_seconds = false) {
	$now = new DateTime("now");

	if (get_class($from_time) == "DateTime")
		$now->setTimezone($from_time->getTimezone());
	elseif (is_int($from_time))
		$from_time = new DateTime($from_time);

	return distance_of_time_in_words($from_time, $now, $include_seconds);
}

?>
