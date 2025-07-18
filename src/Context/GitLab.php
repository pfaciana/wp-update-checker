<?php

namespace PackageUpgrader\V1\Context;

use PackageUpgrader\V1\Context\Types\Kind;
use PackageUpgrader\V1\Context\Types\GitLabRelease;

/**
 * Class GitLab
 *
 * Represents a GitLab repository context for updating plugins or themes.
 * Extends the AbstractRemote class to provide GitLab-specific functionality.
 *
 * @package PackageUpgrader\V1\Context
 */
class GitLab extends AbstractRemote
{
	/** @var string The root URL for API requests. */
	protected string $root_url;

	/** @var string The base URL for a repo for API requests. */
	protected string $base_url = 'https://gitlab.com/api/v4';

	/** @var string The base project URL for a repo for API requests. */
	protected string $project_url;

	/** @var Types\GitLabRelease[]|false An array of release information or false if not available. */
	protected array|false $releases;

	/** @var Types\GitLabRelease|false The latest release information as an object or false if not available. */
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

		$this->root_url    = static::get_base_url( $this->base_url ) . '/';
		$this->project_url = $this->root_url . $this->local->gitlab_repo;
		$this->base_url    .= '/projects/' . urlencode( $this->local->gitlab_repo );
	}

	/**
	 * Check if the request is for a project.
	 *
	 * @param string $url The request URL.
	 * @return bool
	 */
	public function is_project_request ( string $url ): bool
	{
		return str_starts_with( $url, $this->base_url ) || str_starts_with( $url, $this->project_url );
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
		if ( $this->is_project_request( $url ) ) {
			if ( !empty( $access_token = $this->apply_filters( 'access_token', FALSE ) ) ) {
				$parsed_args['headers']['PRIVATE-TOKEN'] = "{$access_token}";
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
	 * @return GitLabRelease[]|false The releases.
	 */
	public function get_releases (): array|false
	{
		if ( isset( $this->releases ) ) {
			return $this->releases;
		}

		$release_url = $this->get_endpoint( "/releases?per_page=100" );

		if ( empty( $releases = $this->get_request( $release_url ) ) ) {
			return $this->releases = FALSE;
		}

		$parsedown = new \Parsedown();

		/** @var Types\GitLabRelease[] $releases */
		foreach ( $releases as $index => &$release ) {
			if ( $release->upcoming_release != $this->local->prerelease ) {
				unset( $release[$index] );
				continue;
			}
			$release->description = $parsedown->text( $release->description );
		}

		return $this->releases = array_values( $releases );
	}

	/**
	 * Get the latest release for the repository.
	 *
	 * @return GitLabRelease|false The latest release data, or false if unable to retrieve.
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

		$main_file_url = $this->get_endpoint( "/repository/files/{$this->local->remote_file}/raw?ref={$release->tag_name}" );

		$ext = pathinfo( $this->local->remote_file, PATHINFO_EXTENSION );

		if ( empty( $content = $this->get_request( $main_file_url, $ext ) ) ) {
			return FALSE;
		}

		if ( !is_string( $content ) ) {
			$content = json_encode( $content );
		}

		$options        = [
			'parse_json' => $ext === 'json',
			'type'       => $this->type->value,
		];
		$this->comments = static::get_header_comments( $content, $options );

		$this->comments->gitlab_uri ??= $this->local->comments->gitlab_uri;

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

		$readme_url = $this->get_endpoint( "/repository/files/README.md/raw?ref={$release->tag_name}" );

		if ( empty( $readme = $this->get_request( $readme_url, 'md' ) ) ) {
			return $this->readme = FALSE;
		}

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
			$published_at = new \DateTime( $release->released_at, new \DateTimeZone( 'UTC' ) );
			$published_at->setTimezone( new \DateTimeZone( wp_timezone_string() ) );
			ob_start();
			?>
			<div class="changelog-release">
				<h2><?= esc_html( $release->tag_name ) ?> (<?= $published_at->format( 'Y-m-d' ) ?>)</h2>
				<p><strong><?= esc_html( $release->name ) ?></strong></p>
				<div><?= wp_kses_post( $release->description ) ?></div>
				<p><a href="<?= esc_url( $release->_links->self ) ?>" target="_blank">View Release for <?= esc_html( $release->tag_name ) ?></a></p>
			</div>
			<?php
			$content[] = ob_get_clean();
		}

		return "<a href='{$this->root_url}{$this->local->repo_id}/-/releases' target='_blank'>See all releases</a><br><br>" . implode( "\n", $content );
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

		$published_at = new \DateTime( $release->released_at, new \DateTimeZone( 'UTC' ) );
		$published_at->setTimezone( new \DateTimeZone( wp_timezone_string() ) );

		ob_start();
		?>
		<h1><strong><?= esc_html( $release->name ) ?></strong></h1>
		<?= $release->description ?>
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
	public function get_contributors ()
	{
		if ( !empty( $this->contributors ) ) {
			return $this->contributors;
		}

		if ( !empty( $this->contributors = $this->apply_filters( 'contributors', [] ) ) ) {
			return $this->contributors;
		}

		$users_url = $this->get_endpoint( "/users" );

		if ( empty( $contributors = $this->get_request( $users_url ) ) ) {
			return $this->contributors = [];
		}

		foreach ( $contributors as $contributor ) {
			$this->contributors[$contributor?->username] = [
				'username'     => $contributor?->username,
				'display_name' => $contributor?->name ?? $contributor?->username,
				'profile'      => $contributor?->web_url,
				'avatar'       => $contributor?->avatar_url,
			];
		}

		return $this->contributors;
	}

	/**
	 * Get the number of downloads for the repository.
	 *
	 * This method retrieves the total number of downloads (fetches) for the GitLab repository.
	 * It first checks if the download count is already cached. If not, it fetches the
	 * statistics from the GitLab API and extracts the total fetch count.
	 *
	 * @return int|null The total number of downloads, or null if unable to retrieve.
	 */
	public function get_downloaded (): int|null
	{
		if ( isset( $this->downloaded ) ) {
			return $this->downloaded;
		}

		$statistics_url = $this->get_endpoint( "/statistics" );

		if ( empty( $statistics = $this->get_request( $statistics_url ) ) ) {
			return $this->downloaded = NULL;
		}

		$this->downloaded = $statistics?->fetches?->total ?? NULL;

		return $this->downloaded;
	}

	/**
	 * Get the default icon URL for the repository.
	 *
	 * This method retrieves the default icon URL for the GitLab repository.
	 * It first checks if a default icon is already set in the icons array.
	 * If not, it fetches the project information from the GitLab API and
	 * returns the avatar URL if the project is public and has an avatar.
	 *
	 * @return string|null The URL of the default icon, or null if not available.
	 */
	public function get_default_icon_url (): string|null
	{
		if ( !empty( $this->icons ) && !empty( $this->icons['default'] ) ) {
			return $this->icons['default'];
		}

		if ( empty( $project = $this->get_request( $this->base_url ) ) || empty( $project?->avatar_url ) || $project?->visibility !== 'public' ) {
			return NULL;
		}

		return $project->avatar_url;
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
		$this->url          = $this->homepage ?? $release->_links->self ?? NULL;
		$this->package      = $this->get_release_asset_url( $release );
		$this->last_updated = ( new \DateTime( $release->released_at, new \DateTimeZone( 'GMT' ) ) )->format( 'Y-m-d g:ia' ) . ' GMT';

		$this->release_description = $release->description;

		$this->icons['default'] = $this->get_default_icon_url();

		if ( $with_sections ) {
			$readme = $this->get_readme();

			$this->contributors = $this->get_contributors();

			$this->downloaded = $this->get_downloaded();

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
	 * @param GitLabRelease $release
	 * @return string
	 */
	public function get_release_asset_url ( $release )
	{
		if ( empty( $this->package ) ) {
			if ( $release?->assets?->sources ?? NULL ) {
				foreach ( $release?->assets?->sources as $source ) {
					if ( $source->format === 'zip' ) {
						return $source->url;
					}
				}
			}

			return NULL;
		}

		if ( !empty( $asset_url = filter_var( $this->package, FILTER_VALIDATE_URL ) ?: NULL ) ) {
			return $asset_url;
		}

		return $this->get_endpoint( "/{$release->tag_name}/downloads/{$this->package}" );
	}
}
