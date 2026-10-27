<?php
/**
 * Undoes a form migration.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Migration;

use GFToElementor\Backup\BackupRepository;
use GFToElementor\Backup\BackupRestorer;
use GFToElementor\Swap\ElementorDocuments;
use GFToElementor\Swap\ShortcodeReplacer;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One reference search per rollback.

/**
 * Restores every backup of a migration. Locations edited after the migration are
 * reported first and only overwritten when the user confirms.
 */
class RollbackService {

	/**
	 * Creates the service.
	 *
	 * @param MigrationRepository $migrations Migration records.
	 * @param BackupRepository    $backups    Backup storage.
	 * @param BackupRestorer      $restorer   Restorer.
	 */
	public function __construct(
		private readonly MigrationRepository $migrations,
		private readonly BackupRepository $backups,
		private readonly BackupRestorer $restorer
	) {
	}

	/**
	 * Builds the service with default collaborators.
	 *
	 * @return self Service.
	 */
	public static function create(): self {
		return new self( new MigrationRepository(), new BackupRepository(), new BackupRestorer( new ElementorDocuments() ) );
	}

	/**
	 * Lists the locations that changed after the migration wrote them.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return array<int, array{object_type: string, object_id: string, title: string}> Conflicts.
	 */
	public function conflicts( int $migration_id ): array {
		$conflicts = array();

		foreach ( $this->backups->for_migration( $migration_id ) as $backup ) {
			if ( '' !== $backup['new_hash'] && $this->restorer->changed_since_migration( $backup ) ) {
				$conflicts[] = array(
					'object_type' => $backup['object_type'],
					'object_id'   => $backup['object_id'],
					'title'       => $this->title( $backup ),
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Rolls a migration back.
	 *
	 * @param int  $migration_id Migration ID.
	 * @param bool $force        Overwrite locations edited after the migration.
	 *
	 * @return array{status: string, conflicts: array<int, array<string, string>>, restored: int, template_trashed: bool} Outcome.
	 *
	 * @throws \InvalidArgumentException When the migration doesn't exist or was already rolled back.
	 */
	public function rollback( int $migration_id, bool $force = false ): array {
		$migration = $this->migrations->find( $migration_id );

		if ( null === $migration || MigrationStatus::RolledBack === $migration['status'] ) {
			throw new \InvalidArgumentException( esc_html__( 'This migration does not exist or was already rolled back.', 'webofd-gf-to-elementor' ) );
		}

		$conflicts = $this->conflicts( $migration_id );

		if ( array() !== $conflicts && ! $force ) {
			return array(
				'status'           => 'conflicts',
				'conflicts'        => $conflicts,
				'restored'         => 0,
				'template_trashed' => false,
			);
		}

		$restored = $this->restore_all( $migration_id );
		$trashed  = $this->trash_unused_template( $migration['template_id'] );

		$this->migrations->update_status( $migration_id, MigrationStatus::RolledBack );

		return array(
			'status'           => MigrationStatus::RolledBack->value,
			'conflicts'        => $conflicts,
			'restored'         => $restored,
			'template_trashed' => $trashed,
		);
	}

	/**
	 * Restores every backup of a migration.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return int Number of objects restored.
	 */
	private function restore_all( int $migration_id ): int {
		$restored = 0;
		$posts    = array();

		foreach ( $this->backups->for_migration( $migration_id ) as $backup ) {
			$restored += $this->restorer->restore( $backup ) ? 1 : 0;

			if ( BackupRepository::TYPE_WIDGET_OPTION !== $backup['object_type'] ) {
				$posts[] = (int) $backup['object_id'];
			}
		}

		foreach ( array_unique( $posts ) as $post_id ) {
			clean_post_cache( $post_id );
		}

		return $restored;
	}

	/**
	 * Trashes the migration's template if nothing references it any more.
	 *
	 * @param int|null $template_id Template post ID.
	 *
	 * @return bool True if it was trashed.
	 */
	private function trash_unused_template( ?int $template_id ): bool {
		if ( null === $template_id || 'elementor_library' !== get_post_type( $template_id ) || $this->is_referenced( $template_id ) ) {
			return false;
		}

		return false !== wp_trash_post( $template_id );
	}

	/**
	 * Tells whether any live post, Elementor document or widget still uses a template.
	 *
	 * Revisions don't count: the migration's own saves left revisions that mention it.
	 *
	 * @param int $template_id Template post ID.
	 *
	 * @return bool True if referenced.
	 */
	private function is_referenced( int $template_id ): bool {
		global $wpdb;

		$needle = '%' . $wpdb->esc_like( substr( ShortcodeReplacer::template_shortcode( $template_id ), 0, -1 ) ) . '%';
		$json   = '%' . $wpdb->esc_like( '"template_id":"' . $template_id . '"' ) . '%';

		$in_posts   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID <> %d AND post_type <> 'revision' AND post_status <> 'trash' AND post_content LIKE %s", $template_id, $needle ) );
		$in_meta    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.post_id <> %d AND p.post_type <> 'revision' AND p.post_status <> 'trash' AND pm.meta_key = '_elementor_data' AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )", $template_id, str_replace( '"', '\\\\"', $needle ), $json ) );
		$in_options = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", $wpdb->esc_like( 'widget_' ) . '%', $needle ) );

		return $in_posts + $in_meta + $in_options > 0;
	}

	/**
	 * Returns a readable name for a backup's object.
	 *
	 * @param array<string, mixed> $backup Backup row.
	 *
	 * @return string Title.
	 */
	private function title( array $backup ): string {
		if ( BackupRepository::TYPE_WIDGET_OPTION === $backup['object_type'] ) {
			return (string) $backup['object_id'];
		}

		$post_id = (int) $backup['object_id'];

		return '' !== get_the_title( $post_id ) ? (string) get_the_title( $post_id ) : '#' . $post_id;
	}
}
