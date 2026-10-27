<?php
/**
 * Tests that the unit-test bootstrap provides the WordPress functions the plugin relies on.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The real WordPress parsers and formatting helpers are available without a database.
 */
final class BootstrapTest extends TestCase {

	/**
	 * Block and shortcode parsers are callable.
	 *
	 * @return void
	 */
	public function test_parsers_are_loaded(): void {
		$blocks = parse_blocks( '<!-- wp:gravityforms/form {"formId":"3"} /-->' );

		$this->assertSame( 'gravityforms/form', $blocks[0]['blockName'] );
		$this->assertSame( 1, preg_match( '/' . get_shortcode_regex( array( 'gravityform' ) ) . '/', '[gravityform id="1"]' ) );
	}

	/**
	 * Formatting helpers work with the option stubs.
	 *
	 * @return void
	 */
	public function test_formatting_helpers_work(): void {
		$this->assertSame( 'Creme brulee', remove_accents( 'Crème brûlée' ) );
		$this->assertSame( '&lt;b&gt;', esc_html( '<b>' ) );
		$this->assertSame( 'admin@example.test', get_option( 'admin_email' ) );
	}
}
