<?php
/*
	html-related helpers
*/

namespace HalfMoon;

class HtmlHelper extends Helper {
	/* like link_to but use a button */
	public function button_to($text, $obj_or_url, $options = array()) {
		if (!isset($options["method"]))
			$options["method"] = "post";

		return "<input type=\"button\" value=\"" . raw_or_h($text) . "\" "
			. $this->options_for_link($options,
				$this->link_from_obj_or_string($obj_or_url))
			. " />";
	}

	public function button_to_function($label, $options = array()) {
		if (!is_array($options))
			$options = array("onclick" => $options);

		if (!isset($options["html_options"]))
			$options["html_options"] = array();

		if (!isset($options["html_options"]["type"]))
			$options["html_options"]["type"] = "button";

		return "<input"
			. " value=\"" . raw_or_h($label) . "\""
			. " onclick=\"" . raw_or_h($options["onclick"]) . "\""
			. $this->options_for_link($options["html_options"])
			. " />";
	}

	/* summarize errors for an activerecord object */
	public function error_messages_for($obj, $obj_name = "", $obj_prefix = "") {
		if ($obj->errors && $obj->errors->size()) {
			if ($obj_name == "")
				$obj_name = \ActiveRecord\Utils::singularize(strtolower(
					get_class($obj)));

			if ($obj_prefix == "") {
				if (\ActiveRecord\Utils::pluralize_if(2, $obj_name) == "2 "
				. $obj_name)
					$obj_prefix = "these";
				else
					$obj_prefix = "this";
			}

			$html = "<p>"
				. "<div class=\"flash-error\">"
				. "<strong>" . $obj->errors->size() . " "
				. h(\ActiveRecord\Utils::pluralize_if($obj->errors->size(),
					"error"))
				. " prohibited " . raw_or_h($obj_prefix) . " "
				. raw_or_h($obj_name) . " from being "
				. ($obj->is_new_record() ? "created" : "saved")
				. ":</strong><br />\n";

			foreach ($obj->errors as $err)
				$html .= raw_or_h($err) . "<br />";

			$html .= "</div>"
				. "</p>";

			return $html;
		}
	}

	/* print the errors stored in the session and then reset the array */
	public function flash_errors() {
		$html = "";

		if (isset($_SESSION["flash_errors"]) &&
		count((array)$_SESSION["flash_errors"])) {
			$html = "<div class=\"flash-error\">"
				. implode("<br />\n", array_map(function($e) {
					return raw_or_h($e); },
					(array)$_SESSION["flash_errors"]))
				. "</div>";

			/* clear out for the next view */
			$_SESSION["flash_errors"] = array();
		}

		return $html;
	}

	/* print the notices stored in the session and then reset the array */
	public function flash_notices() {
		$html = "";

		if (isset($_SESSION["flash_notices"]) &&
		count((array)$_SESSION["flash_notices"])) {
			/* not escaped */
			$html = "<div class=\"flash-notice\">"
				. implode("<br />\n", array_map(function($e) {
					return raw_or_h($e); },
					(array)$_SESSION["flash_notices"]))
				. "</div>";

			/* clear out for the next view */
			$_SESSION["flash_notices"] = array();
		}

		return $html;
	}

	/* print the successes stored in the session and then reset the array */
	public function flash_successes() {
		$html = "";

		if (isset($_SESSION["flash_successes"]) &&
		count((array)$_SESSION["flash_successes"])) {
			$html = "<div class=\"flash-success\">"
				. implode("<br />\n", array_map(function($e) {
					return raw_or_h($e); },
					(array)$_SESSION["flash_successes"]))
				. "</div>";

			/* clear out for the next view */
			$_SESSION["flash_successes"] = array();
		}

		return $html;
	}

	/* create a link to a javascript file, appending its modification time to
	 * force clients to reload it when it's modified */
	public function javascript_include_tag($files) {
		$out = "";

		if (!is_array($files))
			$files = array($files);

		foreach ($files as $file) {
			if (preg_match("/^(https?:)?\/\//", $file))
				$out .= "<script type=\"text/javascript\" src=\"" . $file
					. "\"></script>\n";
			else {
				$file_value = $file;

				if (!strpos($file_value, "?")) {
					if (!preg_match("/\.js$/i", $file_value))
						$file_value .= ".js";

					$mtime = 0;
					if (file_exists(HALFMOON_ROOT . "/public/javascripts/"
					. $file_value))
						$file_value .= "?" . filemtime(HALFMOON_ROOT
							. "/public/javascripts/" . $file_value);
				}

				$out .= "<script type=\"text/javascript\" src=\""
					. h("/javascripts/" . $file_value)
					. "\"></script>\n";
			}
		}

		return $out;
	}

	/* return a url string from an object/array or a url string */
	public function link_from_obj_or_string($thing) {
		$link = "";

		/* passed an object, redirect to its show url */
		if (is_object($thing)) {
			$class = get_class($thing);
			$link = "/" . $class::table()->table . "/show/" . $thing->id;
		}

		/* passed an array, figure out what to do */
		elseif (is_array($thing)) {
			$link = "/";

			/* if no controller, assume the current one */
			if (isset($thing["controller"]))
				$link .= $thing["controller"];
			else
				$link .= strtolower(preg_replace("/Controller$/", "",
					Utils::current_controller_name()));

			if (isset($thing["action"]))
				$link .= "/" . $thing["action"];

			if (isset($thing["id"]) && $thing["id"])
				$link .= "/" . $thing["id"];

			/* anything else in the array is assumed to be passed as get
			 * args */
			$url_params = "";
			foreach ($thing as $k => $v) {
				if (in_array($k, array("controller", "action", "id", "anchor")))
					continue;

				$url_params .= ($url_params == "" ? "" : "&") . urlencode($k)
					. "=" . urlencode($v);
			}

			if ($url_params != "")
				$link .= "?" . $url_params;

			if (isset($thing["anchor"]))
				$link .= "#" . h($thing["anchor"]);
		}

		/* assume we were passed a url */
		else
			$link = $thing;
		
		return $link;
	}

	/* create an <a href> tag for a given url or object (defaulting to
	 * the (table)/show/(id) */
	public function link_to($text, $obj_or_url, $options = array()) {
		return "<a href=\""
			. raw_or_h($this->link_from_obj_or_string($obj_or_url)) . "\""
			. $this->options_for_link($options) . ">" . raw_or_h($text)
			. "</a>";
	}

	public function options_for_link($options = array(),
	$button_target = null) {
		$opts_s = "";

		if (isset($options["confirm"]) && is_bool($options["confirm"]))
			$options["confirm"] = "Are you sure?";

		if (isset($options["method"])) {
			$opts_s .= " onclick=\"";

			if (isset($options["confirm"]))
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
					. "t.value = '" . h(Utils::current_controller()->
						form_authenticity_token()) . "'; "
					. "f.appendChild(t); ";

			$opts_s .= "f.submit(); }; return false;\"";

			unset($options["confirm"]);
			unset($options["method"]);
		}

		if (isset($options["confirm"])) {
			$opts_s .= " onclick=\"return confirm('" . $options["confirm"]
				. "');\"";
			unset($options["confirm"]);
		}

		if (isset($options["popup"])) {
			$opts_s .= " onclick=\"window.open(this.href); return false;\"";
			unset($options["popup"]);
		}

		foreach ((array)$options as $k => $v)
			$opts_s .= " " . $k . "=\"" . $v . "\"";

		return $opts_s;
	}

	/* create a link to a css file, appending its modification time to force
	 * clients to reload it when it's modified */
	public function stylesheet_link_tag($files, $options = array()) {
		$out = "";

		if (!is_array($files))
			$files = array($files);

		foreach ($files as $file) {
			if (preg_match("/^(https?:)?\/\//", $file))
				$out .= "<link href=\"" . $file . "\" media=\"screen\" "
					. "rel=\"stylesheet\" type=\"text/css\"/>\n";
			else {
				$file_value = $file;

				if (!strpos($file_value, "?")) {
					if (!preg_match("/\.css$/i", $file_value))
						$file_value .= ".css";

					$mtime = 0;
					if (file_exists(HALFMOON_ROOT . "/public/stylesheets/"
					. $file_value))
						$file_value .= "?" . filemtime(HALFMOON_ROOT
							. "/public/stylesheets/" . $file_value);
				}

				if (isset($options["xml"]))
					$out .= "<?xml-stylesheet type=\"text/css\" "
						. "href=\"" . h("/stylesheets/" . $file_value)
						. "\" ?" . ">\n";
				else
					$out .= "<link href=\"/" . h("stylesheets/"
						. $file_value) . "\" media=\"screen\" "
						. "rel=\"stylesheet\" type=\"text/css\"/>\n";
			}
		}

		return $out;
	}
}

?>
