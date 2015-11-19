<?php
/*
	default file for which all requests should be routed from the web server,
	unless a static file with that name exists in public/.

	with apache and mod_rewrite, the configuration would look like:

		# enable mod_rewrite
		RewriteEngine on

		# if a file with that name exists in public/, just serve it directly
		RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f

		# otherwise route everything to halfmoon
		RewriteRule ^(.*)$ /index.php/%{REQUEST_URI} [QSA,L]

	for nginx+php_fpm, the configuration would look like:

		location / {
			root /var/www/.../public;

			include fastcgi_params;
			fastcgi_param SCRIPT_FILENAME /var/www/.../public/index.php;

			if (!-f $request_filename) {
				fastcgi_pass unix:/var/www/.../php-fpm.sock;
				break;
			}
		}

	depending on your web server, use only one uncommented require_once line
	below.
*/

# require_once("../halfmoon/interfaces/nginx.php");
require_once("../halfmoon/interfaces/apache.php");

?>
