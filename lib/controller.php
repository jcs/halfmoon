<?php

namespace HalfMoon;

class ApplicationController {
	static $DEFAULT_CONTENT_TYPE = "text/html";

	/* array of methods to call before processing any actions, bailing if any
	 * return false
	 * e.g. static $before_filter = array(
	 *			"validate_logged_in_user",
	 *			"validate_admin" => array("only" => array("create")),
	 *			...
	 */
	static $before_filter = array();

	/* array of methods to call after processing actions, which will be passed
	 * all buffered output and must return new output */
	static $after_filter = array();

	/* things to verify (like the method used) before processing any actions */
	static $verify = array();

	/* if non-empty, recurse through GET/POST params and filter out the values
	 * of any parameter names that match, replacing them with '[FILTERED]' */
	static $filter_parameter_logging = array();

	/* per-controller session options, can be "off", "on", or a per-action
	 * setting like: array("on" => array("only" => array("foo", "bar"))) */
	static $session = "";

	/* specify a different layout than controller name or application */
	static $layout = array();

	/* protect all (or specific actions passed as an array) actions from
	 * forgery */
	static $protect_from_forgery = true;

	public $request = array();
	public $params = array();
	public $locals = array();

	/* this will be set to a helper object before rendering a template */
	public $helper = null;

	/* keep track of the content-type being sent */
	public $content_type = null;

	private $did_render = false;
	private $redirected_to = null;
	private $did_layout = false;

	/* set while we're processing a view so render() behaves differently */
	private $in_view = false;

	/* track what ob_get_level() was when we started buffering */
	private $start_ob_level = 0;

	private $etag;

	public function __construct($request) {
		$this->request = $request;
		$this->params = &$request->params;

		if (Config::log_level_at_least("full") && array_keys($this->params)) {
			$params_log = "  Parameters: ";

			/* the closure can't access static vars */
			$filters = static::$filter_parameter_logging;

			/* build a string of parameters, less the ones matching
			 * $filter_parameter_logging */
			$recursor = function($params) use (&$recursor, &$params_log,
			$filters) {
				$params_log .= "{";
				$done_first = false;
				foreach ($params as $k => $v) {
					if ($done_first)
						$params_log .= ", ";
					else
						$done_first = true;

					$params_log .= "\"" . $k . "\"=>";

					if (is_array($v))
						$recursor($v);
					else {
						$filter = false;

						if (is_array($filters) && !empty($filters)) {
							foreach ($filters as $field)
								if (preg_match("/" . preg_quote($field, "/")
								. "/i", $k)) {
									$filter = true;
									break;
								}
						}

						if ($filter)
							$params_log .= "[FILTERED]";
						else
							$params_log .= "\"" . $v . "\"";
					}
				}
				$params_log .= "}";
			};
			$recursor($this->params);

			Log::info($params_log);
		}
	}

	/* turn local class variables into $variables when rendering views */
	public function __set($name, $value) {
		$this->locals[$name] =& $value;
	}
	public function __get($name) {
		if (isset($this->locals[$name]))
			return $this->locals[$name];
		else
			return null;
	}
	public function __isset($name) {
		return isset($this->locals[$name]);
	}
	public function __unset($name) {
		unset($this->locals[$name]);
	}

	/* store an error in the session to be printed on the next view with the
	 * flash_errors() helper */
	public function add_flash_error($string) {
		if (!isset($_SESSION["flash_errors"]) ||
		!is_array($_SESSION["flash_errors"]))
			$_SESSION["flash_errors"] = array();

		array_push($_SESSION["flash_errors"], $string);
	}

	/* store a notice in the session to be printed on the next view with the
	 * flash_notices() helper */
	public function add_flash_notice($string) {
		if (!isset($_SESSION["flash_notices"]) ||
		!is_array($_SESSION["flash_notices"]))
			$_SESSION["flash_notices"] = array();

		array_push($_SESSION["flash_notices"], $string);
	}

	/* cancel all buffered output, send a location: header and stop processing */
	public function redirect_to($obj_or_url) {
		$link = HTMLHelper::link_from_obj_or_string($obj_or_url);

		/* prevent any content from getting to the user */
		while (($l = ob_get_level()) && ($l >= $this->start_ob_level))
			ob_end_clean();

		if (Config::log_level_at_least("full"))
			Log::info("Redirected to " . $link);

		$this->redirected_to = $link;

		/* end session first so it can write the cookie */
		session_write_close();

		Request::send_status_header(302);
		header("Location: " . $link);
	}

	/* render a partial view, an action template, text, etc. */
	public function render($template, $vars = array()) {
		/* render(array("partial" => "somedir/file"), array("v" => $v)) */

		if (!is_array($template))
			$template = array("action" => $template);

		if (isset($template["status"]))
			Request::send_status_header($template["status"]);

		/* just render text with no layout */
		if (is_array($template) && array_key_exists("text", $template)) {
			if (!$this->content_type_set())
				$this->content_type = "text/plain";

			if (Config::log_level_at_least("full"))
				Log::info("Rendering text");

			print $template["text"];
		}

		/* just render json with no layout */
		elseif (is_array($template) && array_key_exists("json", $template)) {
			if (!$this->content_type_set())
				$this->content_type = "application/json";

			if (Config::log_level_at_least("full"))
				Log::info("Rendering json");

			/* there's no way to know if we were passed a json-encoded string,
			 * or a string that needs to be encoded, so just encode everything
			 * and hope the user figures it out */
			print json_encode($template["json"]);
		}

		/* assume we're dealing with files */
		else {
			$tf = "";

			/* render a partial template */
			if (is_array($template) && isset($template["partial"]))
				$tf = $template["partial"];

			/* render an action template */
			elseif (is_array($template) && isset($template["action"]))
				$tf = $template["action"];

			/* just a filename, render it as an action */
			elseif (is_array($template))
				$tf = join("", array_values($template));

			/* just a filename, render it as an action */
			else
				$tf = $template;

			/* if we have no directory, assume it's the in the current
			 * controller's views */
			if (!strpos($tf, "/"))
				$tf = strtolower(preg_replace("/Controller$/", "",
					Utils::current_controller_name())) . "/" . $tf;

			/* partial template files start with _ */
			if (is_array($template) && isset($template["partial"]))
				$tf = dirname($tf) . "/_" . basename($tf);

			/* do the actual renders */

			/* regular php/html */
			if (file_exists($full_file = HALFMOON_ROOT . "/views/"
			. $tf . ".phtml")) {
				if (!$this->content_type_set())
					$this->content_type = "text/html";

				$this->_really_render_file($full_file, $vars);
			}

			/* xml */
			elseif (file_exists($xml_file = HALFMOON_ROOT . "/views/"
			. $tf . ".pxml")) {
				if (!$this->content_type_set())
					$this->content_type = "application/xml";

				$this->_really_render_file($xml_file, $vars);
			}

			/* php-javascript */
			elseif (file_exists($js_file = HALFMOON_ROOT . "/views/"
			. $tf . ".pjs")) {
				if (!$this->content_type_set())
					$this->content_type = "text/javascript";

				$this->_really_render_file($js_file, $vars);
			}

			else
				throw new MissingTemplate("no template file " . $full_file);
		}

		if (!$this->in_view) {
			if (is_array($template) && array_key_exists("layout", $template))
				$this::$layout = $template["layout"];

			elseif ($this->content_type_set() &&
			$this->content_type != static::$DEFAULT_CONTENT_TYPE)
				/* if we were called from the controller, we're not outputting
				 * html, and no layout was explicitly specified, we probably
				 * don't want a layout */
				$this::$layout = false;
		}

		$this->did_render = true;
	}

	/* do render() but capture all the output and return it */
	public function render_to_string($template, $vars = array()) {
		$old_did_render = $this->did_render;

		/* store current content-type in case render() changes it */
		$ct = $this->content_type;

		ob_start();
		$this->render($template, $vars);
		$output = ob_get_contents();
		ob_end_clean();

		$this->did_render = $old_did_render;
		$this->content_type = $ct;

		return $output;
	}

	/* a private function to avoid taining the variable space after the
	 * require() */
	private function _really_render_file($__file, $__vars) {
		/* XXX: should this be checking for more special variable names? */

		$__special_vars = array("__special_vars", "__vars", "__file",
			"controller");

		/* export variables set in the controller to the view */
		foreach ((array)$this->locals as $__k => $__v) {
			if (in_array($__k, $__special_vars)) {
				Log::warn("tried to redefine \$" . $__k . " passed from "
					. "controller");
				continue;
			}

			$$__k = $__v;
		}

		/* and any passed as locals to the render() function */
		foreach ((array)$__vars as $__k => $__v) {
			if (in_array($__k, $__special_vars)) {
				Log::warn("tried to redefine \$" . $__k . " passed "
					. "from render() call");
				continue;
			}

			$$__k = $__v;
		}

		/* make helpers available to the view */
		$this->bring_in_helpers();
		foreach ($this->_helper_refs as $__hn => $__hk) {
			$$__hn = $__hk;
			$$__hn->controller = $this;
			$$__hn->C = $this;
		}

		/* define $controller and $C where $this can't be used */
		$controller = $this;
		$C = $this;

		if (Config::log_level_at_least("full"))
			Log::info("Rendering " . $__file);

		$this->in_view = true;
		require($__file);
		$this->in_view = false;
	}

	/* setup each built-in helper to be $var = VarHelper, and the
	 * application_helper and controller-specific helper to be $helper */
	private $_helper_refs = null;
	private function bring_in_helpers() {
		if (!$this->_helper_refs) {
			$this->_helper_refs = array();

			foreach (get_declared_classes() as $class) {
				if (preg_match("/^HalfMoon\\\\(.+)Helper$/", $class, $m))
					$this->_helper_refs[strtolower($m[1])] = new $class;
			}

			/* bring in the application-wide helpers */
			if (file_exists($__f = HALFMOON_ROOT . "/helpers/"
			. "application_helper.php")) {
				require_once($__f);

				/* and a controller-specific one if it exists */
				$controller = preg_replace("/Controller$/", "",
					Utils::current_controller_name());

				if (file_exists($__f = HALFMOON_ROOT . "/helpers/"
				. strtolower($controller . "_controller.php"))) {
					require_once($__f);

					$n = $controller . "Helper";
					$this->_helper_refs["helper"] = new $n;
				}

				/* otherwise just make it the app-wide one */
				else
					$this->_helper_refs["helper"] = new \ApplicationHelper;
			}
		}
	}

	/* the main entry point for the controller, sent by the router */
	public function render_action($action) {
		$this->enable_or_disable_sessions($action);

		$this->verify_method($action);

		$this->protect_from_forgery($action);

		if (!$this->process_before_filters($action))
			return false;

		/* start our one output buffer that we'll pass to the after filters */
		ob_start();
		$this->start_ob_level = ob_get_level();

		/* we only want to allow calling public methods in controllers, to
		 * avoid users getting directly to before_filters and other utility
		 * functions */
		if (!in_array($action, Utils::get_public_class_methods($this)))
			throw new UndefinedFunction("controller \"" . get_class($this)
				. "\" does not have an action \"" . $action . "\"");

		call_user_func_array(array($this, $action), array());

		if (isset($this->redirected_to)) {
			$this->request->redirected_to = $this->redirected_to;
			return;
		}

		if (!$this->did_render)
			$this->render(array("action" => $this->params["controller"] . "/"
				. $action), $this->locals);

		if (!$this->did_layout)
			$this->render_layout($action);

		$this->process_after_filters($action);

		/* end session first so it can write the cookie */
		session_write_close();

		if (!$this->content_type_set())
			$this->content_type = static::$DEFAULT_CONTENT_TYPE;

		if (!$this->content_type_sent())
			header("Content-type: " . $this->content_type);

		/* flush out everything, we're done playing with buffers */
		ob_end_flush();
	}

	/* capture the output of everything rendered and put it within the layout */
	public function render_layout($action) {
		/* get all buffered output and turn them off, except for our one last
		 * buffer needed for after_filters */
		$content_for_layout = "";
		while (ob_get_level() >= $this->start_ob_level) {
			$content_for_layout = $content_for_layout . ob_get_contents();

			if (ob_get_level() == $this->start_ob_level)
				break;
			else
				ob_end_clean();
		}

		/* now that we have all of our content, clean our last buffer since
		 * we're going to print the layout (and content inside) into it */
		ob_clean();

		$tlayout = null;
		if ($this::$layout === false)
			$tlayout = false;
		else {
			$opts = Utils::options_for_key_from_options_hash($action,
				$this::$layout);
			if (!empty($opts[0]))
				$tlayout = $opts[0];
		}

		/* if we don't want a layout at all, just print the content */
		if ($tlayout === false || $tlayout === "false") {
			print $content_for_layout;
			return;
		}

		/* if no specific layout was set, check for a controller-specific one */
		if (!$tlayout && isset($this->params["controller"]) &&
		file_exists(HALFMOON_ROOT . "/views/layouts/" . $this->params["controller"]
		. ".phtml"))
			$tlayout = $this->params["controller"];

		/* otherwise, default to "application" */
		if (!$tlayout)
			$tlayout = "application";

		$this->did_layout = true;

		if (!file_exists(HALFMOON_ROOT . "/views/layouts/" . $tlayout .
		".phtml"))
			 throw new MissingTemplate("no layout file " . $tlayout .  ".phtml");

		/* make helpers available to the layout */
		$this->bring_in_helpers();
		foreach ($this->_helper_refs as $__hn => $__hk) {
			$$__hn = $__hk;
			$$__hn->controller = $this;
			$$__hn->C = $this;
		}

		/* define $controller where $this can't be used */
		$controller = $this;
		$C = $this;

		if (Config::log_level_at_least("full"))
			Log::info("Rendering layout " . $tlayout);

		require(HALFMOON_ROOT . "/views/layouts/" . $tlayout . ".phtml");
	}

	public function form_authenticity_token() {
		/* explicitly enable sessions so we can store/retrieve the token */
		$this->start_session();

		if (@!$_SESSION["_csrf_token"])
			$_SESSION["_csrf_token"] = Utils::random_hash();

		return $_SESSION["_csrf_token"];
	}

	/* enable or disable sessions according to $session */
	private function enable_or_disable_sessions($action) {
		if (empty($this::$session) ||
		(!is_array($this::$session) && $this::$session == "off"))
			$sessions = false;
		elseif (is_array($this::$session)) {
			$opts = Utils::options_for_key_from_options_hash($action,
				$this::$session);

			if ($opts == array("on"))
				$sessions = true;
		} else
			$sessions = true;

		if ($sessions) {
			session_cache_expire(0);
			session_cache_limiter("private_no_expire");
			$this->start_session();
		} else
			session_cache_limiter("public");
	}

	/* verify any options requiring verification */
	private function verify_method($action) {
		$to_verify = Utils::options_for_key_from_options_hash($action,
			$this::$verify);

		if (empty($to_verify))
			return;

		foreach ($to_verify as $v) {
			if (isset($v["method"])) {
				if (strtolower($this->request->request_method()) !=
				strtolower($v["method"])) {
					if (isset($v["redirect_to"]))
						return $this->redirect_to($v["redirect_to"]);
					else
						throw new BadRequest();
				}
			}

			/* TODO: support other verify options from rails
			 * http://railsapi.com/doc/v2.3.2/classes/ActionController/Verification/ClassMethods.html#M000331
			 */
		}
	}

	/* xsrf protection: verify the passed authenticity token for non-GET
	 * requests */
	private function protect_from_forgery($action) {
		if (!$this::$protect_from_forgery)
			return;

		if (strtoupper($this->request->request_method()) == "GET")
			return;

		if (Utils::option_applies_for_key($action,
		$this::$protect_from_forgery)) {
			if (@$this->params["authenticity_token"] !=
			$this->form_authenticity_token())
				 throw new InvalidAuthenticityToken();
		}
	}

	/* return false if any before_filters return false */
	private function process_before_filters($action) {
		$filters = Utils::options_for_key_from_options_hash($action,
			$this::$before_filter);

		foreach ($filters as $filter) {
			if (!method_exists($this, $filter))
				throw new UndefinedFunction("before_filter \"" . $filter
					. "\" function does not exist");

			if (!call_user_func_array(array($this, $filter), array())) {
				if (Config::log_level_at_least("short"))
					Log::info("Filter chain halted as " . $filter
						. " did not return true.");

				return false;
			}
		
			if (isset($this->redirected_to))
				return false;
		}

		return true;
	}

	/* pass all buffered output through after filters */
	private function process_after_filters($action) {
		$filters = Utils::options_for_key_from_options_hash($action,
			$this::$after_filter);

		foreach ($filters as $filter) {
			if (!method_exists($this, $filter))
				throw new UndefinedFunction("after_filter \"" . $filter
					. "\" function does not exist");

			/* get all buffered output, then replace it with the filtered
			 * output */
			$output = ob_get_contents();
			$output = call_user_func_array(array($this, $filter),
				array($output));
			ob_clean();
			print $output;
		}
	}

	private function start_session() {
		try {
			if (!session_id())
				session_start();
		} catch (\HalfMoon\InvalidCookieData $e) {
			/* probably a decryption failure.  rather than assume the user is
			 * an attacker, try to invalidate their session so they get a new
			 * cookie.  on a reload, they'll at least start with a clean
			 * session instead of continuing to get 500 errors forever. */
			session_destroy();

			/* and then throw the error so they see a 500 and see that
			 * something was wrong, which may help explain their new session on
			 * the reload. */
			throw $e;
		}
	}

	private function content_type_set() {
		if (empty($this->content_type))
			if ($ct = $this->content_type_sent())
				$this->content_type = $ct;

		return !empty($this->content_type);
	}

	private function content_type_sent() {
		foreach ((array)headers_list() as $header)
			if (preg_match("/^Content-type: (.+)/i", $header, $m))
				return $m[1];

		return null;
	}
}

?>
