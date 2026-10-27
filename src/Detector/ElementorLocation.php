<?php
/**
 * Where a form is embedded inside Elementor data, and how to swap it.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Detector;

/**
 * One Elementor widget that embeds the form.
 */
final class ElementorLocation {

	/**
	 * The whole widget is the form: replace it with the Form widget.
	 */
	public const WIDGET_REPLACE = 'widget_replace';

	/**
	 * The shortcode sits among other content: replace only the shortcode with the template shortcode.
	 */
	public const INLINE_REPLACE = 'inline_replace';

	/**
	 * Creates a location.
	 *
	 * @param string $element_id  Elementor element ID (unique within the document).
	 * @param string $widget_type Widget type, e.g. "shortcode", "text-editor", "eael-gravity-form".
	 * @param string $action      WIDGET_REPLACE or INLINE_REPLACE.
	 */
	public function __construct(
		public readonly string $element_id,
		public readonly string $widget_type,
		public readonly string $action
	) {
	}
}
