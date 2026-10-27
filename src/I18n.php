<?php
/**
 * Loads the plugin text domain.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor;

/**
 * Makes the plugin's strings translatable.
 */
class I18n {

	/**
	 * Loads translations from the plugin's languages directory.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'webofd-gf-to-elementor', false, dirname( plugin_basename( GF2EL_FILE ) ) . '/languages' );
	}
}
