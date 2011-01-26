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
		try {
			Router::instance()->routeRequest($this);

			/* we now have a list of when each part of the process started, so
			 * we can build deltas to see how long each part took */
			$end_time = microtime(true);

			$framework_time = (float)($this->start_times["request"] -
				$this->start_times["init"]);
			$app_time = (float)($end_time - $this->start_times["app"]);
			$total_time = (float)($end_time - $this->start_times["init"]);

			if (\ActiveRecord\ConnectionManager::connection_count()) {
				$db_time = (float)\ActiveRecord\ConnectionManager::
					get_connection()->reset_database_time();
				$app_time -= $db_time;
			}

			Log::info("Completed in " . sprintf("%0.5f", $total_time)
				. (isset($db_time) ? " | DB: " . sprintf("%0.5f", $db_time)
					. " (" . intval(($db_time / $total_time) * 100) . "%)" : "")
				. " | App: " . sprintf("%0.5f", $app_time)
					. " (" . intval(($app_time / $total_time) * 100) . "%)"
				. " | Framework: " . sprintf("%0.5f", $framework_time)
					. " (" . intval(($framework_time / $total_time) * 100) . "%)"
				. " [" . $this->url . "]");
		}

		catch (\Exception $e) {
			/* rescue, log, notify (if necessary), exit */
			Rescuer::rescue_exception($e, $this);
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

	/* the user's browser as reported by the server */
	public function user_agent() {
		return $_SERVER["HTTP_USER_AGENT"];
	}
}

?>
