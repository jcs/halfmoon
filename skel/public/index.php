<?php
/*
	default file for which all requests should be routed from apache, unless a
	file with that name exists.  with mod_rewrite, this should look like:

		# enable mod_rewrite
		RewriteEngine on

		# if a file with that name exists in public/, just serve it directly
		RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f

		# otherwise route everything to halfmoon
		RewriteRule ^(.*)$ /index.php/%{REQUEST_URI} [QSA,L]
*/

require_once("../halfmoon/interfaces/apache.php");

?>
