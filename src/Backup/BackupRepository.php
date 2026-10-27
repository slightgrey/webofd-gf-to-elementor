<?php
/**
 * Stores the original values the migration overwrites.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Backup;

use GFToElementor\Installer;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table written once per swap and read once per rollback.

/**
 * Data access for the gf2el_backups table.
 *
 * One row per changed object per migration: when a page holds two embeds, the
 * first swap's original is kept and later swaps only refresh new_hash, so a
 * rollback always restores the page as it was before the migration started.
 *
 * @phpstan-type BackupRow array{id: int, migration_id: int, object_type: string, object_id: string, original_value: string, new_hash: string, created_at: string}
 */
class BackupRepository {

	/**
	 * Object type for a post's post_content.
	 */
	public const TYPE_POST_CONTENT = 'post_content';

	/**
	 * Object type for a post's _elementor_data meta.
	 */
	public const TYPE_ELEMENTOR_DATA = 'elementor_data';

	/**
	 * Object type for a widget option (widget_text, widget_block...).
	 */
	public const TYPE_WIDGET_OPTION = 'widget_option';

	/**
	 * Object type for another post meta value; object_id is "{post_id}|{meta_key}".
	 */
	public const TYPE_POST_META = 'post_meta';

	/**
	 * Stores the original value of an object, unless this migration already backed it up.
	 *
	 * @param int    $migration_id Migration ID.
	 * @param string $object_type  One of the TYPE_* constants.
	 * @param string $object_id    Post ID or option name.
	 * @param string $original     Exact value before the swap.
	 *
	 * @return int Backup ID (existing or new), 0 on failure.
	 */
	public function store( int $migration_id, string $object_type, string $object_id, string $original ): int {
		$existing = $this->find_for_object( $migration_id, $object_type, $object_id );

		if ( null !== $existing ) {
			return $existing['id'];
		}

		return $this->insert( $migration_id, $object_type, $object_id, $original );
	}

	/**
	 * Records the hash of the value written by the swap.
	 *
	 * @param int    $backup_id Backup ID.
	 * @param string $new_value Value now stored live.
	 *
	 * @return bool True if updated.
	 */
	public function set_new_hash( int $backup_id, string $new_value ): bool {
		global $wpdb;

		return false !== $wpdb->update( Installer::backups_table(), array( 'new_hash' => md5( $new_value ) ), array( 'id' => $backup_id ) );
	}

	/**
	 * Returns every backup of a migration, oldest first.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return array<int, array<string, mixed>> Rows.
	 *
	 * @phpstan-return array<int, BackupRow>
	 */
	public function for_migration( int $migration_id ): array {
		global $wpdb;

		$table = Installer::backups_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE migration_id = %d ORDER BY id ASC", $migration_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.

		return array_map( array( $this, 'cast' ), (array) $rows );
	}

	/**
	 * Deletes every backup of a migration.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_for_migration( int $migration_id ): int {
		global $wpdb;

		return (int) $wpdb->delete( Installer::backups_table(), array( 'migration_id' => $migration_id ), array( '%d' ) );
	}

	/**
	 * Finds this migration's backup of one object.
	 *
	 * @param int    $migration_id Migration ID.
	 * @param string $object_type  Object type.
	 * @param string $object_id    Object ID.
	 *
	 * @return array<string, mixed>|null Row, or null if none.
	 *
	 * @phpstan-return BackupRow|null
	 */
	public function find_for_object( int $migration_id, string $object_type, string $object_id ): ?array {
		global $wpdb;

		$table = Installer::backups_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE migration_id = %d AND object_type = %s AND object_id = %s LIMIT 1", $migration_id, $object_type, $object_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.

		return is_array( $row ) ? $this->cast( $row ) : null;
	}

	/**
	 * Inserts a new backup row.
	 *
	 * @param int    $migration_id Migration ID.
	 * @param string $object_type  Object type.
	 * @param string $object_id    Object ID.
	 * @param string $original     Original value.
	 *
	 * @return int New backup ID, 0 on failure.
	 */
	private function insert( int $migration_id, string $object_type, string $object_id, string $original ): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			Installer::backups_table(),
			array(
				'migration_id'   => $migration_id,
				'object_type'    => $object_type,
				'object_id'      => $object_id,
				'original_value' => $original,
				'new_hash'       => '',
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Casts a raw row's columns.
	 *
	 * @param array<string, mixed> $row Raw row.
	 *
	 * @return array<string, mixed> Typed row.
	 *
	 * @phpstan-return BackupRow
	 */
	private function cast( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'migration_id'   => (int) $row['migration_id'],
			'object_type'    => (string) $row['object_type'],
			'object_id'      => (string) $row['object_id'],
			'original_value' => (string) $row['original_value'],
			'new_hash'       => (string) $row['new_hash'],
			'created_at'     => (string) $row['created_at'],
		);
	}
}
