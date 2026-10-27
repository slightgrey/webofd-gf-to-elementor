<?php
/**
 * Reads Gravity Forms forms, with or without Gravity Forms active.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Source;

use GFToElementor\Dependencies;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only fallback for when Gravity Forms is inactive; GF owns these tables.

/**
 * Returns forms as plain arrays: GF_Field objects become arrays, and
 * notifications/confirmations are always arrays keyed by their ID.
 */
class GFFormReader {

	/**
	 * Whether to use GFAPI when it is loaded.
	 *
	 * @var bool
	 */
	private bool $use_api;

	/**
	 * Creates the reader.
	 *
	 * @param bool $use_api Use GFAPI when available. False forces the table reads (for testing).
	 */
	public function __construct( bool $use_api = true ) {
		$this->use_api = $use_api;
	}

	/**
	 * Loads one form.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string, mixed>|null Normalized form, or null if it doesn't exist.
	 */
	public function get_form( int $form_id ): ?array {
		$form = $this->api_available() ? $this->read_from_api( $form_id ) : $this->read_from_tables( $form_id );

		return null === $form ? null : $this->normalize( $form );
	}

	/**
	 * Lists every form that isn't in the trash, ordered by ID.
	 *
	 * Reads the tables directly so the list works whether or not Gravity Forms is active.
	 *
	 * @return array<int, array{id: int, title: string, is_active: bool, field_count: int}> Forms.
	 */
	public function list_forms(): array {
		global $wpdb;

		if ( ! Dependencies::gf_tables_exist() ) {
			return array();
		}

		$form_table = $wpdb->prefix . 'gf_form';
		$meta_table = $wpdb->prefix . 'gf_form_meta';
		$rows       = $wpdb->get_results( "SELECT f.id, f.title, f.is_active, m.display_meta FROM {$form_table} f LEFT JOIN {$meta_table} m ON m.form_id = f.id WHERE f.is_trash = 0 ORDER BY f.id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; table names are internal.
		$forms      = array();

		foreach ( (array) $rows as $row ) {
			$forms[] = array(
				'id'          => (int) $row['id'],
				'title'       => (string) $row['title'],
				'is_active'   => '1' === (string) $row['is_active'],
				'field_count' => $this->count_fields( $row['display_meta'] ),
			);
		}

		return $forms;
	}

	/**
	 * Lists the add-on slugs of a form's feeds (Mailchimp, webhooks...), active or not.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<int, string> Unique add-on slugs; empty when there are no feeds.
	 */
	public function get_feeds( int $form_id ): array {
		$slugs = $this->api_available() ? $this->feed_slugs_from_api( $form_id ) : $this->feed_slugs_from_table( $form_id );

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Reads feed add-on slugs through GFAPI.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<int, string> Slugs.
	 */
	private function feed_slugs_from_api( int $form_id ): array {
		$feeds = \GFAPI::get_feeds( null, $form_id, null, null );

		if ( ! is_array( $feeds ) ) {
			return array();
		}

		return array_map( static fn( $feed ): string => (string) ( $feed['addon_slug'] ?? '' ), $feeds );
	}

	/**
	 * Reads feed add-on slugs from the gf_addon_feed table, if it exists.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<int, string> Slugs.
	 */
	private function feed_slugs_from_table( int $form_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'gf_addon_feed';

		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return array();
		}

		return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT addon_slug FROM {$table} WHERE form_id = %d", $form_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
	}

	/**
	 * Counts the fields in a raw display_meta JSON value.
	 *
	 * @param mixed $display_meta Raw column value.
	 *
	 * @return int Field count, 0 if unreadable.
	 */
	private function count_fields( mixed $display_meta ): int {
		$meta = $this->decode_json_column( $display_meta );

		return is_array( $meta['fields'] ?? null ) ? count( $meta['fields'] ) : 0;
	}

	/**
	 * Tells whether GFAPI will be used.
	 *
	 * @return bool True if GFAPI is loaded and allowed.
	 */
	private function api_available(): bool {
		return $this->use_api && class_exists( '\GFAPI' );
	}

	/**
	 * Loads a form through GFAPI.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string, mixed>|null Form, or null if missing.
	 */
	private function read_from_api( int $form_id ): ?array {
		$form = \GFAPI::get_form( $form_id );

		if ( ! is_array( $form ) ) {
			return null;
		}

		// Turns GF_Field objects into plain arrays.
		$plain = json_decode( (string) wp_json_encode( $form ), true );

		return is_array( $plain ) ? $plain : null;
	}

	/**
	 * Loads a form straight from the gf_form and gf_form_meta tables.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string, mixed>|null Form, or null if missing or unreadable.
	 */
	private function read_from_tables( int $form_id ): ?array {
		global $wpdb;

		$form_table = $wpdb->prefix . 'gf_form';
		$meta_table = $wpdb->prefix . 'gf_form_meta';
		$row        = $wpdb->get_row( $wpdb->prepare( "SELECT f.id, f.title, f.is_active, f.is_trash, m.display_meta, m.confirmations, m.notifications FROM {$form_table} f INNER JOIN {$meta_table} m ON m.form_id = f.id WHERE f.id = %d", $form_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are internal.

		if ( ! is_array( $row ) ) {
			return null;
		}

		$form = json_decode( (string) $row['display_meta'], true );

		if ( ! is_array( $form ) ) {
			return null;
		}

		$form['id']            = (int) $row['id'];
		$form['is_active']     = (string) $row['is_active'];
		$form['is_trash']      = (string) $row['is_trash'];
		$form['notifications'] = $this->decode_json_column( $row['notifications'] );
		$form['confirmations'] = $this->decode_json_column( $row['confirmations'] );

		return $form;
	}

	/**
	 * Decodes a JSON column that may be NULL or empty.
	 *
	 * @param mixed $value Raw column value.
	 *
	 * @return array<mixed> Decoded array, empty if missing or invalid.
	 */
	private function decode_json_column( mixed $value ): array {
		$decoded = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : null;

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Makes both read paths return the same shape.
	 *
	 * @param array<string, mixed> $form Raw form.
	 *
	 * @return array<string, mixed> Normalized form.
	 */
	private function normalize( array $form ): array {
		$form['id']            = (int) ( $form['id'] ?? 0 );
		$form['title']         = (string) ( $form['title'] ?? '' );
		$form['fields']        = is_array( $form['fields'] ?? null ) ? array_map( array( $this, 'strip_display_keys' ), array_values( $form['fields'] ) ) : array();
		$form['notifications'] = $this->key_by_id( $form['notifications'] ?? array() );
		$form['confirmations'] = $this->key_by_id( $form['confirmations'] ?? array() );

		return $form;
	}

	/**
	 * Removes field keys GF recomputes per request (consent tick image URL), so both read paths match.
	 *
	 * @param mixed $field Field array.
	 *
	 * @return array<string, mixed> Field without display-only keys.
	 */
	private function strip_display_keys( mixed $field ): array {
		if ( ! is_array( $field ) ) {
			return array();
		}

		unset( $field['checked_indicator_url'], $field['checked_indicator_markup'] );

		return $field;
	}

	/**
	 * Re-keys a notifications or confirmations list by each item's id.
	 *
	 * @param mixed $items Items (keyed or list).
	 *
	 * @return array<string, array<string, mixed>> Items keyed by ID.
	 */
	private function key_by_id( mixed $items ): array {
		$keyed = array();

		foreach ( is_array( $items ) ? $items : array() as $key => $item ) {
			if ( is_array( $item ) ) {
				$keyed[ (string) ( $item['id'] ?? $key ) ] = $item;
			}
		}

		return $keyed;
	}
}
