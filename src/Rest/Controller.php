<?php
/**
 * REST routes for previewing, migrating and rolling back forms.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Rest;

use GFToElementor\Admin\AdminPage;
use GFToElementor\Admin\EmbedCounter;
use GFToElementor\Capability;
use GFToElementor\Dependencies;
use GFToElementor\Migration\Migrator;
use GFToElementor\Migration\RollbackService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Namespace gf2el/v1. Every route needs the plugin capability and a wp_rest nonce.
 *
 * Body arguments are never marked "required": WordPress validates required arguments
 * before the permission check, which would turn 401/403 answers into 400s.
 */
class Controller {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'gf2el/v1';

	/**
	 * Allowed shape of an embed key, e.g. "post:12:elementor:abc1234" or "widget:text:3".
	 */
	public const KEY_PATTERN = '[a-z]+:[A-Za-z0-9_:.\-]+';

	/**
	 * Creates the controller.
	 *
	 * @param callable(): Migrator        $migrator Returns the migrator (built lazily per request).
	 * @param callable(): RollbackService $rollback Returns the rollback service.
	 */
	public function __construct(
		private $migrator = array( Migrator::class, 'create' ),
		private $rollback = array( RollbackService::class, 'create' )
	) {
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->route( '/forms/(?P<id>\d+)/preview', WP_REST_Server::READABLE, 'preview' );
		$this->route( '/forms/(?P<id>\d+)/migrate', WP_REST_Server::CREATABLE, 'migrate' );
		$this->route( '/migrations/(?P<id>\d+)/embeds/(?P<key>' . self::KEY_PATTERN . ')', WP_REST_Server::CREATABLE, 'swap_embed' );
		$this->route( '/migrations/(?P<id>\d+)/finalize', WP_REST_Server::CREATABLE, 'finalize' );
		$this->route( '/migrations/(?P<id>\d+)/rollback', WP_REST_Server::CREATABLE, 'rollback' );
	}

	/**
	 * Permission callback shared by every route.
	 *
	 * @return bool True if the current user has the plugin capability.
	 */
	public function permissions_check(): bool {
		return Capability::current_user_can_manage();
	}

	/**
	 * GET /forms/{id}/preview.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error Preview or error.
	 */
	public function preview( WP_REST_Request $request ) {
		return $this->respond( fn() => ( $this->migrator )()->preview( absint( $request['id'] ) ), 404 );
	}

	/**
	 * POST /forms/{id}/migrate with { embeds: string[] }.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error Migration ID and queue, or error.
	 */
	public function migrate( WP_REST_Request $request ) {
		$keys = $this->embed_keys( $request->get_param( 'embeds' ) );

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		return $this->requires_elementor_pro() ?? $this->respond( fn() => ( $this->migrator )()->start( absint( $request['id'] ), $keys ), 404 );
	}

	/**
	 * POST /migrations/{id}/embeds/{key}.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error The embed's result row, or error.
	 */
	public function swap_embed( WP_REST_Request $request ) {
		$key = (string) $request['key'];

		return $this->requires_elementor_pro() ?? $this->respond( fn() => ( $this->migrator )()->swap_embed( absint( $request['id'] ), $key ), 409 );
	}

	/**
	 * POST /migrations/{id}/finalize.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error Final status and report URL, or error.
	 */
	public function finalize( WP_REST_Request $request ) {
		$migration_id = absint( $request['id'] );

		return $this->respond(
			function () use ( $migration_id ): array {
				$status = ( $this->migrator )()->finalize( $migration_id )->value;
				EmbedCounter::flush();

				return array(
					'status'     => $status,
					'report_url' => AdminPage::url(
						array(
							'action'    => 'report',
							'migration' => $migration_id,
						)
					),
				);
			},
			409
		);
	}

	/**
	 * POST /migrations/{id}/rollback with { force: bool }.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error Outcome; 409 with the conflicts when unforced and pages changed.
	 */
	public function rollback( WP_REST_Request $request ) {
		$migration_id = absint( $request['id'] );
		$force        = true === rest_sanitize_boolean( (string) $request->get_param( 'force' ) );
		$response     = $this->respond( fn() => ( $this->rollback )()->rollback( $migration_id, $force ), 409 );

		if ( $response instanceof WP_REST_Response && 'conflicts' === ( $response->get_data()['status'] ?? '' ) ) {
			$response->set_status( 409 );
		} elseif ( $response instanceof WP_REST_Response ) {
			EmbedCounter::flush();
		}

		return $response;
	}

	/**
	 * Registers one route with the shared permission callback.
	 *
	 * @param string $path     Route path.
	 * @param string $methods  HTTP methods.
	 * @param string $callback Method name on this class.
	 *
	 * @return void
	 */
	private function route( string $path, string $methods, string $callback ): void {
		register_rest_route(
			self::NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( $this, $callback ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Runs a callback and turns its exceptions into WP_Errors.
	 *
	 * @param callable $callback       Returns the response data.
	 * @param int      $invalid_status HTTP status for an \InvalidArgumentException (missing form, finished migration...).
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	private function respond( callable $callback, int $invalid_status ) {
		try {
			return new WP_REST_Response( $callback(), 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'gf2el_invalid', $e->getMessage(), array( 'status' => $invalid_status ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'gf2el_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Validates the selected embed keys.
	 *
	 * @param mixed $embeds Raw "embeds" parameter.
	 *
	 * @return array<int, string>|WP_Error Keys, or a 400 error.
	 */
	private function embed_keys( mixed $embeds ) {
		$embeds = null === $embeds ? array() : $embeds;

		if ( ! is_array( $embeds ) ) {
			return new WP_Error( 'gf2el_bad_embeds', __( '"embeds" must be a list of embed keys.', 'webofd-gf-to-elementor' ), array( 'status' => 400 ) );
		}

		foreach ( $embeds as $key ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^' . self::KEY_PATTERN . '$/', $key ) ) {
				return new WP_Error( 'gf2el_bad_embeds', __( 'An embed key is not valid.', 'webofd-gf-to-elementor' ), array( 'status' => 400 ) );
			}
		}

		return array_values( array_unique( $embeds ) );
	}

	/**
	 * Returns an error when Elementor Pro is not active, or null.
	 *
	 * @return WP_Error|null Error.
	 */
	private function requires_elementor_pro(): ?WP_Error {
		if ( Dependencies::elementor_pro_active() ) {
			return null;
		}

		return new WP_Error( 'gf2el_elementor_pro_missing', __( 'Elementor Pro must be active to migrate forms.', 'webofd-gf-to-elementor' ), array( 'status' => 412 ) );
	}
}
