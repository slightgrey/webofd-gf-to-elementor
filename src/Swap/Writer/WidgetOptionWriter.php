<?php
/**
 * Writes a swapped widget option, backing up the exact stored bytes.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap\Writer;

use GFToElementor\Backup\BackupRepository;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the raw serialized option so the backup is byte-exact.

/**
 * Widget options (widget_text, widget_block...) are stored serialized; the backup keeps
 * the serialized string itself so a rollback restores it byte for byte.
 */
class WidgetOptionWriter {

	/**
	 * Creates the writer.
	 *
	 * @param BackupRepository $backups Backup storage.
	 */
	public function __construct( private readonly BackupRepository $backups ) {
	}

	/**
	 * Transforms and saves a widget option.
	 *
	 * @param int      $migration_id Migration ID.
	 * @param string   $option_name  Option name, e.g. "widget_text".
	 * @param callable $transform    Receives the option array, returns the new array.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When the option is missing.
	 */
	public function write( int $migration_id, string $option_name, callable $transform ): void {
		$raw   = self::raw_value( $option_name );
		$value = get_option( $option_name );

		if ( null === $raw || ! is_array( $value ) ) {
			/* translators: %s: option name. */
			throw new \RuntimeException( sprintf( esc_html__( 'The %s option is missing.', 'webofd-gf-to-elementor' ), esc_html( $option_name ) ) );
		}

		$new       = $transform( $value );
		$backup_id = $this->backups->store( $migration_id, BackupRepository::TYPE_WIDGET_OPTION, $option_name, $raw );

		update_option( $option_name, $new );
		$this->backups->set_new_hash( $backup_id, (string) self::raw_value( $option_name ) );
	}

	/**
	 * Reads an option's raw (serialized) value from the database.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return string|null Raw value, or null if the option doesn't exist.
	 */
	public static function raw_value( string $option_name ): ?string {
		global $wpdb;

		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );

		return null === $raw ? null : (string) $raw;
	}
}
