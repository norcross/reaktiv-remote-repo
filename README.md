Reaktiv Remote Repo
===================

A plugin to provide a self-hosted WP plugin repository


## General Setup ##

To properly set this up, you must add some code to your plugin, with data from the site you have set up Reaktiv Remote Repo on.
All of this should reside in your root file.

### Your Plugin ###

You first add your repository update URL as a constant. It should be the domain of your site, with `update` on the end.

```php
if ( ! defined( 'YOUR_REPO_URL' ) ) {
	define( 'YOUR_REPO_URL', 'http://yourdomain.com/update/' );
}
```

You then need to add the unique key as a constant. This key is generated in your individual item in the repo.

```php
if ( ! defined( 'YOUR_PLUGIN_UNIQUE' ) ) {
	define( 'YOUR_PLUGIN_UNIQUE', 'XXXXXXXXXX' );
}
```

Include a version number, which is what the update will check against.

```php
if ( ! defined( 'YOUR_PLUGIN_VER' ) ) {
	define( 'YOUR_PLUGIN_VER', '0.0.1' );
}
```

Include the updater class file in your plugin, and load it.

```php
add_action( 'plugins_loaded', 'rkv_load_updater' );

function rkv_load_updater() {

	if ( ! class_exists( 'RKV_Remote_Updater' ) ) {
		include( 'lib/RKV_Remote_Updater.php' );
	}
}
```

And then add the update function in your plugin

```php
add_action( 'admin_init', 'rkv_remote_update' );

function rkv_remote_update() {

	// ensure the class exists before running
	if ( ! class_exists( 'RKV_Remote_Updater' ) ) {
		return;
	}

	$updater = new RKV_Remote_Updater( YOUR_REPO_URL, __FILE__, array(
		'unique'    => YOUR_PLUGIN_UNIQUE,
		'version'   => YOUR_PLUGIN_VER,
		)
	);
}
```
