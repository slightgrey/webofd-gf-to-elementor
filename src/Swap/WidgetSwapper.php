<?php
/**
 * Swaps a Gravity Forms embed in one sidebar widget instance.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

use GFToElementor\Detector\EmbedFinder;

/**
 * Pure transformation of a widget_{base} option array: only the target instance changes.
 */
final class WidgetSwapper {

	/**
	 * Creates the swapper.
	 *
	 * @param ContentSwapper $content Content swapper for the instance's text.
	 */
	public function __construct( private readonly ContentSwapper $content ) {
	}

	/**
	 * Swaps the form in one widget instance.
	 *
	 * @param array<int|string, mixed> $option      The widget_{base} option value.
	 * @param string                   $base        Widget base: text, custom_html or block.
	 * @param int                      $number      Instance number.
	 * @param int                      $form_id     Form ID.
	 * @param int                      $template_id Elementor template ID.
	 *
	 * @return array<int|string, mixed> New option value.
	 *
	 * @throws EmbedNotFound When the instance is missing or no longer embeds the form.
	 */
	public function swap( array $option, string $base, int $number, int $form_id, int $template_id ): array {
		$field = EmbedFinder::WIDGET_FIELDS[ $base ] ?? null;

		if ( null === $field || ! is_array( $option[ $number ] ?? null ) || ! is_string( $option[ $number ][ $field ] ?? null ) ) {
			/* translators: %s: widget ID, e.g. "text-3". */
			throw new EmbedNotFound( sprintf( esc_html__( 'Widget %s no longer exists.', 'webofd-gf-to-elementor' ), esc_html( $base . '-' . $number ) ) );
		}

		$option[ $number ][ $field ] = $this->content->swap( $option[ $number ][ $field ], $form_id, $template_id );

		return $option;
	}
}
