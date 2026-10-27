<?php
/**
 * Reads and writes migration records.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Migration;

use GFToElementor\Installer;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; rows change on every migration step, so caching would only serve stale data.

/**
 * Data access for the gf2el_migrations table. JSON columns are encoded and decoded here.
 *
 * @phpstan-type MigrationRow array{id: int, gf_form_id: int, gf_form_title: string, template_id: int|null, status: MigrationStatus, field_map: array<string, string>, report: array<string, mixed>, user_id: int, created_at: string, updated_at: string}
 */
class MigrationRepository {

	/**
	 * Creates a migration record in the Running state.
	 *
	 * @param int                   $form_id    Gravity Forms form ID.
	 * @param string                $form_title Form title at migration time.
	 * @param array<string, string> $field_map  GF input ID => Elementor custom_id.
	 * @param array<string, mixed>  $report     Conversion report.
	 *
	 * @return int New migration ID, 0 on failure.
	 */
	public function create( int $form_id, string $form_title, array $field_map, array $report ): int {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Installer::migrations_table(),
			array(
				'gf_form_id'    => $form_id,
				'gf_form_title' => $form_title,
				'status'        => MigrationStatus::Running->value,
				'field_map'     => (string) wp_json_encode( $field_map ),
				'report'        => (string) wp_json_encode( $report ),
				'user_id'       => get_current_user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Loads one migration.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return array<string, mixed>|null Decoded row, or null if not found.
	 *
	 * @phpstan-return MigrationRow|null
	 */
	public function find( int $migration_id ): ?array {
		global $wpdb;

		$table = Installer::migrations_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $migration_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.

		return is_array( $row ) ? $this->decode( $row ) : null;
	}

	/**
	 * Loads the most recent migration of a form.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 *
	 * @return array<string, mixed>|null Decoded row, or null if the form was never migrated.
	 *
	 * @phpstan-return MigrationRow|null
	 */
	public function latest_for_form( int $form_id ): ?array {
		global $wpdb;

		$table = Installer::migrations_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gf_form_id = %d ORDER BY id DESC LIMIT 1", $form_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.

		return is_array( $row ) ? $this->decode( $row ) : null;
	}

	/**
	 * Returns the latest migration's ID, status and date for every migrated form.
	 *
	 * @return array<int, array{id: int, status: MigrationStatus, updated_at: string}> Keyed by form ID.
	 */
	public function latest_by_form(): array {
		global $wpdb;

		$table = Installer::migrations_table();
		$rows  = $wpdb->get_results( "SELECT m.id, m.gf_form_id, m.status, m.updated_at FROM {$table} m INNER JOIN ( SELECT MAX(id) AS id FROM {$table} GROUP BY gf_form_id ) latest ON latest.id = m.id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; table name is internal.
		$map   = array();

		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['gf_form_id'] ] = array(
				'id'         => (int) $row['id'],
				'status'     => MigrationStatus::tryFrom( (string) $row['status'] ) ?? MigrationStatus::Failed,
				'updated_at' => (string) $row['updated_at'],
			);
		}

		return $map;
	}

	/**
	 * Sets a migration's status.
	 *
	 * @param int             $migration_id Migration ID.
	 * @param MigrationStatus $status       New status.
	 *
	 * @return bool True if the row was updated.
	 */
	public function update_status( int $migration_id, MigrationStatus $status ): bool {
		return $this->update( $migration_id, array( 'status' => $status->value ) );
	}

	/**
	 * Replaces a migration's report.
	 *
	 * @param int                  $migration_id Migration ID.
	 * @param array<string, mixed> $report       Report data.
	 *
	 * @return bool True if the row was updated.
	 */
	public function save_report( int $migration_id, array $report ): bool {
		return $this->update( $migration_id, array( 'report' => (string) wp_json_encode( $report ) ) );
	}

	/**
	 * Records the Elementor template created for a migration's non-Elementor embeds.
	 *
	 * @param int $migration_id Migration ID.
	 * @param int $template_id  elementor_library post ID.
	 *
	 * @return bool True if the row was updated.
	 */
	public function set_template_id( int $migration_id, int $template_id ): bool {
		return $this->update( $migration_id, array( 'template_id' => $template_id ) );
	}

	/**
	 * Updates columns and stamps updated_at.
	 *
	 * @param int                       $migration_id Migration ID.
	 * @param array<string, string|int> $data         Column => value.
	 *
	 * @return bool True if the row was updated.
	 */
	private function update( int $migration_id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		return false !== $wpdb->update( Installer::migrations_table(), $data, array( 'id' => $migration_id ) );
	}

	/**
	 * Casts a raw row and decodes its JSON columns.
	 *
	 * @param array<string, mixed> $row Raw row.
	 *
	 * @return array<string, mixed> Decoded row.
	 *
	 * @phpstan-return MigrationRow
	 */
	private function decode( array $row ): array {
		$field_map = json_decode( (string) $row['field_map'], true );
		$report    = json_decode( (string) $row['report'], true );

		return array(
			'id'            => (int) $row['id'],
			'gf_form_id'    => (int) $row['gf_form_id'],
			'gf_form_title' => (string) $row['gf_form_title'],
			'template_id'   => null === $row['template_id'] ? null : (int) $row['template_id'],
			'status'        => MigrationStatus::tryFrom( (string) $row['status'] ) ?? MigrationStatus::Failed,
			'field_map'     => is_array( $field_map ) ? $field_map : array(),
			'report'        => is_array( $report ) ? $report : array(),
			'user_id'       => (int) $row['user_id'],
			'created_at'    => (string) $row['created_at'],
			'updated_at'    => (string) $row['updated_at'],
		);
	}
}
