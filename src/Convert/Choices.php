<?php
/**
 * Converts Gravity Forms choices to Elementor option lines.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Elementor stores options as one "label|value" (or just "label") per line.
 */
final class Choices {

	/**
	 * Builds the field_options text for a list of GF choices.
	 *
	 * @param mixed  $choices     GF choices (an array, or "" on fields without choices).
	 * @param string $label_extra "price" appends each choice's price to its label, e.g. "Basic ($10.00)".
	 *
	 * @return string Option lines.
	 */
	public static function options( mixed $choices, string $label_extra = '' ): string {
		$lines = array();

		foreach ( self::as_list( $choices ) as $choice ) {
			$lines[] = self::line( $choice, $label_extra );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Returns the values of the choices marked as selected by default.
	 *
	 * @param mixed $choices GF choices.
	 *
	 * @return array<int, string> Selected values.
	 */
	public static function selected_values( mixed $choices ): array {
		$selected = array();

		foreach ( self::as_list( $choices ) as $choice ) {
			if ( ! empty( $choice['isSelected'] ) ) {
				$selected[] = self::value_of( $choice );
			}
		}

		return $selected;
	}

	/**
	 * Returns the choices as a list of arrays, whatever GF stored.
	 *
	 * @param mixed $choices GF choices.
	 *
	 * @return array<int, array<string, mixed>> Choices.
	 */
	public static function as_list( mixed $choices ): array {
		if ( ! is_array( $choices ) ) {
			return array();
		}

		return array_values( array_filter( $choices, 'is_array' ) );
	}

	/**
	 * Builds one option line.
	 *
	 * @param array<string, mixed> $choice      GF choice.
	 * @param string               $label_extra "price" to append the price to the label.
	 *
	 * @return string "label|value", or "label" when they are equal.
	 */
	private static function line( array $choice, string $label_extra ): string {
		$label = self::clean( (string) ( $choice['text'] ?? '' ) );
		$value = self::clean( self::value_of( $choice ) );

		if ( 'price' === $label_extra && '' !== (string) ( $choice['price'] ?? '' ) ) {
			$label .= ' (' . self::clean( (string) $choice['price'] ) . ')';
		}

		return $label === $value ? $label : $label . '|' . $value;
	}

	/**
	 * Returns a choice's submitted value (GF falls back to the text when value is empty).
	 *
	 * @param array<string, mixed> $choice GF choice.
	 *
	 * @return string Value.
	 */
	private static function value_of( array $choice ): string {
		$value = (string) ( $choice['value'] ?? '' );

		return '' === $value ? (string) ( $choice['text'] ?? '' ) : $value;
	}

	/**
	 * Removes characters that would break Elementor's option format (line breaks and "|").
	 *
	 * @param string $text Label or value.
	 *
	 * @return string Safe text.
	 */
	private static function clean( string $text ): string {
		return trim( str_replace( array( "\r\n", "\r", "\n", '|' ), array( ' ', ' ', ' ', '/' ), $text ) );
	}
}
