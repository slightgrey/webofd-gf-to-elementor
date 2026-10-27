<?php
/**
 * Form widget settings produced from Gravity Forms notifications or confirmations.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Immutable settings, the submit actions they need, and report entries.
 */
final class SettingsResult {

	/**
	 * Creates the result.
	 *
	 * @param array<string, mixed>    $settings Form widget settings.
	 * @param array<int, string>      $actions  Submit actions, e.g. "email", "email2", "redirect".
	 * @param array<int, ReportEntry> $entries  Report entries.
	 */
	public function __construct(
		public readonly array $settings = array(),
		public readonly array $actions = array(),
		public readonly array $entries = array()
	) {
	}
}
