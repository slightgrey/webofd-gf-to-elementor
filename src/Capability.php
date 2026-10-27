<?php
/**
 * The capability required to use Web of D GF to Elementor.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor;

/**
 * Single source for the gf2el_capability filter, shared by admin screens and REST routes.
 */
final class Capability {

	/**
	 * Returns the capability needed to preview, migrate and roll back forms.
	 *
	 * @return string Capability name (default manage_options).
	 */
	public static function required(): string {
		$capability = apply_filters( 'gf2el_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * Tells whether the current user may use the plugin.
	 *
	 * @return bool True if allowed.
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::required() );
	}
}
