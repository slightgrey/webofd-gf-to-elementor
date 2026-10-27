<?php
/**
 * Tests for ContentSwapper and ShortcodeReplacer.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Swap\ContentSwapper;
use GFToElementor\Swap\EmbedNotFound;
use GFToElementor\Swap\ShortcodeReplacer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only the target form's blocks and shortcodes change; everything else keeps its bytes.
 */
final class ContentSwapperTest extends TestCase {

	/**
	 * Swaps form 3 into template 77.
	 *
	 * @param string $content Content.
	 *
	 * @return string New content.
	 */
	private function swap( string $content ): string {
		return ( new ContentSwapper( new ShortcodeReplacer() ) )->swap( $content, 3, 77 );
	}

	/**
	 * Supplies every shortcode spelling from the Locator's test list.
	 *
	 * @return array<string, array{string}> Cases.
	 */
	public static function shortcodes(): array {
		return array(
			'double quotes'  => array( '[gravityform id="3"]' ),
			'single quotes'  => array( "[gravityform id='3']" ),
			'unquoted'       => array( '[gravityform id=3]' ),
			'extra atts'     => array( '[gravityform id="3" title="false" ajax="true"]' ),
			'old tag'        => array( "[gravityforms id='3']" ),
			'upper case att' => array( '[gravityform ID="3"]' ),
			'action form'    => array( '[gravityform action="form" id="3"]' ),
		);
	}

	/**
	 * Every spelling is replaced, and surrounding text is untouched.
	 *
	 * @param string $shortcode Shortcode.
	 *
	 * @return void
	 */
	#[DataProvider( 'shortcodes' )]
	public function test_shortcode_spellings( string $shortcode ): void {
		$this->assertSame( "<p>Hi</p>\n[elementor-template id=\"77\"]\n<p>Bye</p>", $this->swap( "<p>Hi</p>\n{$shortcode}\n<p>Bye</p>" ) );
	}

	/**
	 * Escaped shortcodes, other forms and non-form actions stay; several of ours are all replaced.
	 *
	 * @return void
	 */
	public function test_leaves_others_alone(): void {
		$content = '[[gravityform id="3"]] [gravityform id="4"] [gravityform action="conditional" id="3"] [gravityform id="3"] [gravityform id="33"] [gravityform id="3"]';

		$this->assertSame(
			'[[gravityform id="3"]] [gravityform id="4"] [gravityform action="conditional" id="3"] [elementor-template id="77"] [gravityform id="33"] [elementor-template id="77"]',
			$this->swap( $content )
		);
	}

	/**
	 * Top-level, nested and closing-comment GF blocks become Shortcode blocks; other forms' blocks stay.
	 *
	 * @return void
	 */
	public function test_blocks(): void {
		$content  = "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n";
		$content .= "<!-- wp:gravityforms/form {\"formId\":\"3\",\"title\":false} /-->\n\n";
		$content .= "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:gravityforms/form {\"formId\":3} /--></div>\n<!-- /wp:group -->\n\n";
		$content .= "<!-- wp:gravityforms/form {\"formId\":\"3\"} -->\n<div>legacy</div>\n<!-- /wp:gravityforms/form -->\n\n";
		$content .= '<!-- wp:gravityforms/form {"formId":"4"} /-->';

		$block    = "<!-- wp:shortcode -->\n[elementor-template id=\"77\"]\n<!-- /wp:shortcode -->";
		$expected = "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n{$block}\n\n<!-- wp:group -->\n<div class=\"wp-block-group\">{$block}</div>\n<!-- /wp:group -->\n\n{$block}\n\n<!-- wp:gravityforms/form {\"formId\":\"4\"} /-->";

		$this->assertSame( $expected, $this->swap( $content ) );
	}

	/**
	 * A shortcode inside a Shortcode block is replaced in place.
	 *
	 * @return void
	 */
	public function test_shortcode_block(): void {
		$this->assertSame(
			"<!-- wp:shortcode -->\n[elementor-template id=\"77\"]\n<!-- /wp:shortcode -->",
			$this->swap( "<!-- wp:shortcode -->\n[gravityform id=\"3\" title=\"false\"]\n<!-- /wp:shortcode -->" )
		);
	}

	/**
	 * Content without the form throws.
	 *
	 * @return void
	 */
	public function test_nothing_to_swap_throws(): void {
		$this->expectException( EmbedNotFound::class );

		$this->swap( '[gravityform id="4"] [[gravityform id="3"]]' );
	}
}
