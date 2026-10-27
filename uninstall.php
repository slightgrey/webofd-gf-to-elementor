<?php
/**
 * Removes everything Web of D GF to Elementor stored: its two tables, its options and
 * its template meta, on every site. Migrated forms and templates stay: they are plain
 * Elementor content. Self-contained: loads no plugin class.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drops the tables and deletes the options and meta for the current site.
 *
 * @return void
 */
function gf2el_uninstall_site(): void {
	global $wpdb;

	foreach ( array( 'gf2el_migrations', 'gf2el_backups' ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping the plugin's own tables; the names are internal.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
	}

	delete_option( 'gf2el_db_version' );
	delete_transient( 'gf2el_embed_counts' );
	delete_post_meta_by_key( '_gf2el_source_form' );
}

/**
 * Runs the site cleanup on every site of every network, 100 sites per query.
 *
 * @return void
 */
function gf2el_uninstall_all_sites(): void {
	$offset = 0;

	do {
		$site_ids    = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			)
		);
		$batch_count = count( $site_ids );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			gf2el_uninstall_site();
			restore_current_blog();
		}

		$offset += 100;
	} while ( 100 === $batch_count );
}

if ( is_multisite() ) {
	gf2el_uninstall_all_sites();
} else {
	gf2el_uninstall_site();
}
