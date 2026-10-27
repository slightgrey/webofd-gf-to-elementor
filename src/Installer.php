<?php
/**
 * Creates and upgrades the plugin's database tables.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor;

/**
 * Owns the gf2el_migrations and gf2el_backups schemas and the activation hook.
 */
class Installer {

	/**
	 * Option holding the installed schema version.
	 */
	public const DB_VERSION_OPTION = 'gf2el_db_version';

	/**
	 * Returns the migrations table name for the current site.
	 *
	 * Resolved on every call so it stays correct after switch_to_blog().
	 *
	 * @return string Prefixed table name.
	 */
	public static function migrations_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'gf2el_migrations';
	}

	/**
	 * Returns the backups table name for the current site.
	 *
	 * @return string Prefixed table name.
	 */
	public static function backups_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'gf2el_backups';
	}

	/**
	 * Activation hook callback.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 *
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( self::class, 'install' ) );
			return;
		}

		self::install();
	}

	/**
	 * Runs a callback on every site of the current network, 100 sites per query.
	 *
	 * @param callable $callback Called with no arguments while switched to each site.
	 *
	 * @return void
	 */
	public static function for_each_site( callable $callback ): void {
		$offset = 0;

		do {
			$site_ids = get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 100,
					'offset'     => $offset,
					'network_id' => get_current_network_id(),
				)
			);

			$batch_count = count( $site_ids );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				$callback();
				restore_current_blog();
			}

			$offset += 100;
		} while ( 100 === $batch_count );
	}

	/**
	 * Adds this plugin's tables to the list WordPress drops when a site is deleted.
	 *
	 * @param array<int, string> $tables  Table names to drop.
	 * @param int                $blog_id Site being deleted.
	 *
	 * @return array<int, string> Table names.
	 */
	public static function drop_tables_for_site( array $tables, int $blog_id ): array {
		global $wpdb;

		$prefix = $wpdb->get_blog_prefix( $blog_id );

		return array_merge( $tables, array( $prefix . 'gf2el_migrations', $prefix . 'gf2el_backups' ) );
	}

	/**
	 * Re-runs the installer when the stored schema version is missing or older than the plugin.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		// A background request can still be running on a site that was just deleted;
		// never recreate tables for it.
		if ( is_multisite() && ! wp_is_site_initialized( get_current_blog_id() ) ) {
			return;
		}

		$installed = (string) get_option( self::DB_VERSION_OPTION, '0' );

		if ( version_compare( $installed, GF2EL_VERSION, '>=' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Creates or updates both tables and records the schema version.
	 *
	 * @return void
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::migrations_schema() );
		dbDelta( self::backups_schema() );
		update_option( self::DB_VERSION_OPTION, GF2EL_VERSION, false );
	}

	/**
	 * Builds the CREATE TABLE statement for migrations, in the format dbDelta expects.
	 *
	 * @return string SQL statement.
	 */
	private static function migrations_schema(): string {
		global $wpdb;

		$table           = self::migrations_table();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			gf_form_id bigint(20) unsigned NOT NULL,
			gf_form_title varchar(255) NOT NULL DEFAULT '',
			template_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT '',
			field_map longtext NOT NULL,
			report longtext NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY gf_form_id (gf_form_id)
		) {$charset_collate};";
	}

	/**
	 * Builds the CREATE TABLE statement for backups, in the format dbDelta expects.
	 *
	 * @return string SQL statement.
	 */
	private static function backups_schema(): string {
		global $wpdb;

		$table           = self::backups_table();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			migration_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL DEFAULT '',
			object_id varchar(191) NOT NULL DEFAULT '',
			original_value longtext NOT NULL,
			new_hash char(32) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY migration_id (migration_id)
		) {$charset_collate};";
	}
}
