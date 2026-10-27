<?php
/**
 * Writes Gravity Forms conditional logic out in plain words.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Turns a GF conditionalLogic object into a sentence for the report, so the site owner
 * can rebuild the rule by hand: Show if "Topic" is "sales" or "Phone" is not empty.
 */
final class ConditionalLogicDescriber {

	/**
	 * Tells whether a conditionalLogic value holds at least one rule.
	 *
	 * @param mixed $logic GF conditionalLogic value (array, null or "").
	 *
	 * @return bool True if there are rules.
	 */
	public static function has_rules( mixed $logic ): bool {
		return is_array( $logic ) && ! empty( $logic['rules'] ) && is_array( $logic['rules'] );
	}

	/**
	 * Describes a conditionalLogic object.
	 *
	 * @param array<string, mixed>             $logic          GF conditionalLogic (actionType, logicType, rules).
	 * @param array<int, array<string, mixed>> $fields         The form's fields, for label lookups.
	 * @param bool                             $condition_only Start with "Only if" whatever the actionType (notifications, confirmations).
	 *
	 * @return string Sentence such as: Show if "Topic" is "sales" or "Phone" is not empty.
	 */
	public function describe( array $logic, array $fields, bool $condition_only = false ): string {
		$rules = array();

		foreach ( (array) ( $logic['rules'] ?? array() ) as $rule ) {
			if ( is_array( $rule ) ) {
				$rules[] = $this->describe_rule( $rule, $fields );
			}
		}

		$joiner = 'any' === ( $logic['logicType'] ?? 'all' ) ? __( ' or ', 'webofd-gf-to-elementor' ) : __( ' and ', 'webofd-gf-to-elementor' );

		return $this->action_word( $condition_only ? '' : (string) ( $logic['actionType'] ?? '' ) ) . ' ' . implode( $joiner, $rules );
	}

	/**
	 * Returns the leading verb for the action type.
	 *
	 * @param string $action_type GF actionType (show, hide, or empty for notifications).
	 *
	 * @return string "Show if", "Hide if" or "Only if".
	 */
	private function action_word( string $action_type ): string {
		return match ( $action_type ) {
			'show'  => __( 'Show if', 'webofd-gf-to-elementor' ),
			'hide'  => __( 'Hide if', 'webofd-gf-to-elementor' ),
			default => __( 'Only if', 'webofd-gf-to-elementor' ),
		};
	}

	/**
	 * Describes one rule.
	 *
	 * @param array<string, mixed>             $rule   GF rule (fieldId, operator, value).
	 * @param array<int, array<string, mixed>> $fields Form fields.
	 *
	 * @return string E.g. "Topic" is "sales".
	 */
	private function describe_rule( array $rule, array $fields ): string {
		$subject  = '"' . $this->field_label( (string) ( $rule['fieldId'] ?? '' ), $fields ) . '"';
		$operator = (string) ( $rule['operator'] ?? 'is' );
		$value    = (string) ( $rule['value'] ?? '' );

		if ( '' === $value && in_array( $operator, array( 'is', 'isnot' ), true ) ) {
			return $subject . ' ' . ( 'is' === $operator ? __( 'is empty', 'webofd-gf-to-elementor' ) : __( 'is not empty', 'webofd-gf-to-elementor' ) );
		}

		return $subject . ' ' . $this->operator_words( $operator ) . ' "' . $value . '"';
	}

	/**
	 * Translates a GF operator into words.
	 *
	 * @param string $operator GF operator.
	 *
	 * @return string Words.
	 */
	private function operator_words( string $operator ): string {
		return match ( $operator ) {
			'isnot'       => __( 'is not', 'webofd-gf-to-elementor' ),
			'>'           => __( 'is greater than', 'webofd-gf-to-elementor' ),
			'<'           => __( 'is less than', 'webofd-gf-to-elementor' ),
			'contains'    => __( 'contains', 'webofd-gf-to-elementor' ),
			'starts_with' => __( 'starts with', 'webofd-gf-to-elementor' ),
			'ends_with'   => __( 'ends with', 'webofd-gf-to-elementor' ),
			default       => __( 'is', 'webofd-gf-to-elementor' ),
		};
	}

	/**
	 * Finds the label for a field or sub-input ID ("5" or "1.3").
	 *
	 * @param string                           $field_id GF field or input ID.
	 * @param array<int, array<string, mixed>> $fields   Form fields.
	 *
	 * @return string Label, e.g. "Your Name (First)", or "field #5" if unknown.
	 */
	private function field_label( string $field_id, array $fields ): string {
		$parent = (string) strtok( $field_id, '.' );

		foreach ( $fields as $field ) {
			if ( (string) ( $field['id'] ?? '' ) !== $parent ) {
				continue;
			}

			$label = ItemFactory::plain( (string) ( $field['label'] ?? '' ) );

			return $parent === $field_id ? $label : $label . ' (' . $this->input_label( $field, $field_id ) . ')';
		}

		/* translators: %s: Gravity Forms field ID. */
		return sprintf( __( 'field #%s', 'webofd-gf-to-elementor' ), $field_id );
	}

	/**
	 * Finds a sub-input's label.
	 *
	 * @param array<string, mixed> $field    GF field.
	 * @param string               $input_id Input ID.
	 *
	 * @return string Input label, or the ID if not found.
	 */
	private function input_label( array $field, string $input_id ): string {
		foreach ( (array) ( $field['inputs'] ?? array() ) as $input ) {
			if ( is_array( $input ) && (string) ( $input['id'] ?? '' ) === $input_id ) {
				return ItemFactory::plain( (string) ( $input['customLabel'] ?? $input['label'] ?? $input_id ) );
			}
		}

		return $input_id;
	}
}
