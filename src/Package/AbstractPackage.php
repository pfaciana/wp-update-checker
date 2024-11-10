<?php

namespace PackageUpgrader\V1\Package;

use PackageUpgrader\V1\Context\Types\Kind;
use PackageUpgrader\V1\Context\AbstractRemote;
use PackageUpgrader\V1\Context\Local;

abstract class AbstractPackage
{
	/** @var Kind The type of package */
	public Kind $type;

	/** @var Local The local context of the package. */
	public Local $local;

	/** @var AbstractRemote The remote context of the package. */
	public AbstractRemote $remote;

	/** @var string The API token key */
	protected string $api_token_key;

	/**
	 * Bootstrap the package by initializing local and remote contexts.
	 *
	 * This method sets up the local context and, if a valid repository type is found,
	 * initializes the corresponding remote context.
	 *
	 * @param string $filename The filename to use for initializing the local context.
	 * @param array{
	 *       remote_class?: string
	 *   }           $options  Optional. Additional options for bootstrapping. Default is an empty array.
	 *                         'remote_class': FQCN of the remote class. Default is to the "Repo Type" in the header comments.
	 * @return bool Returns TRUE if bootstrapping is successful, FALSE otherwise.
	 */
	protected function bootstrap ( string $filename, array $options = [] ): bool
	{
		$options = wp_parse_args( $options, [ 'remote_class' => NULL ] );

		$this->local = new Local( $this->type, $filename );
		if ( empty( $this->local->repo_type ) ) {
			return trigger_error( 'No repository found.', E_USER_WARNING ) && FALSE;
		}

		$remote_class = $options['remote_class'] ?: "\PackageUpgrader\\V1\\Context\\{$this->local->repo_type}";

		/** @var class-string<AbstractRemote> $remote_class */
		$this->remote = new $remote_class( $this->type, $this->local );

		if ( $this->is_updating() ) {
			$this->remote->set_props();
		}

		$this->api_token_key = "rpuc_plugin_{$this->local->folder}_api_token";

		$repo_type = strtolower( $this->local->repo_type );
		$repo_id   = strtolower( $this->local->repo_id );

		add_action( 'wp_ajax_save_api_token', [ $this, 'save_api_token' ] );
		add_filter( "rpuc/{$repo_type}/{$repo_id}/access_token", [ $this, 'api_token' ], -99 );
		add_filter( "rpuc/{$repo_type}/{$repo_id}/package_information", [ $this, 'package_information' ], 999, 3 );

		return TRUE;
	}

	/**
	 * Retrieves the API token for the package.
	 *
	 * This method first checks if there's a saved token in the options table.
	 * If found, it returns the saved token. Otherwise, it returns the provided token.
	 *
	 * @param string $api_token The default API token.
	 * @return string The API token to use for the package.
	 */
	public function api_token ( $api_token ): string
	{
		if ( !empty( $saved_token = get_option( $this->api_token_key, '' ) ) ) {
			return $saved_token;
		}

		return $api_token;
	}

	/**
	 * Modifies plugin information by adding additional sections and actions as needed.
	 *
	 * @param bool   $valid         Flag indicating whether setting plugin props was successful.
	 * @param object $remote        The remote plugin information object.
	 * @param bool   $with_sections Flag indicating whether to include additional sections.
	 * @return bool
	 */
	public function package_information ( bool $valid, object $remote, bool $with_sections )
	{
		if ( current_user_can( 'manage_options' ) && $with_sections && in_array( 'private', [ $this->local->remote_visibility, $this->remote->remote_visibility ] ) ) {
			$remote->sections['api_token'] = '<div class="package-upgrader-api-token"></div>';
		}

		return $valid;
	}

	/**
	 * Handles the AJAX request to save, update, or remove the API token.
	 *
	 * This method performs the following actions:
	 * 1. Verifies the plugin ID and user capabilities.
	 * 2. Validates the nonce for security.
	 * 3. Processes the request based on the action (save/update or remove).
	 * 4. Validates the new API token if saving/updating.
	 * 5. Sends a JSON response indicating success or failure.
	 *
	 * @return void Sends a JSON response and terminates execution.
	 */
	public function save_api_token ()
	{
		if ( ( $_POST['plugin_id'] ?? FALSE ) !== $this->local->folder ) {
			return;
		}

		if ( !current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Your user account does not have sufficient permissions.' ] );
		}

		if ( !isset( $_POST['save_api_token_nonce'] ) || !wp_verify_nonce( $_POST['save_api_token_nonce'], 'save_api_token_action' ) ) {
			wp_send_json_error( [ 'message' => 'Security verification failed.' ] );
		}

		$action = strtolower( $_POST["key_action"] ?? 'save' );

		if ( $action === 'remove' ) {
			$updated = delete_option( $this->api_token_key );
		}
		else {
			$api_token = !empty( $_POST['api_token'] ?? FALSE ) ? sanitize_text_field( $_POST['api_token'] ) : FALSE;
			if ( empty( $api_token ) ) {
				wp_send_json_error( [ 'message' => 'API Token is missing.' ] );
			}
			if ( $api_token === get_option( $this->api_token_key, '' ) ) {
				wp_send_json_success( [ 'message' => 'API Token is the same.' ] );
			}
			if ( !empty( $updated = update_option( $this->api_token_key, $api_token ) ) ) {
				if ( empty( $updated = $this->remote->validate_api_token() ) ) {
					wp_send_json_error( [ 'message' => 'API Token is invalid. Verify the API Token and try again.' ] );
				}
			}
		}

		if ( $updated ) {
			wp_send_json_success( [ 'message' => "API Token successfully {$action}d." ] );
		}
		else {
			wp_send_json_error( [ 'message' => "There was an error. API Token was not {$action}d." ] );
		}
	}

	/**
	 * Generates the HTML for the API Token section with an AJAX-enabled form.
	 *
	 * @return string The HTML string for the API Token form.
	 */
	public function get_api_token_section (): string
	{
		$api_token = get_option( $this->api_token_key, '' );
		$api_token = implode( '', array_map( function ( $char, $index ) use ( $api_token ) {
			return ( $index < 3 || $index >= strlen( $api_token ) - 3 ) ? $char : ( $index < 36 ? '*' : '' );
		}, str_split( $api_token ), array_keys( str_split( $api_token ) ) ) );

		$api_placeholder = $api_token ?: 'Enter your API Token';
		$save_verb       = $api_token ? 'Update' : 'Save';

		ob_start();
		?>
		<script type="application/javascript">

			jQuery(function($) {
				$('.package-upgrader-api-token').html(`
					<form id="api-token-form" style="clear:none;">
				        <?php wp_nonce_field( 'save_api_token_action', 'save_api_token_nonce' ); ?>
				        <table class="form-table" style="clear:none;">
				            <tr valign="top">
				                <th scope="row" style="display:block;padding:4px 0 0 0">
				                    <label for="api_token">API Token</label>
				                </th>
				                <td style="display:block;padding:4px 0">
				                    <p>
				                        <input type="text" id="api_token" name="api_token" title="<?= $api_placeholder ?>" placeholder="<?= $api_placeholder ?>" value="" style="width:100%;padding:3px 10px" />
				                        <input type="hidden" name="plugin_id" value="<?= $this->local->folder ?>" />
				                    </p>
				                </td>
				            </tr>
				        </table>
				        <?= get_submit_button( "{$save_verb} API Token", 'primary', strtolower( $save_verb ), FALSE ) ?>
				        <span id="api-token-remove" style="display: <?= $api_token ? 'inline' : 'none' ?>">
				            <?= get_submit_button( "Remove API Token", 'secondary', 'remove', FALSE ) ?>
				        </span>
				        <br><span id="api-token-message"></span>
				    </form>
				`)

				$('#api-token-form').on('submit', function(e) {
					e.preventDefault()
					const $submit = $(this).find('[type="submit"]'),
						$status = $('#api-token-message'),
						$remove = $('#api-token-remove'),
						$token = $('#api_token')
					$submit.attr('disabled', true)
					$status.html('')
					var form = $(this).serializeArray().reduce(function(obj, item) {
						obj[item.name] = item.value
						return obj
					}, {})
					if (e?.originalEvent?.submitter) {
						form['key_action'] = $(e.originalEvent.submitter).attr('name')
					}
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: { action: 'save_api_token', ...form },
						success: function(response) {
							$status.html(`<span style="color: ${response.success ? 'green' : 'red'};">${response.data.message}</span>`)
							const hasKey = response.success !== (form['key_action'] === 'remove')
							$remove.toggle(hasKey)
							!hasKey && $token.val('').attr('placeholder', 'Enter your API Token')
						},
						error: function() {
							$status.html('<span style="color: red;">There was a server error.</span>')
							$remove.toggle(false)
						},
						complete: function() {
							$submit.attr('disabled', false)
						},
					})
				})
			})
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Determines if the current package (theme or plugin) is being updated.
	 *
	 * @return bool True if the package is being updated, false otherwise.
	 */
	protected function is_updating (): bool
	{
		if ( $this->type === Kind::THEME ) {
			if ( ( $_POST['action'] ?? FALSE ) === 'update-theme' && ( $_POST["slug"] ?? FALSE ) === $this->local->folder ) {
				return TRUE;
			}

			if ( ( $_GET['action'] ?? FALSE ) === 'update-selected-themes' && in_array( $this->local->folder, explode( ',', urldecode( $_GET['themes'] ?? '' ) ) ) ) {
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
}
