<?php
/*
	error and exception handling
*/

function halfmoon_exception_handler($exception) {
	$str = $exception->getMessage();

	if (HALFMOON_ENV == "development" && ini_get("display_errors")) {
		ob_end_clean();

		?>
		<html>
		<head>
			<title><?= h($str) ?></title>
			<style type="text/css">
				h2, h3, h4 {
					margin: 0;
				}
				div.info {
					background-color: #eee;
					padding: 10px;
					font-family: monospace;
				}
			</style>
		</head>
		<body>
		<h2><?= h($str) ?></h2>
		<p>
		<tt>HALFMOON_ROOT: <?= h(HALFMOON_ROOT) ?></tt>
		</p>
		<p>
		<h3>Backtrace</h3>
		</p>
		<p>
		<div class="info">
		<?

		$backtrace = $exception->getTrace();

		/* the top call is us */
		array_shift($backtrace);

		foreach ($backtrace as $call) {
			$fileparts = explode("/", $call["file"]);

			for ($x = 0; $x < count($fileparts); $x++) {
				$fileparts[$x] = h($fileparts[$x]);

				if ($x == count($fileparts) - 1)
					$fileparts[$x] = "<strong>" . $fileparts[$x]
						. "</strong>";
			}

			?>
			<?= join("/", $fileparts); ?>:<?= h($call["line"]) ?>
			in
			<?

			$args_s = "";
			foreach ($call["args"] as $arg)
				$args_s .= ($args_s == "" ? "" : ", ")
					. (is_array($arg) ? "" : "\"")
					. h(print_r($arg, true))
					. (is_array($arg) ? "" : "\"");

			?>
			<strong><?= h($call["function"]) ?>(<?= $args_s ?>)</strong>
			<br />
			<?
		}

		?>
		</div>
		</p>
		<p>
		<h3>Request</h3>
		</p>
		<p>
		<h4>GET Parameters</h4>
		</p>
		<p>
		<div class="info">
		<? foreach ($_GET as $k => $v) { ?>
			"<?= str_replace("\"", "\\\"", h($k)) ?>":
			"<?= str_replace("\"", "\\\"", h($v)) ?>"
		<? } ?><br />
		</div>
		</p>
		<p>
		<h4>POST Parameters</h4>
		</p>
		<p>
		<div class="info">
		<? foreach ($_POST as $k => $v) { ?>
			"<?= str_replace("\"", "\\\"", h($k)) ?>":
			"<?= str_replace("\"", "\\\"", h($v)) ?>"
		<? } ?><br />
		</div>
		</p>
		<p>
		<h3>Session</h3>
		</p>
		<p>
		<div class="info">
		<? foreach ($_SESSION as $k => $v) { ?>
			"<?= str_replace("\"", "\\\"", h($k)) ?>":
			"<?= str_replace("\"", "\\\"", h($v)) ?>"
		<? } ?><br />
		</div>
		</p>
		</body>
		</html>
		<?
	} else
		/* XXX: should these messages even be relayed in production? */
		die($str);
}

function throw_exception_from_error($errno, $errstr, $errfile, $errline) {
	$do_report = (bool)($errno & ini_get("error_reporting"));

    if (in_array($errno, array(E_USER_ERROR, E_RECOVERABLE_ERROR)) &&
	$do_report)
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    elseif ($do_report) {
        error_log($string);
        return false;
    }
}

/* make traditional errors throw exceptions so we can handle everything in one
 * place */
set_error_handler("throw_exception_from_error");

/* and handle our exceptions with a pretty html page */
set_exception_handler("halfmoon_exception_handler");

?>