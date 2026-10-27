<?php
/**
 * Writes swapped post content, backing up what it overwrites.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap\Writer;

use GFToElementor\Backup\BackupRepository;

/**
 * Saves through wp_update_post(), so WordPress keeps a revision of the old content too.
 */
class PostContentWriter {

	/**
	 * Creates the writer.
	 *
	 * @param BackupRepository $backups Backup storage.
	 */
	public function __construct( private readonly BackupRepository $backups ) {
	}

	/**
	 * Transforms and saves a post's content.
	 *
	 * @param int      $migration_id Migration ID.
	 * @param int      $post_id      Post ID.
	 * @param callable $transform    Receives the raw content, returns the new content.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When the post can't be updated.
	 */
	public function write( int $migration_id, int $post_id, callable $transform ): void {
		$raw       = (string) get_post_field( 'post_content', $post_id, 'raw' );
		$new       = $transform( $raw );
		$backup_id = $this->backups->store( $migration_id, BackupRepository::TYPE_POST_CONTENT, (string) $post_id, $raw );
		$updated   = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $new ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			throw new \RuntimeException( esc_html( $updated->get_error_message() ) );
		}

		clean_post_cache( $post_id );
		$this->backups->set_new_hash( $backup_id, (string) get_post_field( 'post_content', $post_id, 'raw' ) );
	}
}
