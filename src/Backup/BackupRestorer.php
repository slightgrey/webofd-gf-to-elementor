<?php
/**
 * Reads the live value behind a backup and writes the original back.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Backup;

use GFToElementor\Swap\ElementorDocuments;
use GFToElementor\Swap\Writer\WidgetOptionWriter;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Restores exact stored bytes, bypassing filters that would alter them; caches are cleared right after.

/**
 * Restores write raw values (no save filters, no Elementor re-save), so the result is
 * byte-identical to what was there before the migration. Caches are cleared afterwards.
 */
class BackupRestorer {

	/**
	 * Creates the restorer.
	 *
	 * @param ElementorDocuments $documents Elementor document access.
	 */
	public function __construct( private readonly ElementorDocuments $documents ) {
	}

	/**
	 * Returns what is stored now for a backup's object.
	 *
	 * @param array<string, mixed> $backup Backup row.
	 *
	 * @return string|null Live value, or null if the object no longer exists.
	 */
	public function live_value( array $backup ): ?string {
		$object_id = (string) $backup['object_id'];

		return match ( (string) $backup['object_type'] ) {
			BackupRepository::TYPE_ELEMENTOR_DATA => null === get_post( (int) $object_id ) ? null : $this->documents->raw_data( (int) $object_id ),
			BackupRepository::TYPE_POST_CONTENT   => null === get_post( (int) $object_id ) ? null : (string) get_post_field( 'post_content', (int) $object_id, 'raw' ),
			BackupRepository::TYPE_WIDGET_OPTION  => WidgetOptionWriter::raw_value( $object_id ),
			BackupRepository::TYPE_POST_META      => $this->live_meta( $object_id ),
			default                               => null,
		};
	}

	/**
	 * Tells whether the object changed after the migration wrote it.
	 *
	 * @param array<string, mixed> $backup Backup row.
	 *
	 * @return bool True if the live value differs from what the migration stored.
	 */
	public function changed_since_migration( array $backup ): bool {
		$live = $this->live_value( $backup );

		return null === $live || md5( $live ) !== (string) $backup['new_hash'];
	}

	/**
	 * Writes a backup's original value back.
	 *
	 * @param array<string, mixed> $backup Backup row.
	 *
	 * @return bool False if the object no longer exists.
	 */
	public function restore( array $backup ): bool {
		$object_id = (string) $backup['object_id'];
		$original  = (string) $backup['original_value'];

		return match ( (string) $backup['object_type'] ) {
			BackupRepository::TYPE_ELEMENTOR_DATA => $this->restore_elementor_data( (int) $object_id, $original ),
			BackupRepository::TYPE_POST_CONTENT   => $this->restore_post_content( (int) $object_id, $original ),
			BackupRepository::TYPE_WIDGET_OPTION  => $this->restore_option( $object_id, $original ),
			BackupRepository::TYPE_POST_META      => $this->restore_meta( $object_id, $original ),
			default                               => false,
		};
	}

	/**
	 * Restores _elementor_data.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $original Original JSON.
	 *
	 * @return bool Success.
	 */
	private function restore_elementor_data( int $post_id, string $original ): bool {
		if ( null === get_post( $post_id ) ) {
			return false;
		}

		$this->documents->restore_raw_data( $post_id, $original );

		return true;
	}

	/**
	 * Restores post_content without running save filters.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $original Original content.
	 *
	 * @return bool Success.
	 */
	private function restore_post_content( int $post_id, string $original ): bool {
		global $wpdb;

		if ( null === get_post( $post_id ) ) {
			return false;
		}

		$wpdb->update( $wpdb->posts, array( 'post_content' => $original ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		return true;
	}

	/**
	 * Restores a widget option's serialized value.
	 *
	 * @param string $option_name Option name.
	 * @param string $original    Original serialized value.
	 *
	 * @return bool Success.
	 */
	private function restore_option( string $option_name, string $original ): bool {
		global $wpdb;

		$updated = $wpdb->update( $wpdb->options, array( 'option_value' => $original ), array( 'option_name' => $option_name ) );

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return false !== $updated;
	}

	/**
	 * Restores another post meta value.
	 *
	 * @param string $object_id "{post_id}|{meta_key}".
	 * @param string $original  Original value.
	 *
	 * @return bool Success.
	 */
	private function restore_meta( string $object_id, string $original ): bool {
		list( $post_id, $meta_key ) = array_pad( explode( '|', $object_id, 2 ), 2, '' );

		if ( null === get_post( (int) $post_id ) ) {
			return false;
		}

		update_metadata( 'post', (int) $post_id, $meta_key, wp_slash( $original ) );

		return true;
	}

	/**
	 * Reads a post meta value named "{post_id}|{meta_key}".
	 *
	 * @param string $object_id Object ID.
	 *
	 * @return string|null Value, or null if the post is gone.
	 */
	private function live_meta( string $object_id ): ?string {
		list( $post_id, $meta_key ) = array_pad( explode( '|', $object_id, 2 ), 2, '' );

		return null === get_post( (int) $post_id ) ? null : (string) get_post_meta( (int) $post_id, $meta_key, true );
	}
}
