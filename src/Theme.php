<?php

namespace PackageUpgrader\V1;

use PackageUpgrader\V1\Context\Types\Kind;
use PackageUpgrader\V1\Package\AbstractPackage;

/**
 * Class Theme
 *
 * Handles theme update checks and related functionality.
 *
 * @package PackageUpgrader\V1
 */
class Theme extends AbstractPackage
{
	/** @var Kind The type of package */
	public Kind $type = Kind::THEME;

	/** @var string The theme slug */
	public string $theme_slug;

	/**
	 * Theme constructor.
	 *
	 * @param string|null $filename The theme file path.
	 */
	public function __construct ( ?string $filename = NULL )
	{
		if ( empty( $filename ) ) {
			$backtrace = debug_backtrace();
			$filename  = wp_normalize_path( $backtrace[0]['file'] );
			$filename  = str_replace( $wp_themes_root = rtrim( wp_normalize_path( get_theme_root() ), '/' ) . '/', '', $filename );
			$filename  = $wp_themes_root . ( explode( '/', $filename )[0] ) . '/style.css';
		}

		$this->bootstrap( $filename );

		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'check_update_theme' ] );
		add_filter( 'themes_api', [ $this, 'check_info' ], 9, 3 );
		add_filter( 'wp_prepare_themes_for_js', [ $this, 'prepare_themes_for_js' ] );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
	}

	/**
	 * Checks for theme updates.
	 *
	 * @param object $transient The current transient value.
	 * @return object The modified transient value.
	 */
	public function check_update_theme ( object $transient ): object
	{
		global $wpdb;

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->local->set_props( FALSE, wp_doing_cron() || in_array( $_REQUEST['action'] ?? '', [ 'update-theme', 'update-selected-themes' ] ) );
		$this->remote->set_props();

		if ( version_compare( $this->local->version, $this->remote?->new_version ?? NULL, '<' ) ) {
			$remote = json_decode( json_encode( $this->remote ) );
			foreach ( $remote as &$value ) {
				if ( is_string( $value ) ) {
					$value = $wpdb->strip_invalid_text_for_column( $wpdb->options, 'option_value', $value );
				}
			}
			$transient->response[$this->local->folder] = (array) $remote;
		}
		else {
			unset( $transient->response[$this->local->folder] );
		}

		return $transient;
	}

	/**
	 * Filters the theme API response.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Theme Installation API.
	 * @param object             $args   Theme API arguments.
	 * @return false|object|array The theme info or false.
	 */
	public function check_info ( $result, string $action, object $args )
	{
		if ( ( $args?->slug ?? FALSE ) !== $this->local->id ) {
			return $result;
		}

		$this->remote->set_props();

		return $this->remote;
	}

	/**
	 * Filters the themes prepared for JavaScript, for themes.php.
	 *
	 * Could be useful for changing the order, which is by name by default.
	 *
	 * @param array $prepared_themes Array of theme data.
	 */
	public function prepare_themes_for_js ( $prepared_themes )
	{
		if ( !array_key_exists( $this->local->folder, $prepared_themes ) ) {
			return $prepared_themes;
		}

		$this->remote->set_props();

		$desc = &$prepared_themes[$this->local->folder]["description"];

		if ( in_array( 'private', [ $this->local->remote_visibility, ( $this->remote?->remote_visibility ?? 'public' ) ] ) ) {
			ob_start();
			// The closing, the starting paragraphs tags are need for valid html
			// because the description is wrapped in a paragraph tag in the WordPress theme.php template
			?>
			</p>
			<hr style="margin:25px 0 0 0">
			<div class="package-upgrader-api-token" style="padding:10px 25px 30px 25px; background-color: #f6f7f7"></div>
			<?= $this->get_api_token_section() ?>
			<hr style="margin:0"><p style="display: none;">
			<?php
			$desc .= ob_get_clean();
		}

		return $prepared_themes;
	}

	/**
	 * Fires after a theme has been successfully updated.
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 * @return array Updated installation result data.
	 */
	public function post_install ( bool $response, array $hook_extra, array $result ): bool
	{
		if ( ( $hook_extra['theme'] ?? FALSE ) !== $this->local->folder ) {
			return $response;
		}

		global $wp_filesystem;

		$theme_folder = rtrim( wp_normalize_path( WP_THEME_DIR . DIRECTORY_SEPARATOR . $this->local->folder ), '/' );
		$dest_folder  = rtrim( wp_normalize_path( $result['destination'] ), '/' );

		if ( $dest_folder !== $theme_folder ) {
			$wp_filesystem->move( $dest_folder, $theme_folder );
		}

		if ( wp_get_theme()->get_stylesheet() !== $this->local->folder ) {
			switch_theme( $this->local->folder );
		}

		return $response;
	}
}