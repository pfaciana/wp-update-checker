<?php

namespace PackageUpgrader\V1;

use PackageUpgrader\V1\Context\Types\Kind;
use PackageUpgrader\V1\Package\AbstractPackage;

/**
 * Class Plugin
 *
 * Handles plugin update checks and related functionality.
 *
 * @package PackageUpgrader\V1
 */
class Plugin extends AbstractPackage
{
	/** @var Kind The type of package */
	public Kind $type = Kind::PLUGIN;

	/** @var string The plugin basename */
	public string $plugin;

	/**
	 * Plugin constructor.
	 *
	 * @param string|null $filename The plugin file path.
	 * @param array{
	 *      remote_class?: string
	 *  }                 $options  Optional. Additional options for bootstrapping. Default is an empty array.
	 *                              'remote_class': FQCN of the remote class. Default is to the "Repo Type" in the header comments.
	 */
	public function __construct ( ?string $filename = NULL, array $options = [] )
	{
		if ( empty( $filename ) ) {
			$backtrace = debug_backtrace();
			$filename  = $backtrace[0]['file'];

			if ( !empty( $active_plugins = get_option( 'active_plugins' ) ) ) {
				$directory = explode( '/', plugin_basename( $filename ) )[0];
				foreach ( $active_plugins as $plugin ) {
					if ( str_starts_with( $plugin, $directory ) ) {
						$filename = WP_PLUGIN_DIR . '/' . $plugin;
						break;
					}
				}
			}
		}

		$this->bootstrap( $filename, $options );

		$repo_type = strtolower( $this->local->repo_type );
		$repo_id   = strtolower( $this->local->repo_id );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update_plugin' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 4 );
		add_filter( 'plugins_api', [ $this, 'check_info' ], 9, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
		add_action( 'admin_print_footer_scripts-plugin-install.php', [ $this, 'set_api_token_section' ] );
		add_filter( "rpuc/{$repo_type}/{$repo_id}/package_information", [ $this, 'plugin_information' ], 99, 3 );
	}

	/**
	 * Checks for plugin updates.
	 *
	 * @param object $transient The current transient value.
	 * @return object The modified transient value.
	 */
	public function check_update_plugin ( object $transient ): object
	{
		global $wpdb;

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->local->set_props( FALSE, wp_doing_cron() || in_array( $_REQUEST['action'] ?? '', [ 'update-plugin', 'update-selected' ] ) );
		$this->remote->set_props();

		if ( version_compare( $this->local->version, $this->remote?->new_version ?? NULL, '<' ) ) {
			$remote = json_decode( json_encode( $this->remote ) );
			foreach ( $remote as &$value ) {
				if ( is_string( $value ) ) {
					$value = $wpdb->strip_invalid_text_for_column( $wpdb->options, 'option_value', $value );
				}
			}
			$transient->response[$this->local->id] = $remote;
		}
		else {
			unset( $transient->response[$this->local->id] );
		}

		return $transient;
	}

	/**
	 * Filters the array of row meta for each plugin in the Plugins list table.
	 *
	 * @param string[] $plugin_meta  An array of the plugin's metadata, including
	 *                               the version, author, author URI, and plugin URI.
	 * @param string   $plugin_file  Path to the plugin file relative to the plugins directory.
	 * @param array    $plugin_data  An array of plugin data.
	 * @param string   $status       Status filter currently applied to the plugin list. Possible
	 *                               values are: 'all', 'active', 'inactive', 'recently_activated',
	 *                               'upgrade', 'mustuse', 'dropins', 'search', 'paused',
	 *                               'auto-update-enabled', 'auto-update-disabled'.
	 * @return string[] An array of the plugin's metadata.
	 */
	public function plugin_row_meta ( array $plugin_meta, string $plugin_file, array $plugin_data, string $status ): array
	{
		if ( $plugin_file !== $this->local->id ) {
			return $plugin_meta;
		}

		$this->remote->set_props( TRUE );

		if ( !empty( $sections = ( $this->remote?->sections ?? NULL ) ) ) {
			$links = [
				'current_release_notes' => 'Current Release Notes',
				'api_token'             => 'Enter API Token',
			];

			foreach ( $links as $key => $label ) {
				if ( array_key_exists( $key, $sections ) ) {
					$plugin_meta[] = $this->get_thickbox_metadata_link( $key, $label );
				}
			}
		}

		return $plugin_meta;
	}

	/**
	 * Generates a Thickbox link for displaying metadata sections in the plugin details modal.
	 *
	 * @param string      $section_key  The key identifying the specific metadata section to display.
	 * @param string|null $section_text Optional. The display text for the link. Defaults to the section key if not provided.
	 *
	 * @return string The HTML string for the Thickbox metadata link.
	 */
	public function get_thickbox_metadata_link ( string $section_key, string $section_text = NULL ): string
	{
		return sprintf( //
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>', //
			esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->local->folder . '&section=' . $section_key . '&TB_iframe=true&width=600&height=550' ) ), //
			esc_attr( sprintf( __( 'More information about %s' ), $this->local->name ) ), //
			esc_attr( $this->local->name ), //
			__( $section_text ?? $section_key ) //
		);
	}

	/**
	 * Filters the plugin API response.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array The plugin info or false.
	 */
	public function check_info ( $result, string $action, object $args )
	{
		if ( ( $args?->slug ?? FALSE ) !== $this->local->folder ) {
			return $result;
		}

		$this->remote->set_props( TRUE );

		return $this->remote;
	}

	/**
	 * Fires after a plugin has been successfully updated.
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra argument s passed to hooked filters.
	 * @param array $result     Installation result data.
	 * @return array Updated installation result data.
	 */
	public function post_install ( bool $response, array $hook_extra, array $result ): bool
	{
		if ( ( $hook_extra["plugin"] ?? FALSE ) !== $this->local->id ) {
			return $response;
		}

		global $wp_filesystem;

		$plugin_folder = rtrim( wp_normalize_path( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->local->folder ), '/' );
		$dest_folder   = rtrim( wp_normalize_path( $result['destination'] ), '/' );

		if ( $dest_folder !== $plugin_folder ) {
			if ( $wp_filesystem->exists( $plugin_folder ) ) {
				$wp_filesystem->delete( $plugin_folder, TRUE, 'd' );
			}
			$wp_filesystem->move( $dest_folder, $plugin_folder );
		}

		if ( is_plugin_active( $this->local->id ) ) {
			activate_plugin( $this->local->id );
		}

		return $response;
	}

	/**
	 * Generates the HTML for the API Token section with an AJAX-enabled form.
	 *
	 * @return string The HTML string for the API Token form.
	 */
	public function set_api_token_section (): void
	{
		if ( ( $_GET['plugin'] ?? FALSE ) !== $this->local->folder || ( $_GET['tab'] ?? FALSE ) !== 'plugin-information' ) {
			return;
		}

		echo parent::get_api_token_section();
	}

	/**
	 * Modifies plugin information by adding additional sections and actions as needed.
	 *
	 * @param bool   $valid         Flag indicating whether setting plugin props was successful.
	 * @param object $remote        The remote plugin information object.
	 * @param bool   $with_sections Flag indicating whether to include additional sections.
	 * @return bool
	 */
	public function plugin_information ( bool $valid, object $remote, bool $with_sections )
	{
		$with_sections && add_action( 'admin_print_footer_scripts-plugin-install.php', [ $this, 'install_footer' ] );

		return $valid;
	}

	/**
	 * Add some opinionated styling to the plugin details modal.
	 *
	 * @return void
	 */
	public function install_footer ()
	{
		?>
		<style>
			#section-holder h2, #section-holder h3 {
				clear:  none;
				margin: 1em 0;
			}

			.changelog-release {
				margin-bottom:  3em;
				padding-bottom: 3em;
				border-bottom:  1px solid #dcdcde;
			}
		</style>
		<?php
	}
}
