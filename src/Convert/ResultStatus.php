<?php
/**
 * How faithfully one Gravity Forms item was carried over.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Converted = same behaviour, Approximated = works but differs, Dropped = not carried over.
 */
enum ResultStatus: string {

	case Converted    = 'converted';
	case Approximated = 'approximated';
	case Dropped      = 'dropped';

	/**
	 * Returns the less faithful of two statuses (Dropped > Approximated > Converted).
	 *
	 * @param ResultStatus $other Other status.
	 *
	 * @return self Worst status.
	 */
	public function worst( ResultStatus $other ): self {
		return $other->severity() > $this->severity() ? $other : $this;
	}

	/**
	 * Ranks the status for comparisons.
	 *
	 * @return int 0 = converted, 1 = approximated, 2 = dropped.
	 */
	public function severity(): int {
		return match ( $this ) {
			self::Converted    => 0,
			self::Approximated => 1,
			self::Dropped      => 2,
		};
	}

	/**
	 * Returns the translated label shown in the preview and report.
	 *
	 * @return string Label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Converted    => __( 'Converted', 'webofd-gf-to-elementor' ),
			self::Approximated => __( 'Approximated', 'webofd-gf-to-elementor' ),
			self::Dropped      => __( 'Dropped', 'webofd-gf-to-elementor' ),
		};
	}
}
