<?php
/**
 * Plugin Name:       Web of D GF to Elementor
 * Description:       Converts Gravity Forms forms into Elementor Pro forms and swaps every embed, with backups and per-form rollback. Gravity Forms entries are not migrated.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Requires Plugins:  elementor
 * Author:            Web of D
 * Author URI:        https://vincelayte.me/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webofd-gf-to-elementor
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Another copy of this plugin (e.g. a second folder) is already loaded: let it run alone.
if ( defined( 'GF2EL_VERSION' ) ) {
	return;
}

define( 'GF2EL_VERSION', '1.0.0' );
define( 'GF2EL_FILE', __FILE__ );
define( 'GF2EL_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF2EL_URL', plugin_dir_url( __FILE__ ) );

// Declared conditionally: PHP binds unconditional top-level functions at compile time,
// before the duplicate-copy return above could skip them.
if ( ! function_exists( 'gf2el_missing_vendor_notice' ) ) {
	/**
	 * Prints an admin notice telling the site owner to install Composer dependencies.
	 *
	 * @return void
	 */
	function gf2el_missing_vendor_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Web of D GF to Elementor:', 'webofd-gf-to-elementor' ),
			wp_kses(
				__( 'Run <code>composer install</code> in the plugin directory before use.', 'webofd-gf-to-elementor' ),
				array( 'code' => array() )
			)
		);
	}
}

if ( ! file_exists( GF2EL_DIR . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', 'gf2el_missing_vendor_notice' );
	return;
}

require_once GF2EL_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( 'GFToElementor\Installer', 'activate' ) );

GFToElementor\Plugin::run();
