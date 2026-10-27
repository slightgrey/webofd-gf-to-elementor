<?php
/**
 * Task 5.5: name and address fields.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ResultStatus;

/**
 * Composite GF fields split into one Elementor field per visible input.
 */
final class FieldMapperCompositeTest extends FieldMapperTestCase {

	/**
	 * Form #4's advanced name field: prefix (select), first, last; middle and suffix are hidden.
	 *
	 * @return void
	 */
	public function test_real_name_field(): void {
		$result = $this->map_one( $this->field( 'form-4', 11 ) );
		$items  = $result->items;

		$this->assertSame( array( 'your_name_prefix', 'your_name_first', 'your_name_last' ), array_column( $items, 'custom_id' ) );
		$this->assertSame( array( 'select', 'text', 'text' ), array_column( $items, 'field_type' ) );
		$this->assertSame( array( 'Prefix', 'First', 'Last' ), array_column( $items, 'field_label' ) );
		$this->assertSame( array( '33', '33', '33' ), array_column( $items, 'width' ) );
		$this->assertSame( array( '', 'true', 'true' ), array_column( $items, 'required' ) );
		$this->assertStringStartsWith( "|\nDr.\nMiss\nMr.", $items[0]['field_options'] );
		$this->assertSame( ResultStatus::Approximated, $this->entry( $result )->status );
		$this->assertStringContainsString( 'split into 3 separate fields: Prefix, First, Last', $this->entry( $result )->reason );
	}

	/**
	 * Sub-input merge tag IDs map to the new fields; hidden inputs have no mapping.
	 *
	 * @return void
	 */
	public function test_name_input_map(): void {
		$this->map_one( $this->field( 'form-4', 11 ) );
		$map = $this->ids->map();

		$this->assertSame( 'your_name_first', $map['11.3'] );
		$this->assertSame( 'your_name_last', $map['11.6'] );
		$this->assertArrayNotHasKey( '11.4', $map );
	}

	/**
	 * Form #2's international address: six fields, streets full width, the rest in pairs, country defaulted.
	 *
	 * @return void
	 */
	public function test_real_address_field(): void {
		$items = $this->map_one( $this->field( 'form-2', 2 ) )->items;

		$this->assertSame(
			array( 'address_street', 'address_street2', 'address_city', 'address_state', 'address_zip', 'address_country' ),
			array_column( $items, 'custom_id' )
		);
		$this->assertSame( array( '100', '100', '50', '50', '50', '50' ), array_column( $items, 'width' ) );
		$this->assertSame( 'select', $items[5]['field_type'] );
		$this->assertSame( 'Australia', $items[5]['field_value'] );
		$this->assertStringContainsString( "\nAustralia\n", $items[5]['field_options'] );
	}

	/**
	 * A required address makes every input required except address line 2; hidden inputs are skipped.
	 *
	 * @return void
	 */
	public function test_required_address_skips_line_two_and_hidden(): void {
		$field               = $this->field( 'kitchen-sink', 8 );
		$field['isRequired'] = true;
		$items               = $this->map_one( $field )->items;

		$this->assertCount( 5, $items );
		$this->assertNotContains( 'address_street2', array_column( $items, 'custom_id' ) );
		$this->assertSame( array( 'true', 'true', 'true', 'true', 'true' ), array_column( $items, 'required' ) );
	}

	/**
	 * A US address drops the country input and reports the state list change.
	 *
	 * @return void
	 */
	public function test_us_address_has_no_country(): void {
		$field                = $this->field( 'kitchen-sink', 8 );
		$field['addressType'] = 'us';
		$result               = $this->map_one( $field );

		$this->assertNotContains( 'address_country', array_column( $result->items, 'custom_id' ) );
		$this->assertStringContainsString( 'state/province list became a text field', $this->entry( $result )->reason );
	}

	/**
	 * A simple-format name stays one text field.
	 *
	 * @return void
	 */
	public function test_simple_name_is_one_field(): void {
		$field               = $this->field( 'form-4', 11 );
		$field['nameFormat'] = 'simple';
		$result              = $this->map_one( $field );

		$this->assertCount( 1, $result->items );
		$this->assertSame( ResultStatus::Converted, $this->entry( $result )->status );
	}
}
