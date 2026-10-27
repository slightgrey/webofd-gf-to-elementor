<?php
/**
 * Core plugin bootstrap.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor;

/**
 * Bootstraps all plugin components by wiring hooks through the Loader.
 */
class Plugin {

	/**
	 * Hook loader instance.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Sets up the Loader and registers all component hooks.
	 */
	public function __construct() {
		$this->loader = new Loader();
		$this->register_hooks();
	}

	/**
	 * Wires all component hooks into the Loader.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$this->register_i18n_hooks();
		$this->register_upgrade_hooks();
		$this->register_rest_hooks();
		$this->register_admin_hooks();
	}

	/**
	 * Registers the REST routes.
	 *
	 * @return void
	 */
	private function register_rest_hooks(): void {
		$controller = new Rest\Controller();
		$this->loader->add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
	}

	/**
	 * Registers the schema check that creates missing tables on first admin load.
	 *
	 * Covers network activation and sites created later on multisite.
	 *
	 * @return void
	 */
	private function register_upgrade_hooks(): void {
		$this->loader->add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );

		if ( is_multisite() ) {
			$this->loader->add_filter( 'wpmu_drop_tables', array( Installer::class, 'drop_tables_for_site' ), 10, 2 );
		}
	}

	/**
	 * Registers the text domain loader.
	 *
	 * @return void
	 */
	private function register_i18n_hooks(): void {
		$i18n = new I18n();
		$this->loader->add_action( 'init', array( $i18n, 'load_textdomain' ) );
	}

	/**
	 * Registers admin-only hooks.
	 *
	 * @return void
	 */
	private function register_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$dependencies = new Dependencies();
		$this->loader->add_action( 'admin_notices', array( $dependencies, 'render_elementor_pro_notice' ) );

		$page = new Admin\AdminPage();
		$this->loader->add_action( 'admin_menu', array( $page, 'register_menu' ) );
		$this->loader->add_action( 'admin_enqueue_scripts', array( $page, 'enqueue_assets' ) );
		$this->loader->add_action( 'admin_notices', array( $page, 'render_notices' ) );
	}

	/**
	 * Runs the loader to register all hooks with WordPress.
	 *
	 * Call this once from the main plugin file.
	 *
	 * @return void
	 */
	public static function run(): void {
		$instance = new self();
		$instance->loader->run();
	}
}
