<?php

if ( !defined( 'WP_THEME_DIR' ) ) {
	define( 'WP_THEME_DIR', WP_CONTENT_DIR . '/themes' ); // Full path, no trailing slash.
}

add_action( 'init', function () {
	if ( !did_action( 'rpuc/init' ) ) {
		do_action( 'rpuc/init' );
	}
}, -99 );
