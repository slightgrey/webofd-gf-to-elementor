<?php
/**
 * Splits Gravity Forms name and address fields into separate Elementor fields.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * GF name and address fields hold several inputs; Elementor has one input per field,
 * so each visible input becomes its own field.
 */
final class CompositeMapper {

	/**
	 * Name input number => suffix.
	 */
	private const NAME_INPUTS = array(
		'2' => 'prefix',
		'3' => 'first',
		'4' => 'middle',
		'6' => 'last',
		'8' => 'suffix',
	);

	/**
	 * Name inputs GF makes required when the field is required.
	 */
	private const NAME_REQUIRED = array( 'first', 'last' );

	/**
	 * Address input number => suffix.
	 */
	private const ADDRESS_INPUTS = array(
		'1' => 'street',
		'2' => 'street2',
		'3' => 'city',
		'4' => 'state',
		'5' => 'zip',
		'6' => 'country',
	);

	/**
	 * Address inputs GF shows full width; the others share a row in pairs.
	 */
	private const ADDRESS_FULL_WIDTH = array( 'street', 'street2' );

	/**
	 * Creates the mapper.
	 *
	 * @param IdGenerator           $ids       custom_id generator.
	 * @param ItemFactory           $items     Repeater item factory.
	 * @param array<string, string> $countries Country code => name.
	 */
	public function __construct(
		private readonly IdGenerator $ids,
		private readonly ItemFactory $items,
		private readonly array $countries
	) {
	}

	/**
	 * Maps a name field.
	 *
	 * @param array<string, mixed> $field GF name field.
	 *
	 * @return Mapped Result.
	 */
	public function name( array $field ): Mapped {
		$inputs = $this->visible_inputs( $field, self::NAME_INPUTS );

		if ( 'simple' === ( $field['nameFormat'] ?? '' ) || array() === $inputs ) {
			$id = $this->ids->for_field( (string) $field['id'], (string) ( $field['label'] ?? '' ) );

			return new Mapped( array( $this->items->make( 'text', $id, $field ) ) );
		}

		$width = Widths::nearest( Widths::percent_of_span( $field['layoutGridColumnSpan'] ?? '' ) / count( $inputs ) );
		$items = array();

		foreach ( $inputs as $suffix => $input ) {
			$required = ! empty( $field['isRequired'] ) && in_array( $suffix, self::NAME_REQUIRED, true );
			$items[]  = 'prefix' === $suffix
				? $this->select_item( $field, $input, $suffix, $width, $required, Choices::options( $input['choices'] ?? array() ) )
				: $this->text_item( $field, $input, $suffix, $width, $required );
		}

		return $this->split_result( $items, __( 'Name field', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps an address field.
	 *
	 * @param array<string, mixed> $field GF address field.
	 *
	 * @return Mapped Result.
	 */
	public function address( array $field ): Mapped {
		$international = 'international' === ( $field['addressType'] ?? 'international' );
		$inputs        = $this->visible_inputs( $field, self::ADDRESS_INPUTS );

		if ( ! $international ) {
			unset( $inputs['country'] );
		}

		$items = array();

		foreach ( $inputs as $suffix => $input ) {
			$items[] = $this->address_item( $field, $input, $suffix );
		}

		$result = $this->split_result( $items, __( 'Address field', 'webofd-gf-to-elementor' ) );

		return $international ? $result : $result->with_note( __( 'The state/province list became a text field, and the country is not asked.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Builds one address input's item.
	 *
	 * @param array<string, mixed> $field  GF address field.
	 * @param array<string, mixed> $input  GF input.
	 * @param string               $suffix Input suffix.
	 *
	 * @return array<string, mixed> Repeater item.
	 */
	private function address_item( array $field, array $input, string $suffix ): array {
		$percent  = Widths::percent_of_span( $field['layoutGridColumnSpan'] ?? '' );
		$width    = Widths::nearest( in_array( $suffix, self::ADDRESS_FULL_WIDTH, true ) ? $percent : $percent / 2 );
		$required = ! empty( $field['isRequired'] ) && 'street2' !== $suffix;

		if ( 'country' !== $suffix ) {
			return $this->text_item( $field, $input, $suffix, $width, $required );
		}

		$item = $this->select_item( $field, $input, $suffix, $width, $required, $this->country_options() );

		return array_merge( $item, array_filter( array( 'field_value' => $this->default_country( (string) ( $field['defaultCountry'] ?? '' ) ) ) ) );
	}

	/**
	 * Returns the visible inputs of a multi-input field, keyed by suffix.
	 *
	 * @param array<string, mixed> $field    GF field.
	 * @param array<int, string>   $suffixes Input number => suffix.
	 *
	 * @return array<string, array<string, mixed>> Suffix => input.
	 */
	private function visible_inputs( array $field, array $suffixes ): array {
		$visible = array();

		foreach ( (array) ( $field['inputs'] ?? array() ) as $input ) {
			$number = substr( (string) strrchr( (string) ( $input['id'] ?? '' ), '.' ), 1 );

			if ( isset( $suffixes[ $number ] ) && empty( $input['isHidden'] ) ) {
				$visible[ $suffixes[ $number ] ] = $input;
			}
		}

		return $visible;
	}

	/**
	 * Builds a text item for one input.
	 *
	 * @param array<string, mixed> $field    Parent GF field.
	 * @param array<string, mixed> $input    GF input.
	 * @param string               $suffix   Input suffix.
	 * @param string               $width    Elementor width.
	 * @param bool                 $required Whether the input is required.
	 *
	 * @return array<string, mixed> Repeater item.
	 */
	private function text_item( array $field, array $input, string $suffix, string $width, bool $required ): array {
		return $this->items->make( 'text', $this->input_id( $field, $input, $suffix ), $this->input_as_field( $field, $input ), $this->input_overrides( $width, $required ) );
	}

	/**
	 * Builds a select item for one input.
	 *
	 * @param array<string, mixed> $field    Parent GF field.
	 * @param array<string, mixed> $input    GF input.
	 * @param string               $suffix   Input suffix.
	 * @param string               $width    Elementor width.
	 * @param bool                 $required Whether the input is required.
	 * @param string               $options  Option lines.
	 *
	 * @return array<string, mixed> Repeater item.
	 */
	private function select_item( array $field, array $input, string $suffix, string $width, bool $required, string $options ): array {
		$overrides                  = $this->input_overrides( $width, $required );
		$overrides['field_options'] = "|\n" . $options;

		return $this->items->make( 'select', $this->input_id( $field, $input, $suffix ), $this->input_as_field( $field, $input ), $overrides );
	}

	/**
	 * Builds the width/required overrides shared by every input item.
	 *
	 * @param string $width    Elementor width.
	 * @param bool   $required Whether the input is required.
	 *
	 * @return array<string, string> Overrides.
	 */
	private function input_overrides( string $width, bool $required ): array {
		return array(
			'width'    => $width,
			'required' => $required ? 'true' : '',
		);
	}

	/**
	 * Assigns the custom_id for one input.
	 *
	 * @param array<string, mixed> $field  Parent GF field.
	 * @param array<string, mixed> $input  GF input.
	 * @param string               $suffix Input suffix.
	 *
	 * @return string custom_id.
	 */
	private function input_id( array $field, array $input, string $suffix ): string {
		return $this->ids->for_input( (string) ( $input['id'] ?? '' ), (string) ( $field['label'] ?? '' ), $suffix );
	}

	/**
	 * Turns an input into a field-like array the ItemFactory understands.
	 *
	 * @param array<string, mixed> $field Parent GF field.
	 * @param array<string, mixed> $input GF input.
	 *
	 * @return array<string, mixed> Field-like array.
	 */
	private function input_as_field( array $field, array $input ): array {
		return array(
			'label'        => (string) ( $input['customLabel'] ?? $input['label'] ?? '' ),
			'placeholder'  => (string) ( $input['placeholder'] ?? '' ),
			'defaultValue' => (string) ( $input['defaultValue'] ?? '' ),
			'cssClass'     => (string) ( $field['cssClass'] ?? '' ),
		);
	}

	/**
	 * Builds the Approximated result for a split field.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param string                           $what  "Name field" or "Address field".
	 *
	 * @return Mapped Result.
	 */
	private function split_result( array $items, string $what ): Mapped {
		$labels = implode( ', ', array_map( static fn( array $item ): string => (string) $item['field_label'], $items ) );

		/* translators: 1: "Name field" or "Address field", 2: number of fields, 3: comma-separated field labels. */
		return ( new Mapped( $items ) )->with_note( sprintf( __( '%1$s split into %2$d separate fields: %3$s.', 'webofd-gf-to-elementor' ), $what, count( $items ), $labels ) );
	}

	/**
	 * Returns the country option lines (names only, as GF submits names).
	 *
	 * @return string Option lines.
	 */
	private function country_options(): string {
		return implode( "\n", array_values( $this->countries ) );
	}

	/**
	 * Resolves GF's default country (stored as a code or a name) to a country name.
	 *
	 * @param string $default_country Code like "AU" or name like "Australia".
	 *
	 * @return string Country name, or "" if unknown.
	 */
	private function default_country( string $default_country ): string {
		if ( isset( $this->countries[ strtoupper( $default_country ) ] ) ) {
			return $this->countries[ strtoupper( $default_country ) ];
		}

		return in_array( $default_country, $this->countries, true ) ? $default_country : '';
	}
}
