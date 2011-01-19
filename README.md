
	 ,-,
	( (  halfmoon tiny mvc framework for php
	 `-`

## overview ##

halfmoon, combined with [php-activerecord](http://github.com/kla/php-activerecord),
is a tiny mvc framework for php 5.3 that tries to use the conventions of
ruby on rails wherever possible and reasonable.

it has a similar directory structure to a rails project, with the root
level containing models, views, controllers, and helpers directories.
it supports a concept of environments like rails, defaulting to a
development environment which logs things to apache's error log and
displays errors in the browser.

its url routing works similarly as well, supporting a catch-all default
route of :controller/:action/:id and a root url ("/") route.

form helpers work similar to rails.  for example, doing this in rails:

	<% form_for :post, @post, :url => "/posts/update" do |f| %>
		<%= f.label :title, "Post Title" %>
		<%= f.text_field :title, :size => 20 %>

		<%= submit_tag "Submit" %>
	<% end %>

is similar to this in halfmoon:

	<? $form->form_for($post, "/posts/update", array(), function($f) { ?>
		<?= $f->label("title", "Post Title"); ?>
		<?= $f->text_field("title", array("size" => 20)); ?>

		<?= $f->submit_button("Submit") ?>
	<? }); ?>

with $form being an alias to a FormHelper object automatically setup by
the controller.  there are other helpers available like $time, $html,
etc.

## requirements ##

-	php 5.3 or higher with the pdo database extensions you wish to use
	with activerecord (pdo-mysql, pdo-pgsql, etc.).

	the mcrypt extension is required for using the encrypted cookie
	session store (see [this page](http://michaelgracie.com/2009/09/23/plugging-mcrypt-into-php-on-mac-os-x-snow-leopard-10.6.1/) for mac os x instructions).

	the pcntl extension is required to use dbconsole.  the same
	instructions for mac os can be applied (download the php tarball for
	the version installed, untar, "cd ext/pcntl; phpize; ./configure;
	make; make install", enable in php.ini.

-	apache 1 or 2, with mod_rewrite enabled.  development of halfmoon is
	done on openbsd in a chroot()'d apache 1 server, so any other
	environment should work fine.


## installation ##

1.	(optional) create the root directory where you will be storing
	everything.  halfmoon will do this for you but if you are creating
	it somewhere where you need sudo permissions, do it manually:

		$ sudo mkdir /var/www/example/
		$ sudo chown `whoami` /var/www/example

2.	fetch the halfmoon source code:

		$ git clone git://github.com/jcs/halfmoon.git

3.	run the halfmoon script to create your skeleton directory at your
	root directory created in step 1:

		$ halfmoon/halfmoon create /var/www/example/
		copying halfmoon framework... done.
		creating skeleton directory structure... done.
		creating random encryption key for session storage... done.

		   /var/www/example/:
		   total 14
		   drwxr-xr-x  2 jcs  users  512 Feb 15 10:25 config/
		   drwxr-xr-x  2 jcs  users  512 Feb 15 10:20 controllers/
		   drwxr-xr-x  5 jcs  users  512 Mar 15 20:33 halfmoon/
		   drwxr-xr-x  2 jcs  users  512 Mar 15 20:33 helpers/
		   drwxr-xr-x  2 jcs  users  512 Mar 15 20:33 models/
		   drwxr-xr-x  4 jcs  users  512 Feb 13 19:58 public/
		   drwxr-xr-x  3 jcs  users  512 Feb 13 19:58 views/

		welcome to halfmoon!

	at a later point, halfmoon will be installed system-wide, so that
	running "halfmoon create ..." will work from anywhere.

4.	setup an apache virtual host with a DocumentRoot pointing to the
    public/ directory:

		<VirtualHost 127.0.0.1>
			ServerName www.example.com

			CustomLog logs/example_access combined

			# halfmoon will log a few lines for each request with some
			# useful information about routing, timing, etc., but
			# because of a php/apache bug that prevents stderr output
			# from going into the proper log file for virtual hosts,
			# halfmoon has to use error_log() to log these things so
			# messages are prefixed with '[error]' just like proper
			# errors.  http://bugs.php.net/bug.php?id=51304
			ErrorLog logs/example_info

			# this should point to your public directory where index.php
			# lives to interface with halfmoon
			DocumentRoot /var/www/example/public/

			# try static (cached) pages before dynamic ones
			DirectoryIndex index.html index.php

			# uncomment in a production environment, otherwise we are
			# assuming to be running in development
			#SetEnv HALFMOON_ENV production

			# if suhosin is installed, disable session encryption and
			# bump the maximum id length since we're handling sessions
			# on our own
			php_flag suhosin.session.encrypt off
			php_value suhosin.session.max_id_length 1024

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

	example controllers/posts_controller.rb:

		<?php

		class PostsController extends ApplicationController {
			static $before_filter = array("authenticate_user");

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

	defining a $before_filter array of functions will call them before
	processing the action.  if any of them return false (such as one
	failing to authenticate the user and wanting to redirect to a login
	page), processing will stop, no other before_filters will be run,
	and the controller action will not be run.

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

		$controller->render(array("partial" => "header_image"));
		...

		?>

	then views/posts/_header_image.phtml would be brought in.

	after a controller renders its view file, it is stored in the
	$content_for_layout variable and the views/layouts/application.phtml
	file is rendered.  be sure to print $content_for_layout somewhere in
	that file.

4.	(optional) configure a root route to specify which controller/action
	should be used for viewing the root ("/") url via config/routes.php:

		HalfMoon\Router::instance()->addRootRoute(array(
			"controller" => "posts",
			"action" => "homepage"
		));

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


## caveats ##

there are some differences to be aware of between rails and halfmoon.
some are due to differences between ruby and php and some are just
design changes.

1.	the body of the form_for() will be executed in a different context,
	so $this will not point to the controller as it does elsewhere in
	the view.  to get around this, $controller is defined and (along
	with any other local variables needed) can be passed into the
	form_for() body like so:

		<h1><?= $this->title() ?></h1>

		<? $form->form_for($post, "/posts/update", array(), function($f) use ($controller) { ?>
			<h2><?= $controller->sub_title(); ?></h2>
			...
		<? }); ?>

	this is due to the [design of closures in php](http://wiki.php.net/rfc/closures/removal-of-this).

2.	"list" and "new" are reserved keywords in php, so these cannot be
	used as the controller actions like rails sets up by default.

	it is suggested to use "build" instead of "new", and "index" instead
	of "list".  of course, "list" and "new" can still be used in the url
	by adding a specific route to map them to different controller
	actions.

3.	sessions are disabled by default, but can be enabled per-controller
	or per-action.  in a controller, define a static $session variable
	and either turn it on for the entire controller:

		static $session = "on";

	or just on for specific actions with "except" or "only" arrays:

		static $session = array("on", "only" => array("login"));

	to reverse the settings (enable it for the entire application but
	disable it for specific actions), define it to "on" in your
	ApplicationController and then just turn it off per-controller.

	note: when using the built-in form helper (form_for) with a POST
	form and XSRF protection is enabled, sessions will be explicitly
	enabled to allow storing the token in the session pre-POST and then
	retrieving it on the POST.


### vim:ts=4:tw=72:ft=markdown
