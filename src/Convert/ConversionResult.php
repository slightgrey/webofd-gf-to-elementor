<?php
/**
 * The outcome of converting one Gravity Forms form.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * The Elementor widget, the merge tag field map, the report entries and warnings.
 */
final class ConversionResult {

	/**
	 * Creates the result.
	 *
	 * @param array<string, mixed>    $widget    Elementor form widget element.
	 * @param array<string, string>   $field_map GF input ID => Elementor custom_id.
	 * @param array<int, ReportEntry> $entries   Report entries.
	 * @param array<int, string>      $warnings  Headline warnings for the preview.
	 */
	public function __construct(
		public readonly array $widget,
		public readonly array $field_map,
		public readonly array $entries,
		public readonly array $warnings
	) {
	}

	/**
	 * Counts entries by status.
	 *
	 * @return array<string, int> converted / approximated / dropped => count.
	 */
	public function counts(): array {
		$counts = array_fill_keys( array_map( static fn( ResultStatus $status ): string => $status->value, ResultStatus::cases() ), 0 );

		foreach ( $this->entries as $entry ) {
			++$counts[ $entry->status->value ];
		}

		return $counts;
	}

	/**
	 * Returns the result as plain data for the migration report.
	 *
	 * @return array{entries: array<int, array<string, string>>, warnings: array<int, string>, counts: array<string, int>, widget: array<string, mixed>} Report data.
	 */
	public function to_report(): array {
		return array(
			'entries'  => array_map( static fn( ReportEntry $entry ): array => $entry->to_array(), $this->entries ),
			'warnings' => $this->warnings,
			'counts'   => $this->counts(),
			'widget'   => $this->widget,
		);
	}
}
