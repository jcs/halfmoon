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

	public $redirected_to = null;

	public static function log_runtime($req) {
		$end_time = microtime(true);

		$framework_time = (float)($req->start_times["request"] -
			$req->start_times["init"]);
		$app_time = (float)($end_time - $req->start_times["app"]);
		$total_time = (float)($end_time - $req->start_times["init"]);

		if (\ActiveRecord\ConnectionManager::connection_count()) {
			$db_time = (float)\ActiveRecord\ConnectionManager::
				get_connection()->reset_database_time();
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

	public static function send_status_header($status) {
		header($_SERVER["SERVER_PROTOCOL"] . " " . $status, true, $status);
		header("Status: " . $status, true, $status);
	}

	public function __construct($url, $get_vars, $post_vars, $headers,
	$start_time = null) {
		$this->start_times["init"] = ($start_time ? $start_time
			: microtime(true));
		$this->start_times["request"] = microtime(true);

		$url_parts = parse_url($url);

		$this->url = $url;
		$this->scheme = @$url_parts["scheme"];
		$this->host = @$url_parts["host"];
		$this->port = @$url_parts["port"];
		$this->path = @$url_parts["path"];
		$this->query = @$url_parts["query"];

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

		$this->referrer = @$headers["HTTP_REFERER"];
	}

	/* pass ourself to the router and handle the url.  if it fails, try to
	 * handle it gracefully.  */
	public function process() {
		if (Config::log_level_at_least("short"))
			register_shutdown_function(array("\\HalfMoon\\Request",
				"log_runtime"), $this);

		try {
			Router::instance()->takeRouteForRequest($this);
		} catch (\Exception $e) {
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
	public function remote_ip() {
		if ($this->_remote_ip)
			return $this->_remote_ip;

		$remote_addr_list = array();
		if (isset($this->headers["REMOTE_ADDR"]))
			$remote_addr_list = explode(",", $this->headers["REMOTE_ADDR"]);

		foreach ($remote_addr_list as $addr)
			if (!preg_match(Request::TRUSTED_PROXIES, $addr))
				return ($this->_remote_ip = $addr);

		$forwarded_for = array();

		if (isset($this->headers["HTTP_X_FORWARDED_FOR"]))
			$forwarded_for = explode(",",
				$this->headers["HTTP_X_FORWARDED_FOR"]);

		if (isset($this->headers["HTTP_CLIENT_IP"])) {
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

	/* "GET", "PUT", etc. */
	public function request_method() {
		return strtoupper($this->headers["REQUEST_METHOD"]);
	}

	/* the user's browser as reported by the server */
	public function user_agent() {
		return $_SERVER["HTTP_USER_AGENT"];
	}
}

?>
