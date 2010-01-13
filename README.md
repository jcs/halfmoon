# vim:ts=4:tw=72

	 ,-.
	( (  halfmoon tiny mvc framework for php
	 `-'

## requirements ##

-	php 5.3 or higher, along with the database extensions you wish to
	use (mysql, sqlite3, etc.)

-	apache 1 or 2, with mod_rewrite enabled


## installation ##

1.	(optional) create the root directory where you will be storing
	everything.  halfmoon will do this for you but if you are creating
	it somewhere where you need sudo permissions, do it manually:

		sudo mkdir /var/www/example/
		sudo chown `whoami` /var/www/example

2.	fetch the halfmoon source code:

		git pull git://github.com/jcs/halfmoon.git

3.	run the halfmoon script to create your skeleton directory at your
	root directory created in step 1:

		cd halfmoon
		./halfmoon create /var/www/example/

4.	setup an apache virtual host with a DocumentRoot pointing to the
    public/ directory:

		<VirtualHost 127.0.0.1>
			ServerName www.example.com
			CustomLog logs/example_access combined
			ErrorLog logs/example_error

			DocumentRoot /var/www/example/public/

			# try static (cached) pages before dynamic ones
			DirectoryIndex index.html index.php

			# uncomment in a production environment, otherwise we are
			# assuming to be running in development
			#SetEnv HALFMOON_ENV production

			# enable mod_rewrite
			RewriteEngine on

			# handle requests for static assets (stylesheets,
			# javascript, images, cached pages, etc.) directly
			RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f

			# route all other requests to halfmoon
			RewriteRule ^(.*)$ /index.php/%{REQUEST_URI} [QSA,L]
		</VirtualHost>

5.	create the database, grant permissions, and put those settings in
	the config/db.ini file under the development section.

	by default, halfmoon runs in development mode unless the
	HALFMOON_ENV environment variable is set to something else (such as
	via the commented out example above, using apache's SetEnv function).


## development overview ##

1.	create models in the models/ directory according to your database
	tables.

	example models/Post.php:

		<?php

		class Post extends ActiveRecord\Model {
			static $belongs_to = array(
				array("user"),
			);
		}

		?>

2.	create controllers in the controllers/ directory to map urls to
	actions.

		controllers/posts_controller.rb:

			<?php

			class PostsController extends ApplicationController {
				public function index() {
					$this->posts = Post::find("all");
				}
			}

			?>

	to set variables in the namespace of the view, use "$this->varname".
	in the above example, $posts is an array of all posts and is visible
	to the view template php file.

	the index action will be called by default when a route does
    not specify an action.

3.	create views in the views/ directory.  by default, controller
	actions will try to render views/(controller)/(action).phtml.
    for example, these urls:

		/posts
		/posts/index

	will both call the index action in the posts controller, which
	will render views/posts/index.phtml.

	a url of:

		/posts/view/1

	would map (using the default catch-all route) to the posts
	controller, calling the view action with $id set to 1, and then
	render views/posts/view.phtml.

	partial views are snippets of html that are shared among views and
	can be included in a view template with render function.  their
	filenames must start with underscores.

	for example, if views/posts/index.phtml contained:

		<?php

		render(array("partial" => "header_image"));
		...

		?>

	then views/posts/_header_image.phtml would be brought in.

	after a controller renders its view file, it is stored in the
	$content_for_layout variable and the views/layouts/application.phtml
	file is rendered.  be sure to print $content_for_layout somewhere in
	that file.

4.	(optional) configure a root route to specify which controller/action
	should be used for viewing the root ("/") url via config/routes.php:

		HalfMoon\Router::instance()->rootRoute = array(
			"controller" => "posts",
			"action" => "homepage"
		);

	this uses the same rules as other routes, calling the index action
	if it is not specified.

	if your site should always present a static page (like a
	login/splash page) at the root url, then simply make a
	public/index.html file to avoid processing through halfmoon.

5.	change or create site-specific and environment-specific settings in
	the config/boot.php script.  this can be used to adjust logging,
	tweak php settings, or set global php variables that you need.


## moving to production ##

1.	copy the entire directory tree (/var/www/example in this example)
	somewhere, setup an apache virtual host like the example above, but
	use the SetEnv apache function to change the HALFMOON_ENV
	environment to "production".

		<VirtualHost ...>
			...
			SetEnv HALFMOON_ENV production
			...
		</VirtualHost>

	this will use the database configured in config/db.ini under the
	production group, and any settings you have changed in
	config/boot.php that are environment-specific (such as disabling
	logging).
