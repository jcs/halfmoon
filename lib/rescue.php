<?php
/*
	error and exception handling
*/

function halfmoon_exception_handler($exception) {
	/* kill all buffered output */
	while (ob_end_clean())
		;

	if (HALFMOON_ENV == "development" && ini_get("display_errors")) {
		$str = $exception->getMessage();

		if ($str == "")
			$str = get_class($exception);

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

		foreach ($backtrace as $call) {
			if ($call["file"]) {
				$fileparts = explode("/", $call["file"]);

				for ($x = 0; $x < count($fileparts); $x++) {
					$fileparts[$x] = h($fileparts[$x]);

					if ($x == count($fileparts) - 1)
						$fileparts[$x] = "<strong>" . $fileparts[$x]
							. "</strong>";
				}

				?>
				<?= join("/", $fileparts); ?><?
			} else {
				?><?= h($call["class"]) ?><?
			}
			
			?>:<?= h($call["line"]) ?>
			in
			<strong><?= h($call["function"]) ?>()</strong>
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
		<? foreach ((array)$_GET as $k => $v) { ?>
			<strong><?= h(var_export($k, true)) ?></strong>:
				<?= h(var_export($v, true)) ?><br />
		<? } ?>
		</div>
		</p>

		<p>
		<h4>POST Parameters</h4>
		</p>
		<p>
		<div class="info">
		<? foreach ((array)$_POST as $k => $v) { ?>
			<strong><?= h(var_export($k, true)) ?></strong>:
				<?= h(var_export($v, true)) ?><br />
		<? } ?>
		</div>
		</p>

		<p>
		<h4>Uploaded Files</h4>
		</p>
		<p>
		<div class="info">
		<? foreach ((array)$_FILES as $k => $v) { ?>
			<strong><?= h(var_export($k, true)) ?></strong>:
				<?= h(var_export($v, true)) ?><br />
		<? } ?>
		</div>
		</p>

		<p>
		<h3>Session</h3>
		</p>
		<p>
		<div class="info">
		<? foreach ((array)$_SESSION as $k => $v) { ?>
			<?= h(var_export($k, true) . ": " . var_export($v, true)) ?><br />
		<? } ?>
		</div>
		</p>
		</body>
		</html>
		<?
	} else {
		/* production mode, try to handle gracefully */

		if ($exception instanceof HalfMoon\RoutingException) {
			header($_SERVER["SERVER_PROTOCOL"] . " 404 File Not Found");

			if (file_exists(HALFMOON_ROOT . "/public/404.html"))
				require_once(HALFMOON_ROOT . "/public/404.html");
			else {
				/* failsafe */
				?>
				<html>
				<head>File Not Found</head>
				<body>
				<h1>File Not Found</h1>
				The file you requested could not be found.  An additional error
				occured while processing the error document.
				</body>
				</html>
				<?
			}
		}
		
		elseif ($exception instanceof HalfMoon\InvalidAuthenticityToken) {
			/* be like rails and give the odd 422 status */
			header($_SERVER["SERVER_PROTOCOL"] . " 422 Unprocessable Entity");

			if (file_exists(HALFMOON_ROOT . "/public/422.html"))
				require_once(HALFMOON_ROOT . "/public/422.html");
			else {
				/* failsafe */
				?>
				<html>
				<head>Change Rejected</head>
				<body>
				<h1>Change Rejected</h1>
				The change you submitted was rejected due to a security
				problem.  An additional error occured while processing the
				error document.
				</body>
				</html>
				<?
			}

		} else {
			header($_SERVER["SERVER_PROTOCOL"] . " 500 Server Error");

			if (file_exists(HALFMOON_ROOT . "/public/500.html"))
				require_once(HALFMOON_ROOT . "/public/500.html");
			else {
				/* failsafe */
				?>
				<html>
				<head>Application Error</head>
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

		error_log($str);

		exit;
	}
}

function throw_exception_from_error($errno, $errstr, $errfile, $errline) {
	$do_report = (bool)($errno & ini_get("error_reporting"));

    if (in_array($errno, array(E_USER_ERROR, E_RECOVERABLE_ERROR)) &&
	$do_report)
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    elseif ($do_report)
        return false;
}

/* make traditional errors throw exceptions so we can handle everything in one
 * place */
set_error_handler("throw_exception_from_error");

/* and handle our exceptions with a pretty html page */
set_exception_handler("halfmoon_exception_handler");

?>
