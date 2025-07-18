<?php

namespace PackageUpgrader\V1\Context;

use PackageUpgrader\V1\Context\Types\Kind;
use PackageUpgrader\V1\Context\Types\GitHubRelease;

/**
 * Class GitHub
 *
 * Handles GitHub-specific functionality for updating plugins and themes.
 *
 * @package PackageUpgrader\V1\Context
 * @extends AbstractRemote
 */
class GitHub extends AbstractRemote
{
	/** @var string The root URL for API requests. */
	protected string $root_url;

	/** @var string The base URL for a repo for API requests. */
	protected string $base_url = 'https://api.github.com';

	/** @var Types\GitHubRelease[]|false An array of release information or false if not available. */
	protected array|false $releases;

	/** @var Types\GitHubRelease|false The latest release information as an object or false if not available. */
	protected object|false $latest;

	/**
	 * Constructor for the AbstractRemote class.
	 *
	 * @param Kind|string  $type   The type of package.
	 * @param string|Local $config The configuration, either as a string or Local object.
	 */
	public function __construct ( Kind|string $type, Local|string $config )
	{
		parent::__construct( $type, $config );

		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 10, 2 );

		$this->root_url = $this->base_url;
		$this->base_url .= "/repos/{$this->local->github_repo}";
	}

	/**
	 * Modify HTTP request arguments.
	 *
	 * @param array  $parsed_args The current request arguments.
	 * @param string $url         The request URL.
	 * @return array Modified request arguments.
	 */
	public function http_request_args ( array $parsed_args, string $url ): array
	{
		if ( str_starts_with( $url, $this->base_url ) ) {
			if ( !empty( $access_token = $this->apply_filters( 'access_token', FALSE ) ) ) {
				$parsed_args['headers']['Authorization'] = "Bearer {$access_token}";
			}
			if ( !empty( $parsed_args['filename'] ??= '' ) && ( wp_doing_cron() || $this->is_updating() ) && $this->local?->release_asset ) {
				$parsed_args['headers']['Accept'] = 'application/octet-stream';
			}
		}

		return $parsed_args;
	}

	/**
	 * Validate the API token.
	 *
	 * @return bool
	 */
	public function validate_api_token (): bool
	{
		return !empty( $this->get_request( $this->base_url, 'json', TRUE ) );
	}

	/**
	 * Get all releases for the repository.
	 *
	 * @return GitHubRelease[]|false The releases, or false if unable to retrieve.
	 */
	public function get_releases (): array|false
	{
		if ( isset( $this->releases ) ) {
			return $this->releases;
		}

		$release_url = $this->get_endpoint( '/releases' );

		if ( empty( $releases = $this->get_request( $release_url ) ) ) {
			return $this->releases = FALSE;
		}

		$this->downloaded = 0;
		foreach ( $releases as $release ) {
			foreach ( $release->assets as $asset ) {
				$this->downloaded += $asset->download_count;
			}
		}

		$parsedown = new \Parsedown();

		foreach ( $releases as $index => &$release ) {
			if ( $release->draft != $this->local->draft || $release->prerelease != $this->local->prerelease ) {
				unset( $release[$index] );
				continue;
			}
			$release->body = $parsedown->text( $release->body );
		}

		return $this->releases = array_values( $releases );
	}

	/**
	 * Get the latest release for the repository.
	 *
	 * @return GitHubRelease|false The latest release data, or false if unable to retrieve.
	 */
	public function get_latest_release (): object|false
	{
		if ( isset( $this->latest ) ) {
			return $this->latest;
		}

		if ( empty( $releases = $this->get_releases() ) || !is_array( $releases ) ) {
			return $this->latest = FALSE;
		}

		$this->latest = $releases[array_key_first( $releases )];

		return $this->latest;
	}

	/**
	 * Get comments from the main file of the latest release.
	 *
	 * @return object|false The parsed comments or false if unable to retrieve.
	 */
	public function get_comments (): object|false
	{
		if ( isset( $this->comments ) ) {
			return $this->comments;
		}

		if ( empty( $release = $this->get_latest_release() ) ) {
			return FALSE;
		}

		$main_file_url = $this->get_endpoint( "/contents/{$this->local->remote_file}?ref={$release->tag_name}" );

		$ext = pathinfo( $this->local->remote_file, PATHINFO_EXTENSION );

		if ( empty( $content = $this->get_request( $main_file_url, $ext ) ) ) {
			return FALSE;
		}

		$content = base64_decode( $content->content );

		$options        = [
			'parse_json' => $ext === 'json',
			'type'       => $this->type->value,
		];
		$this->comments = static::get_header_comments( $content, $options );

		$this->comments->github_uri ??= $this->local->comments->github_uri;

		return $this->comments;
	}

	/**
	 * Get the readme content for the repository.
	 *
	 * @return string|false The readme content, or false if unable to retrieve.
	 */
	public function get_readme (): string|false
	{
		if ( isset( $this->readme ) ) {
			return $this->readme;
		}

		if ( !empty( $this->readme = $this->apply_filters( 'readme', FALSE ) ) ) {
			return $this->readme;
		}

		if ( empty( $release = $this->get_latest_release() ) ) {
			return $this->readme = FALSE;
		}

		$readme_url = $this->get_endpoint( "/readme?ref={$release->tag_name}" );

		if ( empty( $content = $this->get_request( $readme_url ) ) ) {
			return $this->readme = FALSE;
		}

		$readme = base64_decode( $content->content );

		$this->readme = ( new \Parsedown() )->text( $readme );

		return $this->readme;
	}

	/**
	 * Get the changelog for all releases.
	 *
	 * @return string The formatted changelog HTML.
	 */
	public function get_changelog (): string
	{
		$releases = $this->get_releases();

		if ( empty( $releases ) ) {
			return $this->release_description;
		}

		$content = [];

		foreach ( $releases as $release ) {
			$published_at = new \DateTime( $release->published_at, new \DateTimeZone( 'UTC' ) );
			$published_at->setTimezone( new \DateTimeZone( wp_timezone_string() ) );
			ob_start();
			?>
			<div class="changelog-release">
				<h2><?= $release->tag_name ?> (<?= $published_at->format( 'Y-m-d' ) ?>)</h2>
				<p><strong><?= $release->name ?></strong></p>
				<div><?= $release->body ?></div>
				<p><a href="<?= $release->html_url ?>" target="_blank">View Release for <?= $release->tag_name ?></a></p>
			</div>
			<?php
			$content[] = ob_get_clean();
		}

		return "<a href='https://github.com/{$this->local->repo_id}/releases' target='_blank'>See all releases</a><br><br>" . implode( "\n", $content );
	}

	/**
	 * Get the latest release notes.
	 *
	 * @return string|false The formatted release notes HTML, or false if unable to retrieve.
	 */
	public function get_latest_release_notes (): string|false
	{
		if ( empty( $release = $this->get_latest_release() ) ) {
			return FALSE;
		}

		$published_at = new \DateTime( $release->published_at, new \DateTimeZone( 'UTC' ) );
		$published_at->setTimezone( new \DateTimeZone( wp_timezone_string() ) );

		ob_start();
		?>
		<h1><strong><?= $release->name ?></strong></h1>
		<?= $release->body ?>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the contributors for the repository.
	 *
	 * This method retrieves the list of contributors for the GitHub repository.
	 * It first checks if the contributors are already cached, then tries to apply
	 * filters. If no cached or filtered data is available, it fetches the
	 * contributors from the GitHub API and formats the data.
	 *
	 * @return array An associative array of contributors, where the key is the
	 *               contributor's login and the value is an array containing:
	 *               - username: The contributor's GitHub username
	 *               - display_name: The contributor's display name (with company if available)
	 *               - profile: The URL to the contributor's GitHub profile
	 *               - avatar: The URL to the contributor's avatar image
	 */
	public function get_contributors (): array
	{
		if ( !empty( $this->contributors ) ) {
			return $this->contributors;
		}

		if ( !empty( $this->contributors = $this->apply_filters( 'contributors', [] ) ) ) {
			return $this->contributors;
		}

		$contributors_url = $this->get_endpoint( "/contributors" );

		if ( empty( $contributors = $this->get_request( $contributors_url ) ) ) {
			return $this->contributors = [];
		}

		foreach ( $contributors as $contributor ) {
			if ( !empty( $user = $this->get_request( "{$this->root_url}/user/{$contributor->id}" ) ) ) {
				$contributor = (object) ( (array) $contributor + (array) $user );
			}

			$this->contributors[$contributor?->login] = [
				'username'     => $contributor?->login,
				'display_name' => ( $contributor?->name ?? $contributor?->login ) . ( $contributor?->company ? " ({$contributor->company})" : '' ),
				'profile'      => $contributor?->html_url,
				'avatar'       => $contributor?->avatar_url,
			];
		}

		return $this->contributors;
	}

	/**
	 * Set properties for the remote context based on the header comments.
	 *
	 * @param bool $with_sections Whether to include additional sections like readme and changelog.
	 * @param bool $force         Whether to force property setting.
	 * @return bool True if properties were set successfully, false otherwise.
	 */
	public function set_props ( bool $with_sections = FALSE, bool $force = FALSE ): bool
	{
		if ( empty( parent::set_props() ) ) {
			return FALSE;
		}

		if ( empty( $release = $this->get_latest_release() ) ) {
			return FALSE;
		}
		$release_version = ltrim( $release->tag_name, "v \t\n\r\0\x0B" );

		$this->tag_name     = $release->tag_name;
		$this->new_version  = version_compare( $this->version, $release_version ) >= 0 ? $this->version : $release_version;
		$this->url          = $this->homepage ?? $release->html_url ?? NULL;
		$this->package      = $this->get_release_asset_url( $release );
		$this->last_updated = ( new \DateTime( $release->published_at, new \DateTimeZone( 'GMT' ) ) )->format( 'Y-m-d g:ia' ) . ' GMT';

		$this->release_description = $release->body;

		if ( $with_sections ) {
			$readme = $this->get_readme();

			$this->contributors = $this->get_contributors();

			$this->sections = [
				'description'           => $readme ?: $this->release_description,
				// 'installation'      => '',
				// 'faq'               => '',
				// 'screenshots'       => '',
				'changelog'             => $this->get_changelog(),
				// 'reviews'           => '',
				// 'other_notes'       => '',
				'current_release_notes' => $this->get_latest_release_notes(),
			];
		}

		return $this->apply_filters( 'package_information', TRUE, $this, $with_sections );
	}

	/**
	 * Get the release asset URL.
	 *
	 * @param GitHubRelease $release
	 * @return string
	 */
	public function get_release_asset_url ( $release )
	{
		if ( empty( $this->package ) ) {
			return $release?->zipball_url ?? NULL;
		}

		if ( !empty( $asset_url = filter_var( $this->package, FILTER_VALIDATE_URL ) ?: NULL ) ) {
			return $asset_url;
		}

		if ( empty( $release?->assets ) || !is_array( $release?->assets ) ) {
			return NULL;
		}

		foreach ( $release?->assets as $asset ) {
			if ( $asset?->name === $this->package ) {
				return "{$this->base_url}/releases/assets/{$asset->id}";
			}
		}

		return NULL;
	}
}
