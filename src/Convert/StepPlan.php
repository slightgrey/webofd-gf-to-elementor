<?php
/**
 * Works out Elementor step labels and buttons from Gravity Forms page breaks.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * GF page N's "Next" text lives on the page break that ends page N, and its
 * "Previous" text on the break that starts it. Elementor puts both on the step,
 * so this reads the whole form once and answers per step.
 */
final class StepPlan {

	/**
	 * Page break fields in order. Index k starts page k (page 0 has no break).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $breaks = array();

	/**
	 * Page names from the form's pagination settings.
	 *
	 * @var array<int, string>
	 */
	private array $names;

	/**
	 * Reads the form's page breaks and page names.
	 *
	 * @param array<string, mixed> $form GF form.
	 */
	public function __construct( array $form ) {
		$index = 1;

		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( is_array( $field ) && 'page' === ( $field['type'] ?? '' ) ) {
				$this->breaks[ $index ] = $field;
				++$index;
			}
		}

		$pages       = $form['pagination']['pages'] ?? array();
		$this->names = is_array( $pages ) ? array_values( array_map( 'strval', $pages ) ) : array();
	}

	/**
	 * Tells whether the form has more than one page.
	 *
	 * @return bool True if there is at least one page break.
	 */
	public function has_pages(): bool {
		return array() !== $this->breaks;
	}

	/**
	 * Returns the page index a page break starts.
	 *
	 * @param array<string, mixed> $page_field GF page field.
	 *
	 * @return int Page index (1 for the first break).
	 */
	public function index_of( array $page_field ): int {
		foreach ( $this->breaks as $index => $field ) {
			if ( (string) ( $field['id'] ?? '' ) === (string) ( $page_field['id'] ?? '' ) ) {
				return $index;
			}
		}

		return 0;
	}

	/**
	 * Returns a step's title: the GF page name, or "Step N".
	 *
	 * @param int $index Page index.
	 *
	 * @return string Label.
	 */
	public function label( int $index ): string {
		$name = trim( $this->names[ $index ] ?? '' );

		/* translators: %d: step number. */
		return '' !== $name ? $name : sprintf( __( 'Step %d', 'webofd-gf-to-elementor' ), $index + 1 );
	}

	/**
	 * Returns a step's button texts.
	 *
	 * @param int $index Page index.
	 *
	 * @return array<string, string> previous_button / next_button, only when GF sets a text.
	 */
	public function buttons( int $index ): array {
		return array_filter(
			array(
				'previous_button' => $this->button_text( $this->breaks[ $index ]['previousButton'] ?? null ),
				'next_button'     => $this->button_text( $this->breaks[ $index + 1 ]['nextButton'] ?? null ),
			)
		);
	}

	/**
	 * Reads a GF button's text (image buttons have none).
	 *
	 * @param mixed $button GF button settings.
	 *
	 * @return string Text, or "".
	 */
	private function button_text( mixed $button ): string {
		if ( ! is_array( $button ) || 'text' !== ( $button['type'] ?? 'text' ) ) {
			return '';
		}

		return ItemFactory::plain( (string) ( $button['text'] ?? '' ) );
	}
}
