<?php
/**
 * The Elementor items produced for one Gravity Forms field.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Immutable per-field mapping result: repeater items, the overall status and the reasons.
 */
final class Mapped {

	/**
	 * Creates a result.
	 *
	 * @param array<int, array<string, mixed>> $items        Elementor form_fields repeater items.
	 * @param ResultStatus                     $status       Overall status.
	 * @param array<int, string>               $notes        Reasons, in plain words.
	 * @param array<int, string>               $requirements Site settings the items need, e.g. "recaptcha".
	 */
	public function __construct(
		public readonly array $items,
		public readonly ResultStatus $status = ResultStatus::Converted,
		public readonly array $notes = array(),
		public readonly array $requirements = array()
	) {
	}

	/**
	 * Returns a dropped result with a reason.
	 *
	 * @param string $note Reason.
	 *
	 * @return self Result with no items.
	 */
	public static function dropped( string $note ): self {
		return new self( array(), ResultStatus::Dropped, array( $note ) );
	}

	/**
	 * Returns a copy with another reason added and the status raised to at least $status.
	 *
	 * @param string       $note   Reason.
	 * @param ResultStatus $status Status this reason implies.
	 *
	 * @return self New result.
	 */
	public function with_note( string $note, ResultStatus $status = ResultStatus::Approximated ): self {
		return new self( $this->items, $this->status->worst( $status ), array_merge( $this->notes, array( $note ) ), $this->requirements );
	}

	/**
	 * Returns a copy whose items were passed through a callback.
	 *
	 * @param callable $callback Receives and returns one item array.
	 *
	 * @return self New result.
	 */
	public function map_items( callable $callback ): self {
		return new self( array_map( $callback, $this->items ), $this->status, $this->notes, $this->requirements );
	}

	/**
	 * Returns a copy with a requirement added.
	 *
	 * @param string $requirement Requirement key.
	 *
	 * @return self New result.
	 */
	public function requiring( string $requirement ): self {
		return new self( $this->items, $this->status, $this->notes, array_merge( $this->requirements, array( $requirement ) ) );
	}
}
