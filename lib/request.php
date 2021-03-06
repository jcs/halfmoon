<?php
/*
	an individual HTTP request
*/

namespace HalfMoon;

class Request {
	const TRUSTED_PROXIES = '/^127\.0\.0\.1$|^(10|172\.(1[6-9]|2[0-9]|30|31)|192\.168)\./i';

	public $start_times = array();

	public $url, $scheme, $host, $port, $path, $query;

	public $referrer;

	public $get = array();
	public $post = array();
	public $params = array();

	public $headers = array();

	public $etag = null;
	public $redirected_to = null;

	public function __get($name) {
		if (method_exists($this, "get_" . $name)) {
			$name = "get_" . $name;
			return $this->$name();
		}

		throw new Exception("invalid property " . $name);
	}

	/* a register_shutdown function that will log some stats about the time it
	 * took to process and the url */
	public static function log_runtime($req) {
		$end_time = microtime(true);

		$framework_time = (float)($req->start_times["request"] -
			$req->start_times["init"]);
		if (isset($req->start_times["app"]))
			$app_time = (float)($end_time - $req->start_times["app"]);
		$total_time = (float)($end_time - $req->start_times["init"]);

		if (class_exists('\ActiveRecord\ConnectionManager') &&
		\ActiveRecord\ConnectionManager::connection_count()) {
			$db_time = (float)\ActiveRecord\ConnectionManager::
				get_connection()->reset_database_time();

			if (isset($app_time))
				$app_time -= $db_time;
		}

		$status = "200";
		foreach (headers_list() as $header)
			if (preg_match("/^Status: (\d+)/", $header, $m))
				$status = $m[1];

		$log = "Completed in " . sprintf("%0.5f", $total_time);

		if (isset($db_time))
			$log .= " | DB: " . sprintf("%0.5f", $db_time) . " ("
				. intval(($db_time / $total_time) * 100) . "%)";

		if (isset($app_time))
			$log .= " | App: " . sprintf("%0.5f", $app_time) . " ("
				. intval(($app_time / $total_time) * 100) . "%)";

		$log .= " | Framework: " . sprintf("%0.5f", $framework_time)
			. " (" . intval(($framework_time / $total_time) * 100)
			. "%)";

		$log .= " | " . $status . " [" . $req->url . "]";

		if (isset($req->redirected_to))
			$log .= " -> [" . $req->redirected_to . "]";

		Log::info($log);
	}

	/* send both style status headers; the first for mod_php to actually see
	 * it, and the second so it shows up in headers_list() and for fastcgi */
	public static function send_status_header($status) {
		header($_SERVER["SERVER_PROTOCOL"] . " " . $status, true, $status);
		header("Status: " . $status, true, $status);
	}

	/* build a request from the web server interface */
	public function __construct($url, $get_vars, $post_vars, $headers,
	$start_time = null) {
		$this->start_times["init"] = ($start_time ? $start_time
			: microtime(true));
		$this->start_times["request"] = microtime(true);

		$url_parts = parse_url($url);

		$this->scheme = @$url_parts["scheme"];
		if (empty($this->scheme))
			$this->scheme = "http";

		$this->host = @$url_parts["host"];

		$this->port = @$url_parts["port"];
		if (empty($this->port)) {
			if (strtolower($this->scheme) == "https")
				$this->port = 443;
			else
				$this->port = 80;
		}

		/* normalize path, strip leading and trailing slashes */
		$this->path = @$url_parts["path"];
		if ($this->path == "")
			$this->path = "/";
		$path_dirs = explode("/", $this->path);
		$tpath = array();
		foreach ($path_dirs as $tdir) {
			if ($tdir == "" || $tdir == ".")
				continue;
			elseif ($tdir == "..") {
				array_pop($tpath);
				continue;
			}

			array_push($tpath, $tdir);
		}
		$this->path = join("/", $tpath);

		$this->query = @$url_parts["query"];

		$this->url = $this->scheme . "://" . $this->host;
		if ($this->port != 80 && $this->port != 443)
			$this->url .= ":" . $this->port;

		$this->url .= "/" . $this->path;

		if ($this->query != "")
			$this->url .= "?" . $this->query;

		/* if this looks like a request from ie's castrated XDomainRequest()
		 * then it didn't send a proper content-type, so try to read and parse
		 * it to put it into $_POST ourselves */
		if (empty($post_vars) && !empty($headers["REQUEST_METHOD"]) &&
		$headers["REQUEST_METHOD"] == "POST" &&
		!empty($headers["HTTP_ORIGIN"])) {
			if (empty($HTTP_RAW_POST_DATA))
				$HTTP_RAW_POST_DATA = file_get_contents("php://input");

			if (!empty($HTTP_RAW_POST_DATA)) {
				$pairs = explode("&", $HTTP_RAW_POST_DATA);
				foreach ($pairs as $pair) {
					if (!empty($pair)) {
						list($k, $v) = explode('=', $pair);
						$k = urldecode($k);
						$v = urldecode($v);

						if (preg_match("/^([^\[]+)\[([^\]]+)\]?$/", $k, $m)) {
							if (!is_array($post_vars[$m[1]]))
								$post_vars[$m[1]] = array();

							$post_vars[$m[1]][$m[2]] = $v;
						} else
							$post_vars[$k] = $v;
					}
				}
			}
		}

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

		$this->referrer = @$headers["HTTP_REFERER"];
	}

	public function __toString() {
		return $this->request_method() . " " . $this->url . " ("
			. $this->remote_ip() . ")";
	}

	/* pass ourself to the router and handle the url.  if it fails, try to
	 * handle it gracefully.  */
	public function process() {
		if (Config::log_level_at_least("short"))
			register_shutdown_function(array("\\HalfMoon\\Request",
				"log_runtime"), $this);

		try {
			ob_start();

			Router::instance()->takeRouteForRequest($this);

			/* if we received an If-None-Match header from the client and our
			 * generated etag matches it, send a not-modified header and no
			 * data */
			if (empty($this->redirected_to) && $this->etag_matches_inm()) {
				$headers = (array)headers_sent();
				ob_end_clean();
				foreach ($headers as $header)
					header($header);

				Request::send_status_header(304);
			}

			else {
				if (empty($this->redirected_to))
					$this->send_etag_header();

				if (ob_get_level())
					ob_end_flush();
			}
		}

		catch (\Exception $e) {
			/* rescue, log, notify (if necessary), exit */
			if (class_exists("\\HalfMoon\\Rescuer"))
				Rescuer::rescue_exception($e, $this);
			else
				throw $e;
		}
	}

	/* determine originating IP address.  REMOTE_ADDR is the standard but will
	 * fail if the user is behind a proxy.  HTTP_CLIENT_IP and/or
	 * HTTP_X_FORWARDED_FOR are set by proxies so check for these if
	 * REMOTE_ADDR is a proxy. HTTP_X_FORWARDED_FOR may be a comma- delimited
	 * list in the case of multiple chained proxies; the last address which is
	 * not trusted is the originating IP. */
	private $_remote_ip;
	public function get_remote_ip() {
		if ($this->_remote_ip)
			return $this->_remote_ip;

		$remote_addr_list = array();
		if (!empty($this->headers["REMOTE_ADDR"]))
			$remote_addr_list = explode(",", $this->headers["REMOTE_ADDR"]);

		foreach ($remote_addr_list as $addr)
			if (!preg_match(Request::TRUSTED_PROXIES, $addr))
				return ($this->_remote_ip = $addr);

		$forwarded_for = array();

		if (!empty($this->headers["HTTP_X_FORWARDED_FOR"]))
			$forwarded_for = explode(",",
				$this->headers["HTTP_X_FORWARDED_FOR"]);

		if (!empty($this->headers["HTTP_CLIENT_IP"])) {
			if (!in_array($this->headers["HTTP_CLIENT_IP"], $forwarded_for))
				throw new HalfMoonException("IP spoofing attack? "
					. "HTTP_CLIENT_IP="
					. var_export($this->headers["HTTP_CLIENT_IP"], true)
					. ", HTTP_X_FORWARDED_FOR="
					. var_export($this->headers["HTTP_X_FORWARDED_FOR"], true));

			return ($this->_remote_ip = $this->headers["HTTP_CLIENT_IP"]);
		}

		if (!empty($forwarded_for)) {
			while (count($forwarded_for) > 1 &&
			preg_match(Request::TRUSTED_PROXIES, trim(end($forwarded_for))))
				array_pop($forwarded_for);

			return ($this->_remote_ip = trim(end($forwarded_for)));
		}

		return ($this->_remote_ip = $this->headers["REMOTE_ADDR"]);
	}

	/* whether this was a request received over ssl */
	private $_ssl;
	public function get_ssl() {
		if (isset($this->_ssl))
			return $this->_ssl;

		return ($this->_ssl =
			(!empty($this->headers["HTTPS"]) &&
				$this->headers["HTTPS"] == "on") ||
			(!empty($this->headers["HTTP_X_FORWARDED_PROTO"]) &&
				$this->headers["HTTP_X_FORWARDED_PROTO"] == "https") ||
			(!empty($this->headers["SCRIPT_URI"]) && preg_match("/^https:/",
				$this->headers["SCRIPT_URI"]))
			);
	}

	/* "GET", "PUT", etc. */
	public function get_request_method() {
		return strtoupper($this->headers["REQUEST_METHOD"]);
	}

	/* the user's browser as reported by the server */
	public function get_user_agent() {
		return @$this->headers["HTTP_USER_AGENT"];
	}

	private function etag_matches_inm() {
		if (!empty($this->headers["HTTP_IF_NONE_MATCH"])) {
			$this->calculate_etag();
			if ($this->etag === $this->headers["HTTP_IF_NONE_MATCH"])
				return true;
		}

		return false;
	}

	private function send_etag_header() {
		$this->calculate_etag();
		header("ETag: " . $this->etag);
    }

	private function calculate_etag() {
		if (empty($this->etag))
			$this->etag = md5(ob_get_contents());
	}

	/* legacy functions that have been changed to pseudo-properties */
	public function remote_ip() {
		return $this->get_remote_ip();
	}
	public function ssl() {
		return $this->get_ssl();
	}
	public function request_method() {
		return $this->get_request_method();
	}
	public function user_agent() {
		return $this->get_user_agent();
	}
}

?>
