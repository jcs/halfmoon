<?php
/*
	error and exception handling
*/

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

?>
