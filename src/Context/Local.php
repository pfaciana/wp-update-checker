<?php

namespace PackageUpgrader\V1\Context;

use PackageUpgrader\V1\Context\Types\Kind;

/**
 * Class Local
 *
 * Represents a local context for plugin or theme updates.
 * This class extends AbstractContext to handle local file-based configurations.
 *
 * @package PackageUpgrader\V1\Context
 */
class Local extends AbstractContext
{
	/** @var string The full path to the plugin or theme file. */
	public string $filename;

	/**
	 * Constructor for the Local class.
	 *
	 * Initializes the local context with the provided configuration.
	 * Sets up essential properties like slug, id, basename, folder, and file.
	 *
	 * @param Kind|string  $type   The type of package.
	 * @param Local|string $config The configuration, either as a string.
	 */
	public function __construct ( Kind|string $type, Local|string $config )
	{
		$this->type     = is_string( $type ) ? Kind::from( $type ) : $type;
		$this->filename = is_string( $config ) ? $config : $config->filename;

		if ( $this->type === Kind::THEME ) {
			$this->id = $this->basename = static::theme_basename( $this->filename );
		}
		else {
			$this->id = $this->basename = plugin_basename( $this->filename );
		}
		[ $this->folder, $this->file ] = explode( '/', $this->id );
		$this->slug = $this->folder;
		if ( $this->type === Kind::THEME ) {
			$this->theme = $this->folder;
		}
		else {
			$this->plugin = $this->id;
		}

		$this->set_props();
	}

	/**
	 * Get the header comments from the plugin or theme file.
	 *
	 * @param bool $force Whether to force a refresh of the comments.
	 * @return object|false The parsed header comments or null if not available.
	 */
	public function get_comments ( $force = FALSE ): object|false
	{
		if ( !$force && isset( $this->comments ) ) {
			return $this->comments;
		}

		$this->comments = static::get_header_comments( $this->filename, [ 'type' => $this->type->value ] );

		return $this->comments;
	}
}
