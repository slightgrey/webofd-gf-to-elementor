<?php
/**
 * One line of a conversion report.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Immutable record of what happened to one Gravity Forms item.
 */
final class ReportEntry {

	/**
	 * Creates an entry.
	 *
	 * @param string       $subject       What kind of item: field, notification, confirmation, form or feed.
	 * @param string       $gf_ref        The Gravity Forms item, e.g. "Name (#1)".
	 * @param ResultStatus $status        How faithfully it was carried over.
	 * @param string       $reason        Why, in plain words. Empty for a plain conversion.
	 * @param string       $elementor_ref What it became in Elementor, e.g. "text: name_first".
	 */
	public function __construct(
		public readonly string $subject,
		public readonly string $gf_ref,
		public readonly ResultStatus $status,
		public readonly string $reason = '',
		public readonly string $elementor_ref = ''
	) {
	}

	/**
	 * Converts the entry to a plain array for JSON storage.
	 *
	 * @return array{subject: string, gf_ref: string, status: string, reason: string, elementor_ref: string} Entry data.
	 */
	public function to_array(): array {
		return array(
			'subject'       => $this->subject,
			'gf_ref'        => $this->gf_ref,
			'status'        => $this->status->value,
			'reason'        => $this->reason,
			'elementor_ref' => $this->elementor_ref,
		);
	}

	/**
	 * Rebuilds an entry from stored data. Unknown statuses become Dropped.
	 *
	 * @param array<string, mixed> $data Data from to_array().
	 *
	 * @return self Entry.
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['subject'] ?? '' ),
			(string) ( $data['gf_ref'] ?? '' ),
			ResultStatus::tryFrom( (string) ( $data['status'] ?? '' ) ) ?? ResultStatus::Dropped,
			(string) ( $data['reason'] ?? '' ),
			(string) ( $data['elementor_ref'] ?? '' )
		);
	}
}
