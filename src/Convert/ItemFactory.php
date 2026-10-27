<?php
/**
 * Builds Elementor form_fields repeater items.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Applies the properties every GF field shares (label, required, width, placeholder,
 * default value, CSS class) to a new Elementor repeater item.
 */
final class ItemFactory {

	/**
	 * Elementor field types that show a placeholder.
	 */
	private const PLACEHOLDER_TYPES = array( 'tel', 'text', 'email', 'textarea', 'number', 'url', 'password', 'time', 'date' );

	/**
	 * Elementor field types that accept a default value.
	 */
	private const VALUE_TYPES = array( 'text', 'email', 'textarea', 'url', 'tel', 'radio', 'select', 'number', 'date', 'time', 'hidden' );

	/**
	 * Elementor field types without a width or required setting.
	 */
	private const LAYOUTLESS_TYPES = array( 'hidden', 'recaptcha', 'recaptcha_v3', 'step', 'honeypot' );

	/**
	 * Returns a new random element ID (Elementor's 7-character hex format).
	 *
	 * @var callable(): string
	 */
	private $random_id;

	/**
	 * Creates the factory.
	 *
	 * @param callable(): string $random_id Returns a new random ID for each repeater item.
	 */
	public function __construct( callable $random_id ) {
		$this->random_id = $random_id;
	}

	/**
	 * Builds a repeater item from a GF field.
	 *
	 * @param string               $type      Elementor field type.
	 * @param string               $custom_id Field ID.
	 * @param array<string, mixed> $field     GF field (label, isRequired, layoutGridColumnSpan...).
	 * @param array<string, mixed> $overrides Settings that replace the derived ones; null removes a key.
	 *
	 * @return array<string, mixed> Repeater item.
	 */
	public function make( string $type, string $custom_id, array $field, array $overrides = array() ): array {
		$item = array_merge(
			array(
				'_id'         => ( $this->random_id )(),
				'custom_id'   => $custom_id,
				'field_type'  => $type,
				'field_label' => self::plain( (string) ( $field['label'] ?? '' ) ),
			),
			$this->layout( $type, $field ),
			$this->content( $type, $field ),
			$overrides
		);

		return array_filter( $item, static fn( $value ): bool => null !== $value );
	}

	/**
	 * Returns a new random element ID.
	 *
	 * @return string ID.
	 */
	public function random_id(): string {
		return ( $this->random_id )();
	}

	/**
	 * Strips tags and decodes entities from a GF label.
	 *
	 * @param string $text Label.
	 *
	 * @return string Plain text.
	 */
	public static function plain( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Tells whether a GF default value is a merge tag Elementor can't evaluate.
	 *
	 * @param string $value Default value.
	 *
	 * @return bool True for values like "{user:display_name}".
	 */
	public static function is_dynamic_value( string $value ): bool {
		return 1 === preg_match( '/\{[^{}]+\}/', $value );
	}

	/**
	 * Builds the required and width settings.
	 *
	 * @param string               $type  Elementor field type.
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return array<string, string> Settings.
	 */
	private function layout( string $type, array $field ): array {
		if ( in_array( $type, self::LAYOUTLESS_TYPES, true ) ) {
			return array();
		}

		$layout = array( 'width' => Widths::from_span( $field['layoutGridColumnSpan'] ?? '' ) );

		if ( 'html' !== $type ) {
			$layout['required'] = empty( $field['isRequired'] ) ? '' : 'true';
		}

		return $layout;
	}

	/**
	 * Builds the placeholder, default value and CSS class settings.
	 *
	 * @param string               $type  Elementor field type.
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return array<string, string> Settings.
	 */
	private function content( string $type, array $field ): array {
		$content     = array();
		$placeholder = self::plain( (string) ( $field['placeholder'] ?? '' ) );
		$value       = (string) ( $field['defaultValue'] ?? '' );
		$css         = trim( (string) preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) ( $field['cssClass'] ?? '' ) ) );

		if ( '' !== $placeholder && in_array( $type, self::PLACEHOLDER_TYPES, true ) ) {
			$content['placeholder'] = $placeholder;
		}

		if ( '' !== $value && ! self::is_dynamic_value( $value ) && in_array( $type, self::VALUE_TYPES, true ) ) {
			$content['field_value'] = $value;
		}

		if ( '' !== $css ) {
			$content['css_classes'] = $css;
		}

		return $content;
	}
}
