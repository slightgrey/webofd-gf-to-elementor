<?php
/**
 * Converts Gravity Forms merge tags into Elementor shortcodes.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * {Email:6} -> [field id="email"], {all_fields} -> [all-fields], {admin_email} and
 * {form_title} -> their values. Anything Elementor can't evaluate is removed and reported.
 */
final class MergeTagConverter {

	/**
	 * Matches a field tag's inside: label, field/input ID, optional modifier.
	 */
	private const FIELD_TAG = '/^(.*):(\d+(?:\.\d+)?)(?::([^{}]*))?$/s';

	/**
	 * Matches a named tag's inside, e.g. entry_id or user:display_name (no spaces or semicolons, so CSS is left alone).
	 */
	private const NAMED_TAG = '/^[a-z_]+(?::[^{}\s;]*)?$/';

	/**
	 * Creates the converter.
	 *
	 * @param array<string, string> $field_map   GF input ID => Elementor custom_id.
	 * @param string                $admin_email Site admin email (replaces {admin_email}).
	 * @param string                $form_title  Form title (replaces {form_title}).
	 */
	public function __construct(
		private readonly array $field_map,
		private readonly string $admin_email,
		private readonly string $form_title
	) {
	}

	/**
	 * Converts every merge tag in a text.
	 *
	 * @param string $text Text with GF merge tags.
	 *
	 * @return ConvertedText Converted text and the tags that could not be converted.
	 */
	public function convert( string $text ): ConvertedText {
		$problems  = array();
		$converted = preg_replace_callback(
			'/\{([^{}]*)\}/',
			function ( array $found ) use ( &$problems ): string {
				return $this->replace_tag( $found[0], $found[1], $problems );
			},
			$text
		);

		return new ConvertedText( (string) $converted, array_values( array_unique( $problems ) ) );
	}

	/**
	 * Returns the custom_id when a text is exactly one field merge tag, e.g. "{Email:6}".
	 *
	 * Used for reply-to, which Elementor only accepts as a field ID.
	 *
	 * @param string $text Text.
	 *
	 * @return string|null custom_id, or null if the text is anything else.
	 */
	public function single_field_id( string $text ): ?string {
		if ( 1 !== preg_match( '/^\{([^{}]*)\}$/', trim( $text ), $match ) || 1 !== preg_match( self::FIELD_TAG, $match[1], $parts ) ) {
			return null;
		}

		return $this->field_map[ $parts[2] ] ?? null;
	}

	/**
	 * Returns the replacement for one {...} match.
	 *
	 * @param string             $tag      Whole tag, e.g. "{Email:6}".
	 * @param string             $inside   Text between the braces.
	 * @param array<int, string> $problems Collects tags that were removed or changed.
	 *
	 * @return string Replacement.
	 */
	private function replace_tag( string $tag, string $inside, array &$problems ): string {
		if ( 1 === preg_match( '/^all_fields(:.*)?$/', $inside, $all ) ) {
			return $this->with_modifier_note( '[all-fields]', $tag, $all[1] ?? '', $problems );
		}

		if ( 'admin_email' === $inside ) {
			return $this->admin_email;
		}

		if ( 'form_title' === $inside ) {
			return $this->form_title;
		}

		if ( 1 === preg_match( self::FIELD_TAG, $inside, $parts ) ) {
			return $this->replace_field_tag( $tag, $parts, $problems );
		}

		if ( 1 === preg_match( self::NAMED_TAG, $inside ) ) {
			$problems[] = $tag;

			return '';
		}

		return $tag;
	}

	/**
	 * Replaces a field tag, or removes it when the field no longer exists.
	 *
	 * @param string             $tag      Whole tag.
	 * @param array<int, string> $parts    Regex parts: 1 = label, 2 = ID, 3 = modifier.
	 * @param array<int, string> $problems Collects problem tags.
	 *
	 * @return string Replacement.
	 */
	private function replace_field_tag( string $tag, array $parts, array &$problems ): string {
		$custom_id = $this->field_map[ $parts[2] ] ?? null;

		if ( null === $custom_id ) {
			$problems[] = $tag;

			return '';
		}

		return $this->with_modifier_note( '[field id="' . $custom_id . '"]', $tag, $parts[3] ?? '', $problems );
	}

	/**
	 * Records a tag whose modifier (":noadmin", ":value"...) was dropped.
	 *
	 * @param string             $replacement Replacement text.
	 * @param string             $tag         Whole tag.
	 * @param string             $modifier    Modifier, possibly with a leading colon.
	 * @param array<int, string> $problems    Collects problem tags.
	 *
	 * @return string The replacement, unchanged.
	 */
	private function with_modifier_note( string $replacement, string $tag, string $modifier, array &$problems ): string {
		if ( '' !== trim( $modifier, ':' ) ) {
			$problems[] = $tag;
		}

		return $replacement;
	}
}
