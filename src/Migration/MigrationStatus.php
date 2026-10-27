<?php
/**
 * Lifecycle states of a form migration.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Migration;

/**
 * Status stored in the gf2el_migrations.status column.
 */
enum MigrationStatus: string {

	case Running    = 'running';
	case Migrated   = 'migrated';
	case Partial    = 'partial';
	case RolledBack = 'rolled_back';
	case Failed     = 'failed';

	/**
	 * Returns the translated label shown in the admin.
	 *
	 * @return string Label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Running    => __( 'In progress', 'webofd-gf-to-elementor' ),
			self::Migrated   => __( 'Migrated', 'webofd-gf-to-elementor' ),
			self::Partial    => __( 'Partial', 'webofd-gf-to-elementor' ),
			self::RolledBack => __( 'Rolled back', 'webofd-gf-to-elementor' ),
			self::Failed     => __( 'Failed', 'webofd-gf-to-elementor' ),
		};
	}
}
