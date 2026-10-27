<?php
/**
 * Swaps Gravity Forms embeds in post content and widget text.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

/**
 * Replaces the form's GF blocks with a Shortcode block, and its shortcodes with the
 * Elementor template shortcode. Works on the raw text so nothing else in the post
 * is re-serialized: a rollback restores exactly the original bytes.
 */
final class ContentSwapper {

	/**
	 * Matches a gravityforms/form block, self-closing or with a closing comment.
	 */
	private const FORM_BLOCK = '#<!--\s+wp:gravityforms/form(?:\s+(\{.*?\}))?\s+(?:/-->|-->.*?<!--\s+/wp:gravityforms/form\s+-->)#s';

	/**
	 * Creates the swapper.
	 *
	 * @param ShortcodeReplacer $shortcodes Shortcode replacer.
	 */
	public function __construct( private readonly ShortcodeReplacer $shortcodes ) {
	}

	/**
	 * Replaces every embed of one form.
	 *
	 * @param string $content     Post content or widget text.
	 * @param int    $form_id     Form ID.
	 * @param int    $template_id Elementor template that now holds the form.
	 *
	 * @return string New content.
	 *
	 * @throws EmbedNotFound When the content no longer embeds the form.
	 */
	public function swap( string $content, int $form_id, int $template_id ): string {
		$shortcode  = ShortcodeReplacer::template_shortcode( $template_id );
		$blocks     = 0;
		$shortcodes = 0;
		$content    = $this->replace_blocks( $content, $form_id, $shortcode, $blocks );
		$content    = $this->shortcodes->replace( $content, $form_id, $shortcode, $shortcodes );

		if ( 0 === $blocks + $shortcodes ) {
			throw new EmbedNotFound( esc_html__( 'The content no longer embeds the form.', 'webofd-gf-to-elementor' ) );
		}

		return $content;
	}

	/**
	 * Replaces the form's GF blocks with Shortcode blocks.
	 *
	 * @param string $content   Content.
	 * @param int    $form_id   Form ID.
	 * @param string $shortcode Template shortcode.
	 * @param int    $count     Set to the number of blocks replaced.
	 *
	 * @return string Content.
	 */
	private function replace_blocks( string $content, int $form_id, string $shortcode, int &$count ): string {
		if ( false === strpos( $content, 'wp:gravityforms/form' ) ) {
			return $content;
		}

		$result = preg_replace_callback(
			self::FORM_BLOCK,
			static function ( array $block ) use ( $form_id, $shortcode, &$count ): string {
				$attrs = isset( $block[1] ) ? json_decode( $block[1], true ) : null;

				if ( ! is_array( $attrs ) || trim( (string) ( $attrs['formId'] ?? '' ) ) !== (string) $form_id ) {
					return $block[0];
				}

				++$count;

				return "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
			},
			$content
		);

		return is_string( $result ) ? $result : $content;
	}
}
