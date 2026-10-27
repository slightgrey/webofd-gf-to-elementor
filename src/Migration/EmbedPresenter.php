<?php
/**
 * Describes an embed for people: title, links and what will happen to it.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Migration;

use GFToElementor\Detector\ElementorLocation;
use GFToElementor\Detector\Embed;
use GFToElementor\Detector\EmbedFinder;

/**
 * Adds display data to an Embed for the preview, the REST API and the report.
 */
class EmbedPresenter {

	/**
	 * Returns the embed with its title, type, status, links and planned action.
	 *
	 * @param Embed $embed Embed.
	 *
	 * @return array<string, mixed> Display data.
	 */
	public function describe( Embed $embed ): array {
		$data = $embed->to_array() + array( 'planned' => $this->planned( $embed ) );

		return Embed::KIND_WIDGET === $embed->kind ? $data + $this->widget_details( $embed ) : $data + $this->post_details( $embed->post_id );
	}

	/**
	 * Describes what the migration will do with an embed.
	 *
	 * @param Embed $embed Embed.
	 *
	 * @return string Plain words.
	 */
	public function planned( Embed $embed ): string {
		return match ( $embed->action ) {
			ElementorLocation::WIDGET_REPLACE => __( 'Replace the widget with the Elementor form', 'webofd-gf-to-elementor' ),
			ElementorLocation::INLINE_REPLACE => __( 'Replace the shortcode inside the widget with the form template', 'webofd-gf-to-elementor' ),
			Embed::ACTION_TEMPLATE            => __( 'Replace the shortcode/block with the form template', 'webofd-gf-to-elementor' ),
			default                           => __( 'Report only: change this by hand', 'webofd-gf-to-elementor' ),
		};
	}

	/**
	 * Builds a post's display details.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string, string> Title, type, status and links.
	 */
	private function post_details( int $post_id ): array {
		$type_object = get_post_type_object( (string) get_post_type( $post_id ) );
		$elementor   = EmbedFinder::is_elementor_built( $post_id );

		return array(
			'title'       => '' !== get_the_title( $post_id ) ? (string) get_the_title( $post_id ) : __( '(no title)', 'webofd-gf-to-elementor' ),
			'type_label'  => null !== $type_object ? (string) $type_object->labels->singular_name : (string) get_post_type( $post_id ),
			'post_status' => (string) get_post_status( $post_id ),
			'edit_url'    => $elementor ? admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) : (string) get_edit_post_link( $post_id, 'raw' ),
			'view_url'    => 'publish' === get_post_status( $post_id ) ? (string) get_permalink( $post_id ) : '',
		);
	}

	/**
	 * Builds a widget's display details.
	 *
	 * @param Embed $embed Widget embed.
	 *
	 * @return array<string, string> Title, sidebar and link.
	 */
	private function widget_details( Embed $embed ): array {
		global $wp_registered_sidebars;

		$sidebar = $embed->context;
		$name    = is_array( $wp_registered_sidebars ) && isset( $wp_registered_sidebars[ $sidebar ]['name'] ) ? (string) $wp_registered_sidebars[ $sidebar ]['name'] : $sidebar;

		return array(
			/* translators: %s: widget ID, e.g. "text-3". */
			'title'       => sprintf( __( 'Widget %s', 'webofd-gf-to-elementor' ), $embed->object_ref ),
			'type_label'  => __( 'Widget', 'webofd-gf-to-elementor' ),
			'post_status' => '' === $name || str_starts_with( $sidebar, 'wp_inactive_widgets' ) ? __( 'inactive', 'webofd-gf-to-elementor' ) : $name,
			'edit_url'    => admin_url( 'widgets.php' ),
			'view_url'    => '',
		);
	}
}
