<?php
/**
 * The Elementor fields and report entries produced for a whole form.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Immutable accumulation of repeater items, report entries and site requirements.
 */
final class FieldResult {

	/**
	 * Creates a result.
	 *
	 * @param array<int, array<string, mixed>> $items        Elementor form_fields repeater items, in order.
	 * @param array<int, ReportEntry>          $entries      Report entries.
	 * @param array<int, string>               $requirements Site settings needed, e.g. "recaptcha".
	 */
	public function __construct(
		public readonly array $items = array(),
		public readonly array $entries = array(),
		public readonly array $requirements = array()
	) {
	}

	/**
	 * Returns a new result with another result appended.
	 *
	 * @param FieldResult $other Result to append.
	 *
	 * @return self Combined result.
	 */
	public function merge( FieldResult $other ): self {
		return new self(
			array_merge( $this->items, $other->items ),
			array_merge( $this->entries, $other->entries ),
			array_values( array_unique( array_merge( $this->requirements, $other->requirements ) ) )
		);
	}

	/**
	 * Tells whether any item has the given Elementor field type.
	 *
	 * @param string $field_type Elementor field type.
	 *
	 * @return bool True if present.
	 */
	public function has_type( string $field_type ): bool {
		return in_array( $field_type, array_column( $this->items, 'field_type' ), true );
	}
}
