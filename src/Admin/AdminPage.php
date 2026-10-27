<?php
/**
 * The Tools → GF to Elementor screen.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Capability;
use GFToElementor\Dependencies;
use GFToElementor\Migration\MigrationRepository;
use GFToElementor\Migration\Migrator;
use GFToElementor\Rest\Controller;
use GFToElementor\Source\GFFormReader;

/**
 * Registers the page, loads its assets, shows its notices and routes to the
 * forms list, a form preview or a migration report.
 */
class AdminPage {

	/**
	 * Page slug.
	 */
	public const SLUG = 'gf2el';

	/**
	 * Screen ID WordPress gives the page.
	 */
	public const SCREEN_ID = 'tools_page_gf2el';

	/**
	 * Hook suffix returned by add_management_page().
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Registers the Tools submenu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->hook_suffix = (string) add_management_page(
			__( 'Web of D GF to Elementor', 'webofd-gf-to-elementor' ),
			__( 'GF to Elementor', 'webofd-gf-to-elementor' ),
			Capability::required(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueues the page's CSS and JS on this screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'gf2el-admin', GF2EL_URL . 'assets/css/admin.css', array(), GF2EL_VERSION );
		wp_enqueue_script( 'gf2el-admin', GF2EL_URL . 'assets/js/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), GF2EL_VERSION, true );
		wp_localize_script(
			'gf2el-admin',
			'gf2elAdmin',
			array(
				'restNamespace' => Controller::NAMESPACE,
				'listUrl'       => self::url(),
			)
		);
		wp_set_script_translations( 'gf2el-admin', 'webofd-gf-to-elementor', GF2EL_DIR . 'languages' );
	}

	/**
	 * Prints the notices that belong on this screen.
	 *
	 * @return void
	 */
	public function render_notices(): void {
		if ( ! $this->is_current_screen() || ! Capability::current_user_can_manage() ) {
			return;
		}

		if ( ! Dependencies::gf_available() ) {
			$this->notice( 'warning', __( 'No Gravity Forms data was found on this site, so there is nothing to migrate.', 'webofd-gf-to-elementor' ) );
		}

		if ( '' === $this->current_action() ) {
			$this->notice( 'info', __( 'Uninstalling this plugin deletes the backups, so migrations can no longer be rolled back. Migrated forms keep working.', 'webofd-gf-to-elementor' ) );
		}
	}

	/**
	 * Renders the page for the requested view.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capability::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'webofd-gf-to-elementor' ) );
		}

		echo '<div class="wrap gf2el">';

		match ( $this->current_action() ) {
			'preview' => ( new PreviewView( Migrator::create(), new MigrationRepository() ) )->render( $this->query_int( 'form' ) ),
			'report'  => ( new ReportView( new MigrationRepository() ) )->render( $this->query_int( 'migration' ) ),
			default   => $this->render_list(),
		};

		echo '</div>';
	}

	/**
	 * Builds a URL to this page.
	 *
	 * @param array<string, string|int> $args Query arguments.
	 *
	 * @return string URL.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Renders the forms list.
	 *
	 * @return void
	 */
	private function render_list(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		printf( '<h1>%s</h1>', esc_html__( 'GF to Elementor', 'webofd-gf-to-elementor' ) );
		printf( '<p class="gf2el-intro">%s</p>', esc_html__( 'Convert a Gravity Forms form into an Elementor Pro form and swap it in everywhere it is used. Preview first: nothing changes until you press Migrate, and every migration can be rolled back.', 'webofd-gf-to-elementor' ) );

		$table = new FormsTable( new GFFormReader(), new MigrationRepository(), new EmbedCounter() );
		$table->prepare_items();
		$table->display();
	}

	/**
	 * Returns the requested view.
	 *
	 * @return string "preview", "report" or "".
	 */
	private function current_action(): string {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.

		return in_array( $action, array( 'preview', 'report' ), true ) ? $action : '';
	}

	/**
	 * Reads a positive integer from the query string.
	 *
	 * @param string $name Parameter name.
	 *
	 * @return int Value, 0 if missing.
	 */
	private function query_int( string $name ): int {
		return isset( $_GET[ $name ] ) ? absint( wp_unslash( $_GET[ $name ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter.
	}

	/**
	 * Tells whether the current screen is this page.
	 *
	 * @return bool True on this page.
	 */
	private function is_current_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $screen && self::SCREEN_ID === $screen->id;
	}

	/**
	 * Prints one notice.
	 *
	 * @param string $type    notice-{type}: info, warning, error.
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private function notice( string $type, string $message ): void {
		printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}
}
