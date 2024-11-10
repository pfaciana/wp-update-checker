<?php

namespace PackageUpgrader\V1\Context;

use PackageUpgrader\V1\Context\Types\Kind;

abstract class AbstractRemote extends AbstractContext
{
	/** @var string The base URL for a repo for API requests. */
	protected string $base_url;

	/** @var Local The local configuration object. */
	protected Local $local;

	/** @var string|false The readme content as a string or false if not available. */
	protected string|false $readme;

	/**
	 * Constructor for the AbstractRemote class.
	 *
	 * @param Kind|string  $type   The type of package.
	 * @param string|Local $config The configuration, either as a string or Local object.
	 */
	public function __construct ( Kind|string $type, Local|string $config )
	{
		$this->type  = is_string( $type ) ? Kind::from( $type ) : $type;
		$this->local = is_string( $config ) ? new Local( $this->type, $config ) : $config;

		add_filter( 'upgrader_source_selection', [ $this, 'set_source_selection' ], 10, 4 );
		add_filter( 'upgrader_install_package_result', [ $this, 'set_install_package_result' ], 10, 2 );
	}

	/**
	 * Get cached request data.
	 *
	 * @param string $transient The transient key.
	 * @return mixed|false The cached data or false if not found.
	 */
	public function get_cached_request ( string $transient ): mixed
	{
		$cached_response = get_transient( $transient );

		return !empty( $cached_response ) ? $cached_response : FALSE;
	}

	/**
	 * Perform a GET request and cache the response.
	 *
	 * @param string $url   The URL to request.
	 * @param string $type  The expected response type (default: 'json').
	 * @param bool   $force Whether to force a new request.
	 * @return mixed|false The response body or false on failure.
	 */
	public function get_request ( string $url, string $type = 'json', bool $force = FALSE ): mixed
	{
		$transient = sanitize_title( $url );

		if ( !$force && !filter_input( INPUT_GET, 'force-check', FILTER_VALIDATE_BOOLEAN ) && !empty( $body = $this->get_cached_request( $transient ) ) ) {
			return $body;
		}

		$response = wp_remote_get( $url );

		if ( empty( $body = $this->get_body( $response, $type ) ) ) {
			return FALSE;
		}

		set_transient( $transient, $body, HOUR_IN_SECONDS * 12 );

		return $body;
	}

	/**
	 * Extract and process the body from a WordPress HTTP response.
	 *
	 * @param array|\WP_Error $response The WordPress HTTP response.
	 * @param string          $type     The expected response type (default: 'json').
	 * @return mixed|false The processed body or false on failure.
	 */
	public function get_body ( $response, string $type = 'json' ): mixed
	{
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return trigger_error( 'There was error getting the repo request.', E_USER_WARNING ) && FALSE;
		}

		if ( empty( $body = wp_remote_retrieve_body( $response ) ) ) {
			return '';
		}

		if ( $type === 'json' && empty( $body = json_decode( $body ) ) ) {
			return FALSE;
		}

		return $body;
	}

	/**
	 * Get the full endpoint URL.
	 *
	 * @param string $uri The URI to append to the base URL.
	 * @return string The full endpoint URL.
	 */
	public function get_endpoint ( string $uri ): string
	{
		return $this->base_url . $uri;
	}

	/**
	 * Validate the API token.
	 *
	 * @return bool
	 */
	public function validate_api_token (): bool
	{
		return TRUE;
	}

	/**
	 * Filters the source file location for the upgrade package.
	 *
	 * @param string       $source        File source location.
	 * @param string       $remote_source Remote file source location.
	 * @param \WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array        $hook_extra    Extra arguments passed to hooked filters.
	 * @return string The source file location.
	 */
	public function set_source_selection ( string $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra ): string
	{
		if ( ( $hook_extra[strtolower( $this->type->value )] ?? FALSE ) !== $this->local->{strtolower( $this->type->value )} ) {
			return $source;
		}

		global $wp_filesystem;

		$orig_source = wp_normalize_path( trailingslashit( $source ) );
		$source      = wp_normalize_path( trailingslashit( dirname( $orig_source ) . DIRECTORY_SEPARATOR . $this->local->folder ) );

		if ( $source !== $orig_source ) {
			$wp_filesystem->move( $orig_source, $source );
		}

		return $source;
	}

	/**
	 * Filters the result of WP_Upgrader::install_package().
	 *
	 * @param array|\WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array           $hook_extra Extra arguments passed to hooked filters.
	 * @return array|\WP_Error
	 */
	public function set_install_package_result ( array|\WP_Error $result, array $hook_extra )
	{
		if ( is_wp_error( $result ) || ( $hook_extra[strtolower( $this->type->value )] ?? FALSE ) !== $this->local->{strtolower( $this->type->value )} ) {
			return $result;
		}

		$result['destination_name']   = $this->local->folder;
		$result['destination']        = ( $this->type === Kind::THEME ? WP_THEME_DIR : WP_PLUGIN_DIR ) . '/' . $result['destination_name'] . '/';
		$result['remote_destination'] = wp_normalize_path( $result['destination'] );

		return $result;
	}

	/**
	 * Set properties for the remote context based on the header comments.
	 *
	 * @param bool $with_sections Whether to include additional sections like readme and changelog.
	 * @param bool $force         Whether to force a refresh of the properties.
	 * @return bool True if properties were set successfully, false otherwise.
	 */
	public function set_props ( bool $with_sections = FALSE, bool $force = FALSE ): bool
	{
		$this->file = $this->local->remote_file ?? $this->local->file ?? NULL;

		if ( empty( $valid = parent::set_props( $force ) ) ) {
			return FALSE;
		}

		$this->base_url = $this->api_url ?? $this->base_url;

		$this->id = $this->basename = $this->local->id;
		[ $this->folder, $this->file ] = explode( '/', $this->id );
		$this->slug = $this->folder;
		if ( $this->type === Kind::THEME ) {
			$this->theme = $this->local->theme;
		}
		else {
			$this->plugin = $this->local->plugin;
		}

		return $valid;
	}

	/**
	 * Determines if the current package (theme or plugin) is being updated.
	 *
	 * @return bool True if the package is being updated, false otherwise.
	 */
	protected function is_updating ()
	{
		if ( $this->type === Kind::THEME ) {
			if ( ( $_POST['action'] ?? FALSE ) === 'update-theme' && ( $_POST["slug"] ?? FALSE ) === $this->local->folder ) {
				return TRUE;
			}

			if ( ( $_GET['action'] ?? FALSE ) === 'update-selected-themes' && in_array( $this->local->folder, explode( ',', urldecode( $_GET["themes"] ?? '' ) ) ) ) {
				return TRUE;
			}

			return FALSE;
		}

		if ( ( $_POST[strtolower( $this->type->value )] ?? FALSE ) === $this->local->id || ( $_POST["slug"] ?? FALSE ) === $this->local->id ) {
			return TRUE;
		}

		if ( ( $_GET['action'] ?? FALSE ) === 'update-selected' && in_array( $this->local->id, explode( ',', urldecode( $_GET['plugins'] ?? '' ) ) ) ) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Applies a series of filters to a value based on the repository type and ID.
	 *
	 * This method applies multiple WordPress filters in a specific order, allowing
	 * for granular control over the filtering process. It checks for the presence
	 * of repository type and ID before applying the filters.
	 *
	 * @param string $hook_key The base hook key for the filters.
	 * @param mixed  $value    The value to be filtered.
	 * @param mixed  ...$args  Additional arguments to be passed to the filter functions.
	 * @return mixed|bool The filtered value, or FALSE if repository type or ID is not set.
	 */
	public function apply_filters ( string $hook_key, $value, ...$args )
	{
		if ( empty( $this->local->repo_type ) ) {
			return trigger_error( 'Repository type has not been initialized.', E_USER_WARNING ) && FALSE;
		}

		if ( empty( $this->local->repo_id ) ) {
			return trigger_error( 'Repository ID has not been initialized.', E_USER_WARNING ) && FALSE;
		}

		$repo_type = strtolower( $this->local->repo_type );
		$repo_id   = strtolower( $this->local->repo_id );

		$value = apply_filters( "rpuc/{$hook_key}", $value, ...[ $repo_type, $repo_id, ...$args, $hook_key ] );
		$value = apply_filters( "rpuc/{$repo_type}", $value, ...[ $hook_key, $repo_id, ...$args, $repo_type ] );
		$value = apply_filters( "rpuc/{$repo_type}/{$hook_key}", $value, ...[ $repo_id, ...$args, $hook_key, $repo_type ] );
		$value = apply_filters( "rpuc/{$repo_type}/{$repo_id}", $value, ...[ $hook_key, ...$args, $repo_type, $repo_id ] );
		$value = apply_filters( "rpuc/{$repo_type}/{$repo_id}/{$hook_key}", $value, ...[ ...$args, $hook_key, $repo_type, $repo_id ] );

		return $value;
	}
}
