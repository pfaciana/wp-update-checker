<?php

if ( !defined( 'WP_THEME_DIR' ) ) {
	define( 'WP_THEME_DIR', WP_CONTENT_DIR . '/themes' ); // Full path, no trailing slash.
}

add_action( 'init', function () {
	// Do not run the upgrader on this package if it's installed via composer
	if ( basename( $dir = dirname( __DIR__ ) ) !== 'pfaciana' && basename( dirname( $dir ) ) !== 'vendor' ) {
		new PackageUpgrader\V1\Plugin;
	}
	if ( !did_action( 'rpuc/init' ) ) {
		do_action( 'rpuc/init' );
	}
}, -99 );
