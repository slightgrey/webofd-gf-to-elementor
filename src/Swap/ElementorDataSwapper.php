<?php
/**
 * Swaps a Gravity Forms embed inside Elementor data.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

use GFToElementor\Detector\ElementorLocation;

/**
 * Pure transformation of a decoded _elementor_data tree. Only the target element changes.
 */
final class ElementorDataSwapper {

	/**
	 * Non-underscore settings of the old widget that still apply to the new one.
	 */
	private const KEPT_SETTINGS = array( 'hide_desktop', 'hide_tablet', 'hide_mobile' );

	/**
	 * Creates the swapper.
	 *
	 * @param ShortcodeReplacer $shortcodes Shortcode replacer for inline swaps.
	 */
	public function __construct( private readonly ShortcodeReplacer $shortcodes ) {
	}

	/**
	 * Swaps the embed in one element.
	 *
	 * @param array<mixed>         $elements    Decoded _elementor_data.
	 * @param string               $element_id  Target element ID.
	 * @param string               $action      ElementorLocation::WIDGET_REPLACE or INLINE_REPLACE.
	 * @param int                  $form_id     Gravity Forms form ID.
	 * @param array<string, mixed> $form_widget Elementor form widget element.
	 * @param int                  $template_id Template for inline swaps (0 when not needed).
	 *
	 * @return array<mixed> New elements.
	 *
	 * @throws EmbedNotFound When the element is missing or no longer embeds the form.
	 */
	public function swap( array $elements, string $element_id, string $action, int $form_id, array $form_widget, int $template_id ): array {
		$found  = false;
		$result = $this->walk(
			$elements,
			$element_id,
			function ( array $element ) use ( $action, $form_id, $form_widget, $template_id ): array {
				return ElementorLocation::WIDGET_REPLACE === $action
					? $this->replace_widget( $element, $form_widget )
					: $this->replace_inline( $element, $form_id, $template_id );
			},
			$found
		);

		if ( ! $found ) {
			/* translators: %s: Elementor element ID. */
			throw new EmbedNotFound( sprintf( esc_html__( 'Element %s is no longer on the page.', 'webofd-gf-to-elementor' ), esc_html( $element_id ) ) );
		}

		return $result;
	}

	/**
	 * Walks the tree and transforms the element with the given ID.
	 *
	 * @param array<mixed> $elements   Elements.
	 * @param string       $element_id Target ID.
	 * @param callable     $transform  Receives and returns the target element.
	 * @param bool         $found      Set to true when the target is reached.
	 *
	 * @return array<mixed> Elements.
	 */
	private function walk( array $elements, string $element_id, callable $transform, bool &$found ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( (string) ( $element['id'] ?? '' ) === $element_id ) {
				$elements[ $index ] = $transform( $element );
				$found              = true;

				return $elements;
			}

			if ( is_array( $element['elements'] ?? null ) ) {
				$elements[ $index ]['elements'] = $this->walk( $element['elements'], $element_id, $transform, $found );
			}

			if ( $found ) {
				return $elements;
			}
		}

		return $elements;
	}

	/**
	 * Replaces a whole widget with the form widget, keeping its ID and Advanced-tab settings.
	 *
	 * @param array<string, mixed> $element     Old widget.
	 * @param array<string, mixed> $form_widget Form widget.
	 *
	 * @return array<string, mixed> New widget.
	 */
	private function replace_widget( array $element, array $form_widget ): array {
		$old_settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$kept         = array_filter(
			$old_settings,
			static fn( $key ): bool => str_starts_with( (string) $key, '_' ) || in_array( $key, self::KEPT_SETTINGS, true ),
			ARRAY_FILTER_USE_KEY
		);

		$form_widget['id']       = $element['id'];
		$form_widget['settings'] = array_merge( $kept, (array) ( $form_widget['settings'] ?? array() ) );

		return $form_widget;
	}

	/**
	 * Replaces the form's shortcodes inside a widget's text settings with the template shortcode.
	 *
	 * @param array<string, mixed> $element     Widget.
	 * @param int                  $form_id     Form ID.
	 * @param int                  $template_id Template ID.
	 *
	 * @return array<string, mixed> Widget.
	 *
	 * @throws EmbedNotFound When the widget no longer holds the form's shortcode.
	 */
	private function replace_inline( array $element, int $form_id, int $template_id ): array {
		$total    = 0;
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		array_walk_recursive(
			$settings,
			function ( &$value ) use ( $form_id, $template_id, &$total ): void {
				if ( is_string( $value ) ) {
					$count  = 0;
					$value  = $this->shortcodes->replace( $value, $form_id, ShortcodeReplacer::template_shortcode( $template_id ), $count );
					$total += $count;
				}
			}
		);

		if ( 0 === $total ) {
			throw new EmbedNotFound( esc_html__( 'The widget no longer contains the form shortcode.', 'webofd-gf-to-elementor' ) );
		}

		$element['settings'] = $settings;

		return $element;
	}
}
