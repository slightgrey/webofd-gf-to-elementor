<?php
/**
 * Gives every migrated field a unique Elementor custom_id.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Slugs labels into custom_ids ("Mobile Telephone" -> "mobile_telephone"), de-duplicates
 * them, and remembers which Gravity Forms input ID each one came from, so merge tags like
 * {Mobile Telephone:4} can become [field id="mobile_telephone"].
 */
class IdGenerator {

	/**
	 * Longest custom_id produced, before any "_2" de-duplication suffix.
	 */
	private const MAX_LENGTH = 40;

	/**
	 * The custom_ids handed out so far.
	 *
	 * @var array<string, true>
	 */
	private array $used = array();

	/**
	 * Gravity Forms input ID => custom_id.
	 *
	 * @var array<string, string>
	 */
	private array $map = array();

	/**
	 * Assigns a custom_id to a whole field.
	 *
	 * @param string $gf_id GF field ID, e.g. "4".
	 * @param string $label Field label.
	 *
	 * @return string Unique custom_id.
	 */
	public function for_field( string $gf_id, string $label ): string {
		$custom_id = $this->unique( $this->base_for( $gf_id, $label ) );

		$this->map[ $gf_id ] = $custom_id;

		return $custom_id;
	}

	/**
	 * Assigns a custom_id to one input of a multi-input field, e.g. "1.3" -> "your_name_first".
	 *
	 * @param string $gf_input_id GF input ID, e.g. "1.3".
	 * @param string $field_label Parent field label.
	 * @param string $suffix      Input suffix, e.g. "first".
	 *
	 * @return string Unique custom_id.
	 */
	public function for_input( string $gf_input_id, string $field_label, string $suffix ): string {
		$field_id  = strtok( $gf_input_id, '.' );
		$base      = $this->base_for( false === $field_id ? $gf_input_id : $field_id, $field_label );
		$custom_id = $this->unique( $this->trim_length( $base . '_' . $this->slug( $suffix ) ) );

		$this->map[ $gf_input_id ] = $custom_id;

		return $custom_id;
	}

	/**
	 * Points another GF input ID at an existing custom_id (e.g. a checkbox's "11.1" -> "features").
	 *
	 * @param string $gf_input_id GF input ID.
	 * @param string $custom_id   Existing custom_id.
	 *
	 * @return void
	 */
	public function alias( string $gf_input_id, string $custom_id ): void {
		$this->map[ $gf_input_id ] = $custom_id;
	}

	/**
	 * Returns the GF input ID => custom_id map built so far.
	 *
	 * @return array<string, string> Map.
	 */
	public function map(): array {
		return $this->map;
	}

	/**
	 * Builds the slug base for a field, falling back to field_{id} when the label gives no letters.
	 *
	 * @param string $gf_id GF field ID.
	 * @param string $label Label.
	 *
	 * @return string Base custom_id.
	 */
	private function base_for( string $gf_id, string $label ): string {
		$slug = $this->trim_length( $this->slug( $label ) );

		if ( 1 !== preg_match( '/[a-z]/', $slug ) ) {
			return 'field_' . $this->slug( $gf_id );
		}

		return $slug;
	}

	/**
	 * Lower-cases, transliterates accents and joins words with underscores.
	 *
	 * Non-Latin letters (Cyrillic, CJK...) are removed, which can leave an empty slug.
	 *
	 * @param string $text Text.
	 *
	 * @return string Slug of [a-z0-9_].
	 */
	private function slug( string $text ): string {
		$text = strtolower( remove_accents( wp_strip_all_tags( $text ) ) );

		return trim( (string) preg_replace( '/[^a-z0-9]+/', '_', $text ), '_' );
	}

	/**
	 * Cuts a slug to MAX_LENGTH without leaving a trailing underscore.
	 *
	 * @param string $slug Slug.
	 *
	 * @return string Trimmed slug.
	 */
	private function trim_length( string $slug ): string {
		return rtrim( substr( $slug, 0, self::MAX_LENGTH ), '_' );
	}

	/**
	 * Returns the base, or base_2, base_3... if it is already taken, and marks it used.
	 *
	 * @param string $base Base custom_id.
	 *
	 * @return string Unique custom_id.
	 */
	private function unique( string $base ): string {
		$candidate = $base;
		$counter   = 2;

		while ( isset( $this->used[ $candidate ] ) ) {
			$candidate = $base . '_' . $counter;
			++$counter;
		}

		$this->used[ $candidate ] = true;

		return $candidate;
	}
}
