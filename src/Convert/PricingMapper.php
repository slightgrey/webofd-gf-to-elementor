<?php
/**
 * Maps Gravity Forms pricing fields to plain Elementor inputs.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Product, option, quantity, shipping and total fields keep their choices and
 * labels, but no prices are calculated: Elementor forms have no payments.
 */
final class PricingMapper {

	/**
	 * GF input types mapped to the Elementor type that collects the same answer.
	 */
	private const CHOICE_TYPES = array(
		'select'   => 'select',
		'radio'    => 'radio',
		'checkbox' => 'checkbox',
	);

	/**
	 * Creates the mapper.
	 *
	 * @param IdGenerator $ids   custom_id generator.
	 * @param ItemFactory $items Repeater item factory.
	 */
	public function __construct(
		private readonly IdGenerator $ids,
		private readonly ItemFactory $items
	) {
	}

	/**
	 * Maps one pricing field.
	 *
	 * @param array<string, mixed> $field GF product, option, quantity, shipping or total field.
	 *
	 * @return Mapped Result.
	 */
	public function map( array $field ): Mapped {
		if ( 'total' === ( $field['type'] ?? '' ) ) {
			return Mapped::dropped( __( 'Totals need price calculation, which Elementor forms do not do.', 'webofd-gf-to-elementor' ) );
		}

		return $this->map_input( $field )->with_note( $this->price_note( $field ) );
	}

	/**
	 * Picks the Elementor input for a pricing field.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return Mapped Result.
	 */
	private function map_input( array $field ): Mapped {
		$input_type = (string) ( $field['inputType'] ?? '' );
		$id         = $this->ids->for_field( (string) $field['id'], (string) ( $field['label'] ?? '' ) );

		foreach ( (array) ( $field['inputs'] ?? array() ) as $input ) {
			$this->ids->alias( (string) ( $input['id'] ?? '' ), $id );
		}

		if ( isset( self::CHOICE_TYPES[ $input_type ] ) ) {
			return $this->choice_item( $field, self::CHOICE_TYPES[ $input_type ], $id );
		}

		if ( in_array( $input_type, array( 'singleshipping', 'hiddenproduct' ), true ) || ! empty( $field['disableQuantity'] ) ) {
			return $this->fixed_item( $field, $id );
		}

		return new Mapped( array( $this->items->make( 'number', $id, $field, array( 'field_min' => 0 ) ) ) );
	}

	/**
	 * Builds a select/radio/checkbox item whose labels show the prices.
	 *
	 * @param array<string, mixed> $field GF field.
	 * @param string               $type  Elementor type.
	 * @param string               $id    custom_id.
	 *
	 * @return Mapped Result.
	 */
	private function choice_item( array $field, string $type, string $id ): Mapped {
		$item = $this->items->make( $type, $id, $field, array( 'field_options' => Choices::options( $field['choices'] ?? array(), 'price' ) ) );

		return new Mapped( array( $item ) );
	}

	/**
	 * Builds a hidden item recording the fixed product or shipping line.
	 *
	 * @param array<string, mixed> $field GF field.
	 * @param string               $id    custom_id.
	 *
	 * @return Mapped Result.
	 */
	private function fixed_item( array $field, string $id ): Mapped {
		$value = trim( ItemFactory::plain( (string) ( $field['label'] ?? '' ) ) . ' ' . (string) ( $field['basePrice'] ?? '' ) );

		return new Mapped( array( $this->items->make( 'hidden', $id, $field, array( 'field_value' => $value ) ) ) );
	}

	/**
	 * Builds the "no prices" note, naming the base price when there is one.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return string Note.
	 */
	private function price_note( array $field ): string {
		$price = trim( (string) ( $field['basePrice'] ?? '' ) );

		if ( '' === $price ) {
			return __( 'Pricing field kept as a plain input; no prices are calculated or charged.', 'webofd-gf-to-elementor' );
		}

		/* translators: %s: price, e.g. "$50.00". */
		return sprintf( __( 'Pricing field kept as a plain input; the %s price is not calculated or charged.', 'webofd-gf-to-elementor' ), $price );
	}
}
