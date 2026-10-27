<?php
/**
 * Tests for ReportEntry.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ReportEntry;
use GFToElementor\Convert\ResultStatus;
use PHPUnit\Framework\TestCase;

/**
 * Report entries survive a JSON round trip.
 */
final class ReportEntryTest extends TestCase {

	/**
	 * to_array() -> JSON -> from_array() gives an equal entry.
	 *
	 * @return void
	 */
	public function test_json_round_trip(): void {
		$entry = new ReportEntry( 'field', 'Name (#1)', ResultStatus::Approximated, 'Split into 2 fields.', 'text: name_first, name_last' );

		$copy = ReportEntry::from_array( json_decode( (string) json_encode( $entry->to_array() ), true ) );

		$this->assertEquals( $entry, $copy );
	}

	/**
	 * An unknown status is read back as Dropped.
	 *
	 * @return void
	 */
	public function test_unknown_status_becomes_dropped(): void {
		$entry = ReportEntry::from_array( array( 'status' => 'weird' ) );

		$this->assertSame( ResultStatus::Dropped, $entry->status );
	}
}
