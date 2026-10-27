<?php
/**
 * Task 5.6: list, pricing and unsupported fields, conditional logic and shared notes.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ConditionalLogicDescriber;
use GFToElementor\Convert\ResultStatus;

/**
 * Fields without an Elementor equivalent are approximated or dropped, always with a reason.
 */
final class FieldMapperRemainingTest extends FieldMapperTestCase {

	/**
	 * A list field becomes a text area.
	 *
	 * @return void
	 */
	public function test_list_becomes_textarea(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 24 ) );

		$this->assertSame( 'textarea', $result->items[0]['field_type'] );
		$this->assertSame( ResultStatus::Approximated, $this->entry( $result )->status );
	}

	/**
	 * A single product becomes a quantity number; the price is named in the reason.
	 *
	 * @return void
	 */
	public function test_single_product(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 25 ) );

		$this->assertSame( 'number', $result->items[0]['field_type'] );
		$this->assertSame( 0, $result->items[0]['field_min'] );
		$this->assertStringContainsString( '$50.00', $this->entry( $result )->reason );
		$this->assertSame( 'inspection', $this->ids->map()['25.3'] );
	}

	/**
	 * Drop-down products and checkbox options show prices in their labels.
	 *
	 * @return void
	 */
	public function test_priced_choices(): void {
		$product = $this->map_one( $this->field( 'kitchen-sink', 26 ) )->items[0];
		$option  = $this->map_one( $this->field( 'kitchen-sink', 28 ) )->items[0];

		$this->assertSame( 'select', $product['field_type'] );
		$this->assertSame( "Basic (\$10.00)|Basic\nPremium (\$25.00)|Premium", $product['field_options'] );
		$this->assertSame( 'checkbox', $option['field_type'] );
		$this->assertSame( 'Photos ($5.00)|Photos', $option['field_options'] );
	}

	/**
	 * Quantity becomes a number, a single shipping line a hidden value, and a total is dropped.
	 *
	 * @return void
	 */
	public function test_quantity_shipping_total(): void {
		$quantity = $this->map_one( $this->field( 'kitchen-sink', 27 ) );
		$shipping = $this->map_one( $this->field( 'kitchen-sink', 29 ) );
		$total    = $this->map_one( $this->field( 'kitchen-sink', 30 ) );

		$this->assertSame( 'number', $quantity->items[0]['field_type'] );
		$this->assertSame( 'hidden', $shipping->items[0]['field_type'] );
		$this->assertSame( 'Shipping $7.00', $shipping->items[0]['field_value'] );
		$this->assertSame( array(), $total->items );
		$this->assertSame( ResultStatus::Dropped, $this->entry( $total )->status );
	}

	/**
	 * An unknown type becomes a text field; a signature is dropped.
	 *
	 * @return void
	 */
	public function test_unsupported_types(): void {
		$post_title = $this->map_one( $this->field( 'kitchen-sink', 32 ) );
		$signature  = $this->map_one( $this->field( 'kitchen-sink', 33 ) );

		$this->assertSame( 'text', $post_title->items[0]['field_type'] );
		$this->assertStringContainsString( 'Unsupported "post_title" field', $this->entry( $post_title )->reason );
		$this->assertSame( array(), $signature->items );
		$this->assertSame( ResultStatus::Dropped, $this->entry( $signature )->status );
	}

	/**
	 * A field with a usable inputType (survey radio) is converted as that type.
	 *
	 * @return void
	 */
	public function test_input_type_fallback(): void {
		$result = $this->map_one(
			array(
				'type'      => 'survey',
				'inputType' => 'radio',
				'id'        => 40,
				'label'     => 'Rate us',
				'choices'   => array( array( 'text' => 'Good', 'value' => 'g' ) ),
			)
		);

		$this->assertSame( 'radio', $result->items[0]['field_type'] );
		$this->assertStringContainsString( 'converted as a plain "radio" field', $this->entry( $result )->reason );
	}

	/**
	 * Conditional logic is described in words, with "any" joined by "or".
	 *
	 * @return void
	 */
	public function test_logic_entry(): void {
		$form   = $this->form( 'kitchen-sink' );
		$result = $this->mapper()->map_field( $this->field( 'kitchen-sink', 6 ), $form['fields'] );
		$logic  = $this->entry( $result, 'logic' );

		$this->assertSame( ResultStatus::Dropped, $logic->status );
		$this->assertSame( 'Conditional logic dropped: Show if "Topic" is "sales" or "Phone" is not empty.', $logic->reason );
	}

	/**
	 * Sub-input rules name the input, and "all" rules are joined by "and".
	 *
	 * @return void
	 */
	public function test_describer_sub_inputs_and_operators(): void {
		$fields = $this->form( 'kitchen-sink' )['fields'];
		$logic  = array(
			'actionType' => 'hide',
			'logicType'  => 'all',
			'rules'      => array(
				array( 'fieldId' => '1.3', 'operator' => 'contains', 'value' => 'Jo' ),
				array( 'fieldId' => '12', 'operator' => '>', 'value' => '3' ),
				array( 'fieldId' => '999', 'operator' => 'is', 'value' => 'x' ),
			),
		);

		$this->assertSame(
			'Hide if "Your Name (First)" contains "Jo" and "Bedrooms" is greater than "3" and "field #999" is "x"',
			( new ConditionalLogicDescriber() )->describe( $logic, $fields )
		);
	}

	/**
	 * An admin-only field becomes hidden; a dynamic default value is left empty and reported.
	 *
	 * @return void
	 */
	public function test_admin_only_and_dynamic_default(): void {
		$result = $this->map_one(
			array(
				'type'         => 'text',
				'id'           => 41,
				'label'        => 'Agent',
				'visibility'   => 'administrative',
				'defaultValue' => '{user:display_name}',
			)
		);
		$entry  = $this->entry( $result );

		$this->assertSame( 'hidden', $result->items[0]['field_type'] );
		$this->assertArrayNotHasKey( 'field_value', $result->items[0] );
		$this->assertStringContainsString( 'Admin-only field became a hidden field', $entry->reason );
		$this->assertStringContainsString( '{user:display_name}', $entry->reason );
	}
}
