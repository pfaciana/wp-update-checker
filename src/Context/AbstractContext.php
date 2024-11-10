<?php

namespace PackageUpgrader\V1\Context;

use PackageUpgrader\V1\Context\Types\Kind;

abstract class AbstractContext
{
	/** @var Kind The type of package */
	public Kind $type;

	/** @var string The basename of the plugin or theme */
	public string $basename;

	/** @var string The relative path to the plugin or theme folder */
	public string $folder;

	/** @var string The relative path to the plugin or theme file that has the header comments */
	public string $file;

	/** @var string The remote relative path to the plugin or theme file that has the header comments */
	public string $remote_file;

	/** @var string If the plugin or theme needs an api token to access remote data */
	public string $remote_visibility;

	/** @var string The repository type (e.g., 'GitHub', 'GitLab') */
	public string $repo_type;

	# GUIDs
	/** @var string The name of the plugin or theme */
	public string $name;
	/** @var string The slug (usually the basename) */
	public string $slug;
	/** @var string The ID (usually the path_dir/path_file) */
	public string $id;
	/** @var string The theme ID (usually the path_dir/path_file) */
	public string $theme;
	/** @var string The plugin ID (usually the path_dir/path_file) */
	public string $plugin;

	# Meta
	/** @var string|null "Description" */
	public ?string $description;
	/** @var string|null "License" */
	public ?string $license;
	/** @var string|null "Network" */
	public ?string $network;
	/** @var string|null "Text Domain" | "TextDomain" */
	public ?string $text_domain;
	/** @var string|null "Domain Path" | "DomainPath" */
	public ?string $domain_path;
	/** @var string|null ***hook*** */
	public ?string $upgrade_notice;
	/** @var string|null Description of the release */
	public ?string $release_description;
	/**
	 * @var array{
	 *        description?: string,
	 *        installation?: string,
	 *        faq?: string,
	 *        screenshots?: string,
	 *        changelog?: string,
	 *        reviews?: string,
	 *        other_notes?: string,
	 *        latest_release_notes?: string,
	 * }
	 * Additional sections
	 * */
	public array $sections = [];

	# Versions
	/** @var string "Version" */
	public string $version;
	/** @var string|null New version available for update */
	public ?string $new_version;
	/** @var string|null Required by WordPress internally */
	public ?string $current_version;
	/** @var string|null "Requires at least" | "RequiresWP" Minimum required WordPress version */
	public ?string $requires;
	/** @var string|null "Compatible up to" | "Tested up to" | "Tested" Maximum tested WordPress version */
	public ?string $tested;
	/** @var string|null "Requires PHP" Minimum required PHP version */
	public ?string $requires_php;
	/** @var string|null Tag name of the release */
	public ?string $tag_name;

	# Times
	/** @var string|null Last updated time in 'Y-m-d g:ia GMT' format, e.g. "2023-09-19 8:04am GMT" */
	public ?string $last_updated;
	/** @var string|null Date added in 'Y-m-d' format, e.g. "2010-10-11" */
	public ?string $added;

	# URLs
	/** @var string|null URL to the plugin/theme page (usually the WP plugin/theme page) */
	public ?string $url;
	/** @var string|null "Plugin/Theme URI" URL to the plugin/theme homepage (usually self-hosted) */
	public ?string $homepage;
	/** @var string|null "Release Asset" URL to the zip file for download */
	public ?string $release_asset;
	/** @var string|null URL to the zip file for download */
	public ?string $package;
	/** @var string|null URL for donations */
	public ?string $donate_link;
	/** @var string|null "Author URI" URL to the author's profile */
	public ?string $author_profile;
	/** @var string|null "License URI" URL to the license details */
	public ?string $license_link;
	/** @var string|null "Update URI" | "UpdateURI" URL for updates */
	public ?string $update_link;

	# People
	/** @var string|null "Author" */
	public ?string $author;
	/** @var array List of contributors */
	public array $contributors = [];

	# Integers
	/** @var int|null Number of active installations (rounded) */
	public ?int $active_installs;
	/** @var int|null Number of downloads */
	public ?int $downloaded;
	/** @var int|null Number of ratings */
	public ?int $num_ratings;
	/** @var int|null Rating score (0 - 100) */
	public ?int $rating;
	/** @var array Detailed ratings information */
	public array $ratings = [];

	# Tags
	/** @var array "Tags" */
	public array $tags = [];
	/** @var array "Depends" */
	public array $requires_plugins = [];

	# Images
	/** @var array Banner images for the plugin or theme */
	public array $banners = [];
	/** @var array Icon images for the plugin or theme */
	public array $icons = [];

	# Services
	/** @var string|null Repository URI from "GitHub URI", "GitLab URI", etc */
	public ?string $repo_id;
	/** @var string|null "GitHub URI" */
	public ?string $github_repo;
	/** @var string|null "GitLab URI" */
	public ?string $gitlab_repo;
	/** @var string|null "API URI" e.g. https://api.github.com or https://gitlab.com/api/v4 */
	public ?string $api_url;
	/** @var string "Primary Branch" */
	public ?string $branch;
	/** @var bool "Draft Release" */
	public bool $draft;
	/** @var bool "Pre-Release" */
	public bool $prerelease;

	# Comments
	/** @var object|false The parsed header comments or null if not available. */
	protected object|false $comments;

	/**
	 * Get the header comments from the plugin or theme file.
	 *
	 * @return object|false The parsed header comments or null if not available.
	 */
	abstract public function get_comments (): object|false;

	/**
	 * Set properties based on the header comments.
	 *
	 * @param bool $with_sections Whether to include additional sections like readme and changelog.
	 * @param bool $force         Whether to force property setting.
	 * @return bool True if properties were set successfully, false otherwise.
	 */
	public function set_props ( bool $with_sections = FALSE, bool $force = FALSE ): bool
	{
		if ( empty( $comments = $this->get_comments( $force ) ) ) {
			return FALSE;
		}

		$this->name        = $comments->name;
		$this->description = $comments->description ?? '';
		$this->license     = $comments->license ?? NULL;
		$this->network     = $comments->network ?? NULL;
		$this->text_domain = $comments->text_domain ?? NULL;
		$this->domain_path = $comments->domain_path ?? NULL;

		$this->version      = $comments->version ?? '0.0.0';
		$this->requires     = $comments->requires_at_least ?? NULL;
		$this->tested       = $comments->tested ?? $comments->tested_up_to ?? $comments->compatible_up_to ?? NULL;
		$this->requires_php = $comments->requires_php ?? NULL;

		$this->homepage       = $comments->uri ?? NULL;
		$this->donate_link    = $comments->donate_uri ?? NULL;
		$this->license_link   = $comments->license_uri ?? NULL;
		$this->update_link    = $comments->update_uri ?? NULL;
		$this->author_profile = $comments->author_uri ?? NULL;

		$this->author = $comments->author ?? NULL;

		$this->tags             = isset( $comments->tags ) ? array_map( 'trim', explode( ',', $comments->tags ) ) : [];
		$this->requires_plugins = isset( $comments->depends ) ? array_map( 'trim', explode( ',', $comments->depends ) ) : [];

		if ( property_exists( $comments, 'github_uri' ) ) {
			$this->repo_type = 'GitHub';
			$this->repo_id   = $comments->github_uri;
		}
		elseif ( property_exists( $comments, 'gitlab_uri' ) ) {
			$this->repo_type = 'GitLab';
			$this->repo_id   = $comments->gitlab_uri;
		}
		else {
			$this->repo_type = $comments->repo_type ?? NULL;
			$this->repo_id   = $comments->repo_id ?? NULL;
		}
		$this->github_repo       = $comments->github_uri ?? NULL;
		$this->gitlab_repo       = $comments->gitlab_uri ?? NULL;
		$this->remote_file       = $comments->remote_file ?? $this->file ?? NULL;
		$this->release_asset     = $comments->release_asset ?? NULL;
		$this->remote_visibility = strtolower( trim( $comments->remote_visibility ?? 'public' ) );
		$this->package           = $comments->release_asset ?? NULL;
		$this->branch            = $comments->primary_branch ?? 'master';
		$this->draft             = $comments->draft_release ?? FALSE;
		$this->prerelease        = $comments->pre_release ?? FALSE;

		return TRUE;
	}

	/**
	 * Extracts the base URL from a given URL string.
	 *
	 * This function parses a URL and returns a new URL containing only the scheme,
	 * user information (if present), host, and port (if present). It effectively
	 * removes the path, query string, and fragment identifier from the URL.
	 *
	 * @param string $url The URL to parse and extract the base from.
	 * @return string The base URL if successful, or the input url if invalid.
	 */
	static public function get_base_url ( string $url ): string
	{
		$parts = parse_url( $url );

		if ( !isset( $parts['scheme'] ) || !isset( $parts['host'] ) ) {
			return $url;
		}

		$scheme = $parts['scheme'] . '://';;
		$auth = ( ( $user = $parts['user'] ?? '' ) || ( $pass = isset( $parts['pass'] ) ? ':' . $parts['pass'] : '' ) ) ? $user . $pass . '@' : '';
		$host = $parts['host'];
		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

		return $scheme . $auth . $host . $port;
	}


	/**
	 * Gets the basename of a theme.
	 *
	 * This method extracts the name of a theme from its filename.
	 *
	 * @param string $file The filename of theme.
	 * @return string The name of a theme.
	 */
	static public function theme_basename ( $file )
	{
		$file = wp_normalize_path( $file );

		$theme_dir = wp_normalize_path( WP_THEME_DIR );

		// Get relative path from themes directory.
		$file = preg_replace( '#^' . preg_quote( $theme_dir, '#' ) . '/#', '', $file );
		$file = trim( $file, '/' );

		return $file;
	}

	/**
	 * Mocks WordPress header comments from a composer.json file or object.
	 *
	 * @param string|object $composer The composer.json content as a string or decoded JSON object.
	 * @param array{
	 *      type?: string,
	 *  }                   $options  Optional. Additional options for parsing. Default is an empty array.
	 *                                'type': Whether it's a plugin or a theme. Default is `Plugin`.
	 * @return string|false Returns a string containing WordPress header comments or false on failure.
	 */
	static public function mock_header_comments_from_composer_json ( string|object $composer, array $options = [] ): string|false
	{
		if ( is_string( $composer ) ) {
			$composer = \json_decode( $composer );
			if ( \json_last_error() !== \JSON_ERROR_NONE ) {
				return FALSE;
			}
		}

		$options = wp_parse_args( $options, [ 'type' => 'Plugin' ] );
		$type    = $options['type'];

		$wordpress = $composer?->extra?->wordpress ?? new \stdClass();

		$headers = [
			"{$type} Name"      => $wordpress?->{"{$type} Name"} ?? $composer?->name ?? '',
			"{$type} URI"       => $wordpress?->{"{$type} URI"} ?? $composer?->homepage ?? '',
			'Version'           => $wordpress?->Version ?? $composer?->version ?? '',
			'Description'       => $wordpress?->Description ?? $composer?->description ?? '',
			'Author'            => $wordpress?->Author ?? ( $composer?->authors[0]?->name ?? '' ),
			'Author URI'        => $wordpress?->{'Author URI'} ?? ( $composer?->authors[0]?->homepage ?? '' ),
			'Requires at least' => $wordpress?->{'Requires at least'} ?? '',
			'Requires PHP'      => $wordpress?->{'Requires PHP'} ?? ( $composer?->require?->php ?? '' ),
			'License'           => $wordpress?->License ?? $composer?->license ?? '',
			'License URI'       => $wordpress?->{'License URI'} ?? '',
		];

		foreach ( $wordpress as $key => $value ) {
			$headers[$key] = $value;
		}

		$content = "<?php\n\n/**\n";
		foreach ( $headers as $key => $value ) {
			if ( $value ) {
				$content .= " * $key: $value\n";
			}
		}
		$content .= " */\n\n";

		return $content;
	}

	/**
	 * Parses header comments from a file or string content.
	 *
	 * @param string|array $content The content to parse, either as a string, file path, or array of lines.
	 * @param array{
	 *     parse_json?: bool,
	 *     type?: string,
	 * }                   $options Optional. Additional options for parsing. Default is an empty array.
	 *                              'parse_json': Whether to parse the content as JSON. Default is false.
	 *                              'type': Whether it's a plugin or a theme. Default is `Plugin`.
	 * @return object|false Returns an object containing parsed header comments or false on failure.
	 */
	static public function get_header_comments ( string|array $content, array $options = [] ): object|false
	{
		$options = wp_parse_args( $options, [ 'parse_json' => FALSE, 'type' => 'Plugin' ] );

		$details = new \stdClass();

		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( !is_string( $content ) ) {
			return FALSE;
		}

		if ( file_exists( $content ) ) {
			$content = file_get_contents( $filename = $content );

			$options['parse_json'] = $options['parse_json'] ?: pathinfo( $filename, PATHINFO_EXTENSION ) === 'json';
		}

		if ( $options['parse_json'] ) {
			$content = static::mock_header_comments_from_composer_json( $content, $options );
		}

		if ( empty( $content ) ) {
			return FALSE;
		}

		if ( preg_match( "/^(?:[\s]*)(?:<\?php)/", $content, $matches ) == 0 ) {
			$content = "<?php\n" . $content . "\n?>";
		}

		$comment = '';

		foreach ( token_get_all( $content ) as $token ) {
			if ( $token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT ) {
				$comment = $token[1];
				break;
			}
		}

		$comment_lines = preg_split( "/(\r\n|\n|\r)/", $comment );

		foreach ( $comment_lines as $comment_line ) {
			$lines = explode( ":", $comment_line, 2 );
			if ( count( $lines ) == 2 ) {
				$key           = preg_replace( '/[\s-]+/', '_', strtolower( trim( $lines[0], "* \t\n\r\0\x0B" ) ) );
				$value         = trim( $lines[1] );
				$details->$key = $value;
			}
		}

		if ( !property_exists( $details, 'name' ) ) {
			if ( property_exists( $details, 'plugin_name' ) ) {
				$details->name = $details->plugin_name;
			}
			elseif ( property_exists( $details, 'theme_name' ) ) {
				$details->name = $details->theme_name;
			}
		}

		if ( !property_exists( $details, 'uri' ) ) {
			if ( property_exists( $details, 'plugin_uri' ) ) {
				$details->uri = $details->plugin_uri;
			}
			elseif ( property_exists( $details, 'theme_uri' ) ) {
				$details->uri = $details->theme_uri;
			}
		}

		return $details;
	}
}
