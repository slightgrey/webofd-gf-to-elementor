<?php
/**
 * Task 5.3: simple field types.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ResultStatus;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * One-input GF fields map onto one Elementor field of the matching type.
 */
final class FieldMapperSimpleTest extends FieldMapperTestCase {

	/**
	 * Supplies kitchen-sink field IDs with the expected Elementor type and status.
	 *
	 * @return array<string, array{int, string, ResultStatus}> Cases.
	 */
	public static function types(): array {
		return array(
			'email + confirm'  => array( 2, 'email', ResultStatus::Approximated ),
			'phone'            => array( 3, 'tel', ResultStatus::Converted ),
			'website'          => array( 4, 'url', ResultStatus::Converted ),
			'select'           => array( 5, 'select', ResultStatus::Converted ),
			'textarea + desc'  => array( 6, 'textarea', ResultStatus::Approximated ),
			'multiselect'      => array( 9, 'select', ResultStatus::Converted ),
			'radio'            => array( 10, 'radio', ResultStatus::Converted ),
			'checkbox'         => array( 11, 'checkbox', ResultStatus::Converted ),
			'number'           => array( 12, 'number', ResultStatus::Converted ),
			'date picker'      => array( 13, 'date', ResultStatus::Converted ),
			'date drop-downs'  => array( 14, 'date', ResultStatus::Approximated ),
			'time'             => array( 15, 'time', ResultStatus::Approximated ),
			'hidden + prefill' => array( 16, 'hidden', ResultStatus::Approximated ),
			'html'             => array( 17, 'html', ResultStatus::Converted ),
			'password'         => array( 31, 'password', ResultStatus::Approximated ),
			'text'             => array( 35, 'text', ResultStatus::Converted ),
		);
	}

	/**
	 * Each GF type becomes the expected Elementor type with the expected status.
	 *
	 * @param int          $field_id GF field ID in the kitchen sink.
	 * @param string       $type     Expected Elementor field type.
	 * @param ResultStatus $status   Expected status.
	 *
	 * @return void
	 */
	#[DataProvider( 'types' )]
	public function test_type_and_status( int $field_id, string $type, ResultStatus $status ): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', $field_id ) );

		$this->assertCount( 1, $result->items );
		$this->assertSame( $type, $result->items[0]['field_type'] );
		$this->assertSame( $status, $this->entry( $result )->status );
	}

	/**
	 * A select's placeholder becomes an empty first option; choice values and the default are kept.
	 *
	 * @return void
	 */
	public function test_select_options_placeholder_and_default(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 5 ) )->items[0];

		$this->assertSame( "Choose a topic|\nSales|sales\nSupport|support", $item['field_options'] );
		$this->assertSame( 'support', $item['field_value'] );
		$this->assertArrayNotHasKey( 'placeholder', $item );
	}

	/**
	 * A multi-select allows several values.
	 *
	 * @return void
	 */
	public function test_multiselect_allows_multiple(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 9 ) )->items[0];

		$this->assertSame( 'true', $item['allow_multiple'] );
		$this->assertSame( "Buying\nRenting\nSelling", $item['field_options'] );
	}

	/**
	 * Radio choices with separate values use label|value lines.
	 *
	 * @return void
	 */
	public function test_radio_values(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 10 ) )->items[0];

		$this->assertSame( "Phone call|phone\nEmail|email", $item['field_options'] );
	}

	/**
	 * Checkbox sub-inputs all point at the one Elementor field, and the pre-checked choice is kept.
	 *
	 * @return void
	 */
	public function test_checkbox_inputs_alias_and_default(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 11 ) )->items[0];

		$this->assertSame( 'Garage', $item['field_value'] );
		$this->assertSame( 'features', $this->ids->map()['11.1'] );
		$this->assertSame( 'features', $this->ids->map()['11.2'] );
	}

	/**
	 * A number field keeps its range and default.
	 *
	 * @return void
	 */
	public function test_number_range(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 12 ) )->items[0];

		$this->assertSame( 1, $item['field_min'] );
		$this->assertSame( 10, $item['field_max'] );
		$this->assertSame( '2', $item['field_value'] );
	}

	/**
	 * An HTML block keeps its content.
	 *
	 * @return void
	 */
	public function test_html_content(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 17 ) )->items[0];

		$this->assertSame( '<p>Please read carefully.</p>', $item['field_html'] );
	}

	/**
	 * A hidden field keeps its value but has no width or required flag.
	 *
	 * @return void
	 */
	public function test_hidden_value_without_layout(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 16 ) )->items[0];

		$this->assertSame( 'web', $item['field_value'] );
		$this->assertArrayNotHasKey( 'width', $item );
		$this->assertArrayNotHasKey( 'required', $item );
	}

	/**
	 * Email confirmation inputs map to the same custom_id.
	 *
	 * @return void
	 */
	public function test_email_confirmation_alias(): void {
		$this->map_one( $this->field( 'kitchen-sink', 2 ) );

		$this->assertSame( 'email', $this->ids->map()['2.2'] );
	}
}
