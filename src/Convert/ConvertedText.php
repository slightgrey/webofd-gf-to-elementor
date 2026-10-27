<?php
/**
 * A text after merge tag conversion.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * The converted text plus the GF tags that were removed or lost a modifier.
 */
final class ConvertedText {

	/**
	 * Creates the result.
	 *
	 * @param string             $text     Converted text.
	 * @param array<int, string> $problems GF tags removed or changed, e.g. "{entry_id}".
	 */
	public function __construct(
		public readonly string $text,
		public readonly array $problems = array()
	) {
	}

	/**
	 * Tells whether any tag could not be converted exactly.
	 *
	 * @return bool True if there were problems.
	 */
	public function has_problems(): bool {
		return array() !== $this->problems;
	}
}
