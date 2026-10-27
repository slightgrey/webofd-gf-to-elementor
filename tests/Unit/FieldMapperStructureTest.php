<?php
/**
 * Task 5.4: steps, sections, uploads, consent, CAPTCHA and honeypot.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\FieldResult;
use GFToElementor\Convert\ResultStatus;

/**
 * Structural and special GF fields map to their Elementor counterparts.
 */
final class FieldMapperStructureTest extends FieldMapperTestCase {

	/**
	 * Returns the items of one Elementor type from a result.
	 *
	 * @param FieldResult $result Result.
	 * @param string      $type   Elementor field type.
	 *
	 * @return array<int, array<string, mixed>> Items.
	 */
	private function items_of( FieldResult $result, string $type ): array {
		return array_values( array_filter( $result->items, static fn( array $item ): bool => $type === $item['field_type'] ) );
	}

	/**
	 * Two page breaks give three steps, the first one before any field, with GF's names and button texts.
	 *
	 * @return void
	 */
	public function test_pages_become_steps(): void {
		$result = $this->mapper()->map_form( $this->form( 'kitchen-sink' ) );
		$steps  = $this->items_of( $result, 'step' );

		$this->assertSame( 'step', $result->items[0]['field_type'] );
		$this->assertSame( array( 'About you', 'Details', 'Finish' ), array_column( $steps, 'field_label' ) );
		$this->assertSame( 'Next', $steps[0]['next_button'] );
		$this->assertArrayNotHasKey( 'previous_button', $steps[0] );
		$this->assertSame( 'Back', $steps[1]['previous_button'] );
		$this->assertSame( 'Continue', $steps[1]['next_button'] );
		$this->assertSame( 'Previous', $steps[2]['previous_button'] );
		$this->assertArrayNotHasKey( 'next_button', $steps[2] );
	}

	/**
	 * A page break's conditional logic is reported as dropped.
	 *
	 * @return void
	 */
	public function test_page_logic_is_reported(): void {
		$result = $this->mapper()->map_form( $this->form( 'kitchen-sink' ) );
		$logic  = array_filter( $result->entries, static fn( $entry ): bool => 'logic' === $entry->subject && str_contains( $entry->gf_ref, '#19' ) );

		$this->assertCount( 1, $logic );
	}

	/**
	 * A section break becomes an HTML heading with its description.
	 *
	 * @return void
	 */
	public function test_section_becomes_heading(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 18 ) );

		$this->assertSame( 'html', $result->items[0]['field_type'] );
		$this->assertSame( '<h3>More about you</h3><p>Optional details</p>', $result->items[0]['field_html'] );
		$this->assertSame( ResultStatus::Approximated, $this->entry( $result )->status );
	}

	/**
	 * A file upload keeps its types, size and count.
	 *
	 * @return void
	 */
	public function test_upload_settings(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 20 ) )->items[0];

		$this->assertSame( 'upload', $item['field_type'] );
		$this->assertSame( 'pdf,jpg,png', $item['file_types'] );
		$this->assertSame( 5, $item['file_sizes'] );
		$this->assertSame( 'yes', $item['allow_multiple_upload'] );
		$this->assertSame( 3, $item['max_files'] );
	}

	/**
	 * The three consent fields of form #3 become acceptance fields with their text.
	 *
	 * @return void
	 */
	public function test_real_consents_become_acceptance(): void {
		$items = $this->items_of( $this->mapper()->map_form( $this->form( 'form-3' ) ), 'acceptance' );

		$this->assertCount( 3, $items );
		$this->assertStringStartsWith( 'I/We understand that I/we am/are required to give a minimum of 28 days notice', $items[0]['acceptance_text'] );
	}

	/**
	 * Consent HTML (a terms link) survives; a required consent stays required.
	 *
	 * @return void
	 */
	public function test_consent_keeps_link(): void {
		$item = $this->map_one( $this->field( 'kitchen-sink', 21 ) )->items[0];

		$this->assertSame( 'I agree to the <a href="/terms">terms</a>.', $item['acceptance_text'] );
		$this->assertSame( 'true', $item['required'] );
	}

	/**
	 * A reCAPTCHA keeps its size and theme and requires Elementor's reCAPTCHA keys.
	 *
	 * @return void
	 */
	public function test_recaptcha(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 22 ) );

		$this->assertSame( 'recaptcha', $result->items[0]['field_type'] );
		$this->assertSame( 'compact', $result->items[0]['recaptcha_size'] );
		$this->assertSame( 'dark', $result->items[0]['recaptcha_style'] );
		$this->assertSame( array( 'recaptcha' ), $result->requirements );
	}

	/**
	 * A math CAPTCHA becomes a honeypot (Approximated).
	 *
	 * @return void
	 */
	public function test_math_captcha_becomes_honeypot(): void {
		$result = $this->map_one( $this->field( 'kitchen-sink', 23 ) );

		$this->assertSame( 'honeypot', $result->items[0]['field_type'] );
		$this->assertSame( ResultStatus::Approximated, $this->entry( $result )->status );
	}

	/**
	 * The form's honeypot setting adds one honeypot, but not a second one.
	 *
	 * @return void
	 */
	public function test_form_honeypot_added_once(): void {
		$form                   = $this->form( 'form-1' );
		$form['enableHoneypot'] = true;

		$this->assertCount( 1, $this->items_of( $this->mapper()->map_form( $form ), 'honeypot' ) );
		$this->assertCount( 1, $this->items_of( $this->mapper()->map_form( $this->form( 'kitchen-sink' ) ), 'honeypot' ) );
		$this->assertCount( 0, $this->items_of( $this->mapper()->map_form( $this->form( 'form-1' ) ), 'honeypot' ) );
	}
}
