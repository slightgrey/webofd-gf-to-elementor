<?php
/**
 * Reads and writes Elementor document data.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

/**
 * Saves through Elementor's Document API (so post_content, the version stamp and
 * CSS caches stay consistent), and restores raw bytes for rollbacks.
 */
class ElementorDocuments {

	/**
	 * Post meta key holding the element tree.
	 */
	public const DATA_KEY = '_elementor_data';

	/**
	 * Post meta key holding the Elementor version that last saved the document.
	 */
	public const VERSION_KEY = '_elementor_version';

	/**
	 * Returns the raw _elementor_data string exactly as stored.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string JSON, "" if missing.
	 */
	public function raw_data( int $post_id ): string {
		$raw = get_post_meta( $post_id, self::DATA_KEY, true );

		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Saves an element tree through Elementor's Document API.
	 *
	 * @param int          $post_id  Post ID.
	 * @param array<mixed> $elements Element tree.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When Elementor refuses the save.
	 */
	public function save_elements( int $post_id, array $elements ): void {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );

		if ( ! $document || ! $document->save( array( 'elements' => $elements ) ) ) {
			/* translators: %d: post ID. */
			throw new \RuntimeException( sprintf( esc_html__( 'Elementor could not save post %d. Check that you can edit it.', 'webofd-gf-to-elementor' ), (int) $post_id ) );
		}
	}

	/**
	 * Writes an exact _elementor_data string back and clears Elementor's caches.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $raw     Raw JSON.
	 *
	 * @return void
	 */
	public function restore_raw_data( int $post_id, string $raw ): void {
		update_metadata( 'post', $post_id, self::DATA_KEY, wp_slash( $raw ) );
		$this->clear_caches( $post_id );
	}

	/**
	 * Deletes the post's generated CSS and element cache so Elementor rebuilds them.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function clear_caches( int $post_id ): void {
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
		}

		delete_post_meta( $post_id, '_elementor_element_cache' );
	}
}
