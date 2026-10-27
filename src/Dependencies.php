<?php
/**
 * Checks for the plugins this one works with.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor;

/**
 * Answers "is Elementor Pro active?" and "is there Gravity Forms data to read?".
 */
class Dependencies {

	/**
	 * Screens that show the Elementor Pro notice.
	 */
	private const NOTICE_SCREENS = array( 'plugins', 'tools_page_gf2el' );

	/**
	 * Tells whether Elementor Pro (and therefore its Form widget) is loaded.
	 *
	 * @return bool True if Elementor Pro is active.
	 */
	public static function elementor_pro_active(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) && class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Tells whether Gravity Forms forms can be read, through its API or its tables.
	 *
	 * @return bool True if GFAPI is loaded or the gf_form table exists.
	 */
	public static function gf_available(): bool {
		return class_exists( '\GFAPI' ) || self::gf_tables_exist();
	}

	/**
	 * Tells whether the Gravity Forms form table exists on the current site.
	 *
	 * @return bool True if {prefix}gf_form exists.
	 */
	public static function gf_tables_exist(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'gf_form';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One cheap existence check.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Prints a notice on the plugins and tool screens when Elementor Pro is missing.
	 *
	 * @return void
	 */
	public function render_elementor_pro_notice(): void {
		if ( self::elementor_pro_active() || ! $this->is_notice_screen() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Web of D GF to Elementor:', 'webofd-gf-to-elementor' ),
			esc_html__( 'Elementor Pro must be active. Migrated forms use its Form widget, so nothing can be migrated until it is.', 'webofd-gf-to-elementor' )
		);
	}

	/**
	 * Tells whether the current admin screen should show the dependency notice.
	 *
	 * @return bool True on the plugins screen and the plugin's own page.
	 */
	private function is_notice_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $screen && in_array( $screen->id, self::NOTICE_SCREENS, true ) && current_user_can( 'activate_plugins' );
	}
}
