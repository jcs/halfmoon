<?php
/*
	an individual HTTP request
*/

namespace HalfMoon;

class Request {
	const TRUSTED_PROXIES = '/^127\.0\.0\.1$|^(10|172\.(1[6-9]|2[0-9]|30|31)|192\.168)\./i';

	private $start_time;

	public $url, $scheme, $host, $port, $path, $query;

	public $referrer;

	public $get = array();
	public $post = array();
	public $params = array();

	public $headers = array();

	public function __construct($url, $get_vars, $post_vars, $headers) {
		$this->start_time = microtime(true);

		$url_parts = parse_url($url);

		$this->url = $url;
		$this->scheme = $url_parts["scheme"];
		$this->host = $url_parts["host"];
		$this->port = $url_parts["port"];
		$this->path = $url_parts["path"];
		$this->query = $url_parts["query"];

		/* strip leading and trailing slashes, then again in case some were
		 * hiding */
		$this->path = trim(preg_replace("/^\/*/", "", preg_replace("/\/$/", "",
			trim($this->path))));

		/* store get and post vars in $params first according to php's
		   variables_order setting (EGPCS by default) */
		foreach (str_split(ini_get("variables_order")) as $vtype) {
			$varray = null;

			switch (strtoupper($vtype)) {
			case "P":
				$varray = $post_vars;
				break;
			case "G":
				$varray = $get_vars;
				break;
			}

			if ($varray)
				foreach ($varray as $k => $v) {
					/* look for arrays that might be inside this array */
					if (is_array($v)) {
						$newv = array();

						/* TODO: recurse */
						foreach ($v as $vk => $vv) {
							if (preg_match("/^([^\[]+)\[([^\]]+)\]?$/", $vk, $m)) {
								if (!is_array($newv[$m[1]]))
									$newv[$m[1]] = array();

								$newv[$m[1]][$m[2]] = $vv;
							} else
								$newv[$vk] = $vv;
						}

						$this->params[$k] = $newv;
					} else
						$this->params[$k] = $v;
				}
		}

		$this->get = $get_vars;
		$this->post = $post_vars;

		$this->headers = $headers;

		$this->referrer = $headers["HTTP_REFERER"];
	}

	/* pass ourself to the router and handle the url.  if it fails, try to
	 * handle it gracefully.  */
	public function process() {
		try {
			Router::instance()->routeRequest($this);

			$total_time = (float)(microtime(time()) - $this->start_time);
			if (\ActiveRecord\ConnectionManager::connection_count())
				$db_time = (float)\ActiveRecord\ConnectionManager::
					get_connection()->reset_database_time();

			Log::info("Completed in " . sprintf("%0.5f", $total_time)
				. (isset($db_time) ? " | DB: " . sprintf("%0.5f", $db_time)
					. " (" . intval(($db_time / $total_time) * 100) . "%)" : "")
				. " [" . $this->url . "]");
		}
		
		catch (\Exception $e) {
			$this->rescue($e);
		}
	}

	/* determine originating IP address.  REMOTE_ADDR is the standard but will
	 * fail if the user is behind a proxy.  HTTP_CLIENT_IP and/or
	 * HTTP_X_FORWARDED_FOR are set by proxies so check for these if
	 * REMOTE_ADDR is a proxy. HTTP_X_FORWARDED_FOR may be a comma- delimited
	 * list in the case of multiple chained proxies; the last address which is
	 * not trusted is the originating IP. */
	private $_remote_ip;
	public function remote_ip() {
		if ($this->_remote_ip)
			return $this->_remote_ip;

		$remote_addr_list = array();
		if ($this->headers["REMOTE_ADDR"])
			$remote_addr_list = explode(",", $this->headers["REMOTE_ADDR"]);

		foreach ($remote_addr_list as $addr)
			if (!preg_match(Request::TRUSTED_PROXIES, $addr))
				return ($this->_remote_ip = $addr);

		$forwarded_for = array();

		if ($this->headers["HTTP_X_FORWARDED_FOR"])
			$forwarded_for = explode(",",
				$this->headers["HTTP_X_FORWARDED_FOR"]);

		if ($this->headers["HTTP_CLIENT_IP"]) {
			if (!in_array($this->headers["HTTP_CLIENT_IP"], $forwarded_for))
				throw new HalfMoonException("IP spoofing attack? "
					. "HTTP_CLIENT_IP="
					. var_export($this->headers["HTTP_CLIENT_IP"], true)
					. ", HTTP_X_FORWARDED_FOR="
					. var_export($this->headers["HTTP_X_FORWARDED_FOR"], true));

			return ($this->_remote_ip = $this->headers["HTTP_CLIENT_IP"]);
		}

		if (count($forwarded_for)) {
			while (count($forwarded_for) > 1 &&
			preg_match(Request::TRUSTED_PROXIES, trim(end($forwarded_for))))
				array_pop($forwarded_for);

			return ($this->_remote_ip = trim(end($forwarded_for)));
		}

		return ($this->_remote_ip = $this->headers["REMOTE_ADDR"]);
	}

	/* "GET", "PUT", etc. */
	public function request_method() {
		return strtoupper($this->headers["REQUEST_METHOD"]);
	}

	/* exception handler, log it and pass it off to rescue_in_public */
	private function rescue($exception) {
		/* kill all buffered output */
		while (ob_end_clean())
			;

		$str = get_class($exception);

		/* activerecord includes the stack trace in the message, so strip it
		 * out */
		if ($exception instanceof \ActiveRecord\DatabaseException)
			$str .= ": " . preg_replace("/\nStack trace:.*/s", "",
				$exception->getMessage());
		elseif ($exception->getMessage())
			$str .= ": " . $exception->getMessage();

		Log::error($str . ":");

		foreach ($exception->getTrace() as $call)
			Log::error("    " . ($call["file"] ? $call["file"]
				: $call["class"]) . ":" . $call["line"] . " in "
				. $call["function"] . "()");

		return $this->rescue_in_public($exception, $str);
	}

	/* return a friendly error page to the user (or a full one with debugging
	 * if we're in development mode with display_errors turned on) */
	private function rescue_in_public($exception, $title) {
		if (HALFMOON_ENV == "development" && ini_get("display_errors"))
			require_once(dirname(__FILE__) . "/rescue.phtml");
		else {
			/* production mode, try to handle gracefully */

			if ($exception instanceof \HalfMoon\RoutingException) {
				header($_SERVER["SERVER_PROTOCOL"] . " 404 File Not Found");

				if (file_exists($f = HALFMOON_ROOT . "/public/404.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>File Not Found</title>
					</head>
					<body>
					<h1>File Not Found</h1>
					The file you requested could not be found.  An additional error
					occured while processing the error document.
					</body>
					</html>
					<?
				}
			}
			
			elseif ($exception instanceof \HalfMoon\InvalidAuthenticityToken) {
				/* be like rails and give the odd 422 status */
				header($_SERVER["SERVER_PROTOCOL"] . " 422 Unprocessable Entity");

				if (file_exists($f = HALFMOON_ROOT . "/public/422.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>Change Rejected</title>
					</head>
					<body>
					<h1>Change Rejected</h1>
					The change you submitted was rejected due to a security
					problem.  An additional error occured while processing the
					error document.
					</body>
					</html>
					<?
				}
			}
			
			else {
				header($_SERVER["SERVER_PROTOCOL"] . " 500 Server Error");

				if (file_exists($f = HALFMOON_ROOT . "/public/500.html")) {
					Log::error("Rendering " . $f);
					require_once($f);
				} else {
					/* failsafe */
					?>
					<html>
					<head>
					<title>Application Error</title>
					</head>
					<body>
					<h1>Application Error</h1>
					An internal application error occured while processing your
					request.  Additionally, an error occured while processing the
					error document.
					</body>
					</html>
					<?
				}
			}
		}

		/* that's it, end of the line */
		exit;
	}
}

?>
