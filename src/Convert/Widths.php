<?php
/**
 * Converts Gravity Forms column spans to Elementor field widths.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * GF lays fields out on a 12-column grid; Elementor offers a fixed list of percentage widths.
 */
final class Widths {

	/**
	 * Widths offered by Elementor's field "Column Width" control.
	 */
	public const ALLOWED = array( 100, 80, 75, 70, 66, 60, 50, 40, 33, 30, 25, 20 );

	/**
	 * Returns the Elementor width for a GF layoutGridColumnSpan value.
	 *
	 * @param mixed $span Column span (1-12). Empty or invalid means full width.
	 *
	 * @return string Elementor width, e.g. "50".
	 */
	public static function from_span( mixed $span ): string {
		return self::nearest( self::percent_of_span( $span ) );
	}

	/**
	 * Returns the share of the row a GF column span takes.
	 *
	 * @param mixed $span Column span (1-12).
	 *
	 * @return float Percentage, 100 for empty or invalid spans.
	 */
	public static function percent_of_span( mixed $span ): float {
		$columns = is_numeric( $span ) ? (int) $span : 12;

		if ( $columns < 1 || $columns > 12 ) {
			$columns = 12;
		}

		return $columns / 12 * 100;
	}

	/**
	 * Returns the allowed Elementor width closest to a percentage.
	 *
	 * Ties go to the wider option, so fields never wrap earlier than in GF.
	 *
	 * @param float $percent Desired width.
	 *
	 * @return string Elementor width.
	 */
	public static function nearest( float $percent ): string {
		$best = self::ALLOWED[0];

		foreach ( self::ALLOWED as $width ) {
			if ( abs( $width - $percent ) < abs( $best - $percent ) ) {
				$best = $width;
			}
		}

		return (string) $best;
	}
}
