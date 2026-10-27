<?php
/**
 * One place where a form is embedded, with how the migration will treat it.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Detector;

/**
 * Immutable embed location with a stable key the REST API and the report use.
 */
final class Embed {

	/**
	 * Embed inside an Elementor document's _elementor_data.
	 */
	public const KIND_ELEMENTOR = 'elementor';

	/**
	 * Embed in a non-Elementor post's post_content (shortcode or block).
	 */
	public const KIND_CONTENT = 'content';

	/**
	 * Embed in a sidebar widget option.
	 */
	public const KIND_WIDGET = 'widget';

	/**
	 * Embed in other post meta (ACF and similar); reported only.
	 */
	public const KIND_META = 'meta';

	/**
	 * Replace the content with [elementor-template id="X"].
	 */
	public const ACTION_TEMPLATE = 'template_shortcode';

	/**
	 * Leave it alone and list it in the report.
	 */
	public const ACTION_REPORT_ONLY = 'report_only';

	/**
	 * Creates an embed.
	 *
	 * @param string $key        Stable key, e.g. "post:12:elementor:abc1234".
	 * @param int    $form_id    Gravity Forms form ID.
	 * @param string $kind       KIND_* constant.
	 * @param int    $post_id    Post ID, 0 for widgets.
	 * @param string $object_ref Element ID, widget ID ("text-3") or meta key; "" for content.
	 * @param string $action     ElementorLocation action or an ACTION_* constant.
	 * @param string $method     How it is embedded: shortcode, block, elementor_widget, elementor_shortcode, widget, meta.
	 * @param string $context    Widget type, block name, sidebar or meta key.
	 */
	public function __construct(
		public readonly string $key,
		public readonly int $form_id,
		public readonly string $kind,
		public readonly int $post_id,
		public readonly string $object_ref,
		public readonly string $action,
		public readonly string $method,
		public readonly string $context = ''
	) {
	}

	/**
	 * Tells whether the migration changes this embed.
	 *
	 * @return bool False for report-only embeds.
	 */
	public function is_swappable(): bool {
		return self::ACTION_REPORT_ONLY !== $this->action;
	}

	/**
	 * Tells whether swapping this embed needs the saved Elementor template.
	 *
	 * @return bool True for template shortcode and inline replacements.
	 */
	public function needs_template(): bool {
		return in_array( $this->action, array( self::ACTION_TEMPLATE, ElementorLocation::INLINE_REPLACE ), true );
	}

	/**
	 * Returns the embed as plain data.
	 *
	 * @return array<string, string|int> Data.
	 */
	public function to_array(): array {
		return array(
			'key'        => $this->key,
			'form_id'    => $this->form_id,
			'kind'       => $this->kind,
			'post_id'    => $this->post_id,
			'object_ref' => $this->object_ref,
			'action'     => $this->action,
			'method'     => $this->method,
			'context'    => $this->context,
		);
	}
}
