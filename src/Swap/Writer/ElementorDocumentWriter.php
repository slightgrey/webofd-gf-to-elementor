<?php
/**
 * Writes a swapped Elementor document, backing up what it overwrites.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap\Writer;

use GFToElementor\Backup\BackupRepository;
use GFToElementor\Swap\ElementorDocuments;
use GFToElementor\Swap\EmbedNotFound;

/**
 * Elementor's save rewrites _elementor_data, post_content (its plain-text copy) and
 * _elementor_version, so all three are backed up before the save.
 */
class ElementorDocumentWriter {

	/**
	 * Creates the writer.
	 *
	 * @param BackupRepository   $backups   Backup storage.
	 * @param ElementorDocuments $documents Elementor document access.
	 */
	public function __construct(
		private readonly BackupRepository $backups,
		private readonly ElementorDocuments $documents
	) {
	}

	/**
	 * Transforms and saves a document's element tree.
	 *
	 * A \RuntimeException from ElementorDocuments::save_elements() passes through when
	 * Elementor refuses the save.
	 *
	 * @param int      $migration_id Migration ID.
	 * @param int      $post_id      Post ID.
	 * @param callable $transform    Receives the decoded elements, returns the new ones.
	 *
	 * @return void
	 *
	 * @throws EmbedNotFound When the document has no readable Elementor data.
	 */
	public function write( int $migration_id, int $post_id, callable $transform ): void {
		$raw      = $this->documents->raw_data( $post_id );
		$elements = json_decode( $raw, true );

		if ( ! is_array( $elements ) ) {
			/* translators: %d: post ID. */
			throw new EmbedNotFound( sprintf( esc_html__( 'Post %d has no readable Elementor data.', 'webofd-gf-to-elementor' ), (int) $post_id ) );
		}

		$new_elements = $transform( $elements );
		$backup_ids   = $this->back_up( $migration_id, $post_id, $raw );

		$this->documents->save_elements( $post_id, $new_elements );
		$this->record_hashes( $post_id, $backup_ids );
	}

	/**
	 * Backs up the data, content and version the save will overwrite.
	 *
	 * @param int    $migration_id Migration ID.
	 * @param int    $post_id      Post ID.
	 * @param string $raw          Current raw _elementor_data.
	 *
	 * @return array<string, int> Backup IDs keyed by what they hold.
	 */
	private function back_up( int $migration_id, int $post_id, string $raw ): array {
		$ids = array(
			'data'    => $this->backups->store( $migration_id, BackupRepository::TYPE_ELEMENTOR_DATA, (string) $post_id, $raw ),
			'content' => $this->backups->store( $migration_id, BackupRepository::TYPE_POST_CONTENT, (string) $post_id, (string) get_post_field( 'post_content', $post_id, 'raw' ) ),
		);

		if ( metadata_exists( 'post', $post_id, ElementorDocuments::VERSION_KEY ) ) {
			$version        = (string) get_post_meta( $post_id, ElementorDocuments::VERSION_KEY, true );
			$ids['version'] = $this->backups->store( $migration_id, BackupRepository::TYPE_POST_META, $post_id . '|' . ElementorDocuments::VERSION_KEY, $version );
		}

		return $ids;
	}

	/**
	 * Records what is stored after the save, so a rollback can spot later edits.
	 *
	 * @param int                $post_id    Post ID.
	 * @param array<string, int> $backup_ids Backup IDs from back_up().
	 *
	 * @return void
	 */
	private function record_hashes( int $post_id, array $backup_ids ): void {
		clean_post_cache( $post_id );

		$this->backups->set_new_hash( $backup_ids['data'], $this->documents->raw_data( $post_id ) );
		$this->backups->set_new_hash( $backup_ids['content'], (string) get_post_field( 'post_content', $post_id, 'raw' ) );

		if ( isset( $backup_ids['version'] ) ) {
			$this->backups->set_new_hash( $backup_ids['version'], (string) get_post_meta( $post_id, ElementorDocuments::VERSION_KEY, true ) );
		}
	}
}
