<?php
/**
 * One Gravity Forms embed found by a detector.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Detector;

/**
 * Immutable description of a single embed: which form, how it is embedded, and the
 * exact text matched (so a swapper can replace precisely that text).
 */
final class EmbedMatch {

	/**
	 * Creates a match.
	 *
	 * @param int|null $form_id      Numeric form ID, or null if only a name was given.
	 * @param string   $form_ref     Identifier text as found, e.g. "3" or 'name="Contact"'.
	 * @param string   $embed_method shortcode, block, elementor_widget, elementor_shortcode, meta or widget.
	 * @param string   $context      Block name, Elementor widget type, meta key or sidebar.
	 * @param string   $raw          Exact matched text, e.g. '[gravityform id="3" ajax="true"]'; "" for blocks and widgets.
	 */
	public function __construct(
		private readonly ?int $form_id,
		private readonly string $form_ref,
		private readonly string $embed_method,
		private readonly string $context = '',
		private readonly string $raw = ''
	) {
	}

	/**
	 * Returns the numeric form ID, or null when only a name was given.
	 *
	 * @return int|null Form ID.
	 */
	public function form_id(): ?int {
		return $this->form_id;
	}

	/**
	 * Returns the identifier text as found.
	 *
	 * @return string Form reference.
	 */
	public function form_ref(): string {
		return $this->form_ref;
	}

	/**
	 * Returns the embed method.
	 *
	 * @return string Embed method.
	 */
	public function embed_method(): string {
		return $this->embed_method;
	}

	/**
	 * Returns the context detail.
	 *
	 * @return string Context.
	 */
	public function context(): string {
		return $this->context;
	}

	/**
	 * Returns the exact matched text.
	 *
	 * @return string Raw text, "" when not applicable.
	 */
	public function raw(): string {
		return $this->raw;
	}

	/**
	 * Returns a copy with a different method and context, keeping the form and raw text.
	 *
	 * @param string $embed_method New embed method.
	 * @param string $context      New context.
	 *
	 * @return self New match.
	 */
	public function with_source( string $embed_method, string $context ): self {
		return new self( $this->form_id, $this->form_ref, $embed_method, $context, $this->raw );
	}

	/**
	 * Builds the identity key used to collapse duplicate matches.
	 *
	 * @return string Key combining form, method and context.
	 */
	public function key(): string {
		$form = null === $this->form_id ? 'ref:' . $this->form_ref : (string) $this->form_id;

		return $form . '|' . $this->embed_method . '|' . $this->context;
	}

	/**
	 * Removes duplicate matches, keeping the first of each.
	 *
	 * @param array<int, self> $matches Matches.
	 *
	 * @return array<int, self> Unique matches.
	 */
	public static function unique( array $matches ): array {
		$unique = array();

		foreach ( $matches as $match ) {
			$unique[ $match->key() ] = $unique[ $match->key() ] ?? $match;
		}

		return array_values( $unique );
	}
}
