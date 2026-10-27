<?php
/**
 * Replaces one form's Gravity Forms shortcodes in a text.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

/**
 * Uses WordPress's own shortcode regex, so escaped shortcodes ([[gravityform id="1"]]),
 * other forms and non-form actions ("conditional") are left exactly as they are.
 */
final class ShortcodeReplacer {

	/**
	 * Shortcode tags Gravity Forms registers.
	 */
	private const TAGS = array( 'gravityform', 'gravityforms' );

	/**
	 * Builds the Elementor template shortcode that replaces a form.
	 *
	 * @param int $template_id elementor_library post ID.
	 *
	 * @return string Shortcode.
	 */
	public static function template_shortcode( int $template_id ): string {
		return '[elementor-template id="' . $template_id . '"]';
	}

	/**
	 * Replaces every form-rendering shortcode of one form.
	 *
	 * @param string $text        Text.
	 * @param int    $form_id     Form ID to replace.
	 * @param string $replacement Replacement text.
	 * @param int    $count       Set to the number of shortcodes replaced.
	 *
	 * @return string Text with the shortcodes replaced.
	 */
	public function replace( string $text, int $form_id, string $replacement, int &$count = 0 ): string {
		$count = 0;

		if ( false === stripos( $text, '[gravityform' ) ) {
			return $text;
		}

		$result = preg_replace_callback(
			'/' . get_shortcode_regex( self::TAGS ) . '/i',
			function ( array $shortcode ) use ( $form_id, $replacement, &$count ): string {
				if ( ! $this->is_form_shortcode( $shortcode, $form_id ) ) {
					return $shortcode[0];
				}

				++$count;

				return $replacement;
			},
			$text
		);

		return is_string( $result ) ? $result : $text;
	}

	/**
	 * Tells whether a regex match is an unescaped, form-rendering shortcode of the form.
	 *
	 * @param array<int, string> $shortcode Regex groups from get_shortcode_regex().
	 * @param int                $form_id   Form ID.
	 *
	 * @return bool True if it should be replaced.
	 */
	private function is_form_shortcode( array $shortcode, int $form_id ): bool {
		if ( '[' === $shortcode[1] && ']' === $shortcode[6] ) {
			return false;
		}

		$attributes = array_change_key_case( (array) shortcode_parse_atts( $shortcode[3] ), CASE_LOWER );
		$action     = strtolower( trim( (string) ( $attributes['action'] ?? '' ) ) );
		$id         = trim( (string) ( $attributes['id'] ?? '' ) );

		return in_array( $action, array( '', 'form' ), true ) && (string) $form_id === $id;
	}
}
