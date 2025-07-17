<?php

if ( !defined( 'WP_THEME_DIR' ) && defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_THEME_DIR', WP_CONTENT_DIR . '/themes' ); // Full path, no trailing slash.
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', function () {
		\Render\Autoload\ClassLoader::getInstance();
	}, PHP_INT_MIN );

	add_action( 'init', function () {
		if ( !did_action( 'rpuc/init' ) ) {
			do_action( 'rpuc/init' );
		}
	}, -99 );
}