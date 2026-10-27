<?php
/**
 * Tests for MergeTagConverter.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\MergeTagConverter;
use PHPUnit\Framework\TestCase;

/**
 * GF merge tags become Elementor shortcodes or values; the rest is removed and reported.
 */
final class MergeTagConverterTest extends TestCase {

	/**
	 * Builds a converter with a small field map.
	 *
	 * @return MergeTagConverter Converter.
	 */
	private function converter(): MergeTagConverter {
		return new MergeTagConverter(
			array(
				'1.3' => 'your_name_first',
				'2'   => 'email',
				'3'   => 'phone',
			),
			'admin@example.test',
			'Contact Form'
		);
	}

	/**
	 * Field tags, including sub-input and label-less tags, become [field] shortcodes.
	 *
	 * @return void
	 */
	public function test_field_tags(): void {
		$result = $this->converter()->convert( 'Hi {Your Name (First):1.3}, we will call {:3} or write to {Email:2}.' );

		$this->assertSame( 'Hi [field id="your_name_first"], we will call [field id="phone"] or write to [field id="email"].', $result->text );
		$this->assertFalse( $result->has_problems() );
	}

	/**
	 * Built-in tags are converted or replaced by their values.
	 *
	 * @return void
	 */
	public function test_builtin_tags(): void {
		$result = $this->converter()->convert( "{all_fields}\nFrom {admin_email} about {form_title}" );

		$this->assertSame( "[all-fields]\nFrom admin@example.test about Contact Form", $result->text );
	}

	/**
	 * A modifier is dropped but the tag still converts, and is reported.
	 *
	 * @return void
	 */
	public function test_modifier_is_reported(): void {
		$result = $this->converter()->convert( '{Email:2:noadmin} {all_fields:nohidden}' );

		$this->assertSame( '[field id="email"] [all-fields]', $result->text );
		$this->assertSame( array( '{Email:2:noadmin}', '{all_fields:nohidden}' ), $result->problems );
	}

	/**
	 * Unsupported tags and tags for missing fields are removed and reported.
	 *
	 * @return void
	 */
	public function test_unsupported_tags_are_removed(): void {
		$result = $this->converter()->convert( 'Entry {entry_id} on {date_mdy} by {user:display_name}; old {Gone:99}.' );

		$this->assertSame( 'Entry  on  by ; old .', $result->text );
		$this->assertSame( array( '{entry_id}', '{date_mdy}', '{user:display_name}', '{Gone:99}' ), $result->problems );
	}

	/**
	 * Text without tags, and CSS braces, are left alone.
	 *
	 * @return void
	 */
	public function test_plain_text_and_css_unchanged(): void {
		$text   = '<style>p { color: red; }</style><p>No tags here.</p>';
		$result = $this->converter()->convert( $text );

		$this->assertSame( $text, $result->text );
		$this->assertFalse( $result->has_problems() );
	}

	/**
	 * A text that is exactly one field tag gives that field's custom_id.
	 *
	 * @return void
	 */
	public function test_single_field_id(): void {
		$converter = $this->converter();

		$this->assertSame( 'email', $converter->single_field_id( ' {Email:2} ' ) );
		$this->assertNull( $converter->single_field_id( 'info@example.test' ) );
		$this->assertNull( $converter->single_field_id( '{Email:2}, {:3}' ) );
		$this->assertNull( $converter->single_field_id( '{Gone:99}' ) );
	}
}
