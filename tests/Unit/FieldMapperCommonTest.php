<?php
/**
 * Task 5.2: shared field properties and widths.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ResultStatus;
use GFToElementor\Convert\Widths;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Label, required, width, placeholder, default value and CSS class carry over to every item.
 */
final class FieldMapperCommonTest extends FieldMapperTestCase {

	/**
	 * Every shared property maps onto the repeater item.
	 *
	 * @return void
	 */
	public function test_common_properties(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 34 ) );

		$this->assertSame(
			array(
				'_id'         => 'r1',
				'custom_id'   => 'company',
				'field_type'  => 'text',
				'field_label' => 'Company',
				'width'       => '33',
				'required'    => '',
				'placeholder' => 'Acme Pty Ltd',
				'field_value' => 'Acme',
				'css_classes' => 'company wide',
			),
			$result->items[0]
		);
	}

	/**
	 * The 60-character limit is reported, so the field is Approximated.
	 *
	 * @return void
	 */
	public function test_max_length_is_reported(): void {
		$entry = $this->entry( $this->map_one( $this->field( 'kitchen-sink', 34 ) ) );

		$this->assertSame( ResultStatus::Approximated, $entry->status );
		$this->assertStringContainsString( '60-character limit', $entry->reason );
	}

	/**
	 * A required field is marked required with Elementor's "true" switcher value.
	 *
	 * @return void
	 */
	public function test_required_uses_true_string(): void {
		$result = $this->map_one( $this->field( 'form-1', 2 ) );

		$this->assertSame( 'true', $result->items[0]['required'] );
	}

	/**
	 * A description is reported as not shown.
	 *
	 * @return void
	 */
	public function test_description_is_reported(): void {
		$entry = $this->entry( $this->map_one( $this->field( 'form-3', 18 ) ) );

		$this->assertSame( ResultStatus::Approximated, $entry->status );
		$this->assertStringContainsString( 'example: 16 New Street', $entry->reason );
	}

	/**
	 * Supplies GF column spans and the expected Elementor widths.
	 *
	 * @return array<string, array{mixed, string}> Cases.
	 */
	public static function spans(): array {
		return array(
			'12'      => array( 12, '100' ),
			'11'      => array( 11, '100' ),
			'10'      => array( 10, '80' ),
			'9'       => array( 9, '75' ),
			'8'       => array( 8, '66' ),
			'7'       => array( 7, '60' ),
			'6'       => array( 6, '50' ),
			'5'       => array( 5, '40' ),
			'4'       => array( 4, '33' ),
			'3'       => array( 3, '25' ),
			'2'       => array( 2, '20' ),
			'1'       => array( 1, '20' ),
			'empty'   => array( '', '100' ),
			'string6' => array( '6', '50' ),
			'invalid' => array( 99, '100' ),
		);
	}

	/**
	 * Each span maps to the nearest Elementor width.
	 *
	 * @param mixed  $span     GF layoutGridColumnSpan.
	 * @param string $expected Elementor width.
	 *
	 * @return void
	 */
	#[DataProvider( 'spans' )]
	public function test_span_to_width( mixed $span, string $expected ): void {
		$this->assertSame( $expected, Widths::from_span( $span ) );
	}
}
