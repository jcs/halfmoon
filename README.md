	 .-.
	( (  halfmoon
	 `-`

## Overview ##

halfmoon, combined with [php-activerecord](http://github.com/kla/php-activerecord),
is a tiny MVC framework for PHP 5.3 that tries to use the conventions of
Ruby on Rails wherever possible and reasonable.

It has a similar directory structure to a Rails project, with the root
level containing models, views, controllers, and helpers directories.
It supports a concept of environments like Rails, defaulting to a
development environment which logs things to Apache's error log and
displays errors in the browser.

Its URL routing works similarly as well, supporting a catch-all default
route of `:controller/:action/:id` and a root URL (`/`) route.

Form helpers work similar to Rails.  For example, doing this in Rails:

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

with `$form` being an alias to a FormHelper object automatically setup
by the controller.  There are other helpers available like `$time`,
`$html`, etc.

## Requirements ##

-	PHP 5.3 or higher with the PDO database extensions you wish to use
	with php-activerecord (pdo-mysql, pdo-pgsql, etc.).

	The `mcrypt` extension is required for using the encrypted cookie
	session store (see [this page](http://michaelgracie.com/2009/09/23/plugging-mcrypt-into-php-on-mac-os-x-snow-leopard-10.6.1/) for Mac OS X instructions).

	The `pcntl` extension is required to use `script/dbconsole`.  The
	readline extension is optional, but will improve the use of
	`script/console`.  Both extensions can be installed on Mac OS X with
	the same instructions for mcrypt but no extra dependencies (download
	the PHP tarball for the version that `php -v` reports, untar,
	`cd ext/{pcntl,readline}; phpize; ./configure; make; make install`,
	enable in php.ini.

-	Apache 1 or 2, with mod_rewrite enabled.  Development of halfmoon is
	done on OpenBSD in a chroot()'d Apache 1 server, so any other
	environment should work fine.


## Installation ##

1.	(Optional) Create the root directory where you will be storing
	everything.  halfmoon will do this for you but if you are creating
	it somewhere where you need sudo permissions, do it manually:

		$ sudo mkdir /var/www/example/
		$ sudo chown `whoami` /var/www/example

2.	Fetch the halfmoon source code into your home directory or somewhere
	convenient (not in the directory you are setting up halfmoon in):

		$ git clone git://github.com/jcs/halfmoon.git

3.	Run the halfmoon script to create your skeleton directory at your
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

	At a later point, halfmoon will be installed system-wide, so that
	running "`halfmoon create ...`" will work from anywhere.

4.	Setup an Apache Virtual Host with a DocumentRoot pointing to the
    public/ directory:

		<VirtualHost 127.0.0.1>
			ServerName www.example.com

			CustomLog logs/example_access combined

			# halfmoon will log a few lines for each request (or one
			# line, or nothing - see config/boot.php) with some
			# useful information about routing, timing, etc.
			#
			# by default these will use php's error_log(), which will
			# log to the file specified below, but prefixed with
			# '[error]' and other junk.  to log information to a
			# separate file, create a class that extends and overrides
			# error(), info(), and warn() methods of \HalfMoon\Log and
			# use HalfMoon\Config::set_log_handler("YourClass") in your
			# boot.php file.
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

5.	(Optional) Create the database and its tables and grant permissions.
	Put those settings in the `config/db.ini` file under the development
	section.

	If you are not using a database, or just don't want to use php-
	activerecord, remove config/db.ini and php-ar will not be initialized,
	saving you some minor processing time on each request.

	By default, halfmoon runs in development mode unless the
	HALFMOON_ENV environment variable is set to something else (such as
	via the commented out example above, using apache's SetEnv function).


## Usage Overview ##

1.	Create models in the `models/` directory according to your database
	tables.

	Example `models/Post.php`:

		<?php

		class Post extends ActiveRecord\Model {
			static $belongs_to = array(
				array("user"),
			);
		}

		?>

2.	Create controllers in the `controllers/` directory to map urls to
	actions.

	Example `controllers/posts_controller.rb`:

		<?php

		class PostsController extends ApplicationController {
			static $before_filter = array("authenticate_user");

			public function index() {
				$this->posts = Post::find("all");
			}
		}

		?>

	To set variables in the namespace of the view, use `$this->varname`.
	In the above example, `$posts` is an array of all posts and is
	visible to the view template php file.

	The index action will be called by default when a route does
	not specify an action.

	Defining a `$before_filter` array of functions will call them before
	processing the action.  If any of them return false (such as one
	failing to authenticate the user and wanting to redirect to a login
	page), processing will stop, no other before_filters will be run,
	and the controller action will not be run.

3.	Create views in the `views/` directory.  By default, controller
	actions will try to render `views/*controller*/*action*.phtml`.
	For example, these URLs:

		/posts
		/posts/index

	will both call the `index` action in `PostsController`, which will
	render `views/posts/index.phtml`.

	A URL of:

		/posts/show/1

	would map (using the default catch-all route) to the posts
	controller, calling the `show` action with `$id` set to 1, and then
	render `views/posts/show.phtml`.

	Partial views are snippets of HTML that are shared among views and
	can be included in a view template with render function.  Their
	filenames must start with underscores.

	For example, if `views/posts/index.phtml` contained:

		<?php

		$controller->render(array("partial" => "header_image"));
		...

		?>

	then `views/posts/_header_image.phtml` would be brought in.

	After a controller renders its view file, it is stored in the
	`$content_for_layout` variable and the `views/layouts/application.phtml`
	file is rendered.  Be sure to print `$content_for_layout` somewhere in
	that file.

4.	(Optional) Configure a root route to specify which controller/action
	should be used for viewing the root (`/`) URL via `config/routes.php`:

		HalfMoon\Router::addRootRoute(array(
			"controller" => "posts",
			"action" => "homepage"
		));

	this uses the same rules as other routes, calling the `index` action
	if it is not specified.

	If your site should always present a static page (like a
	login/splash page) at the root URL, then simply make a
	public/index.html file to avoid processing through halfmoon.  This
	is handled entirely outside of halfmoon by apache, because of the
	`mod_rewrite` rule.

5.	Change or create site-specific and environment-specific settings in
	the `config/boot.php` script.  This can be used to adjust logging,
	tweak PHP settings, or set global PHP variables that you need.


## Moving to Production ##

1.	Copy the entire directory tree (/var/www/example in this example)
	somewhere, setup an Apache Virtual Host like the example above, but
	use the `SetEnv` apache function to change the `HALFMOON_ENV`
	environment to "production".

		<VirtualHost ...>
			...
			SetEnv HALFMOON_ENV production
			...
		</VirtualHost>

	This will use the database configured in `config/db.ini` under the
	production group, and any settings you have changed in
	`config/boot.php` that are environment-specific (such as disabling
	logging).

2.	Verify that your static 404 and 500 pages (in `public/`) have useful
	content.

	You may wish to turn HalfMoon's logging off completely, instead of
	the "short" style used by default in production which will only log
	one line logging the processing time for each request.  This can be
	adjusted in `config/boot.php`:

		HalfMoon\Config::set_activerecord_log_level("none");

	It is also recommended that you enable exception notification
	e-mails, which will e-mail you a backtrace and some helpful
	debugging information any time an error happens in your application:

		HalfMoon\Config::set_exception_notification_recipient("you@example.com");
		HalfMoon\Config::set_exception_notification_subject("[your app]");

## Caveats ##

There are some differences to be aware of between Rails and halfmoon.
Some are due to differences between Ruby and PHP and some are just
design changes.

1.	The body of the `form_for()` will be executed in a different context,
	so `$this` will not point to the controller as it does elsewhere in
	the view.  To get around this, `$controller` is defined (as well as
	a `$C` shortcut alias) and (along with any other local variables
	needed) can be passed into the `form_for()` body like so:

		<h1><?= $this->title() ?></h1>

		<? $form->form_for($post, "/posts/update", array(), function($f) use ($controller) { ?>
			<h2><?= $controller->sub_title(); ?></h2>
			...
		<? }); ?>

	This is due to the [design of closures in php](http://wiki.php.net/rfc/closures/removal-of-this).

	It is recommended to just always use `$C` (or the more verbose
	`$controller`) throughout views and closures.

2.	`list` and `new` are reserved keywords in PHP, so these cannot be
	used as the controller actions like Rails sets up by default.

	It is suggested to use `build` instead of `new`, and `index` instead
	of `list`.  Of course, `list` and `new` can still be used in URLs by
	adding a specific route to map them to different controller actions:

		HalfMoon\Router::addRoute(array(
			"url" => ":controller/list",
			"action" => "index",
		));

3.	Sessions are disabled by default, but can be enabled per-controller
	or per-action.  In a controller, define a static `$session` variable
	and either turn it on for the entire controller:

		static $session = "on";

	or just on for specific actions with `except` or `only` arrays:

		static $session = array(
			"on" => array(
				"only" => array("login", "index")
			)
		);

	To reverse the settings (enable it for the entire application but
	disable it for specific actions), define it to "`on`" in your
	`ApplicationController` and then just turn it off per-controller.

	Note: when using the built-in form helper (form_for) with a `POST`
	form and XSRF protection is enabled, sessions will be explicitly
	enabled to allow storing the token in the session pre-`POST` and then
	retrieving it on the `POST`.

### vim:ts=4:tw=72:ft=markdown
