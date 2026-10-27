<?php
/**
 * Tests for WidgetSwapper.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Swap\ContentSwapper;
use GFToElementor\Swap\EmbedNotFound;
use GFToElementor\Swap\ShortcodeReplacer;
use GFToElementor\Swap\WidgetSwapper;
use PHPUnit\Framework\TestCase;

/**
 * Only the targeted widget instance changes.
 */
final class WidgetSwapperTest extends TestCase {

	/**
	 * Returns a swapper.
	 *
	 * @return WidgetSwapper Swapper.
	 */
	private function swapper(): WidgetSwapper {
		return new WidgetSwapper( new ContentSwapper( new ShortcodeReplacer() ) );
	}

	/**
	 * A multi-instance text widget option: instance 3 changes, 2 and the _multiwidget flag don't.
	 *
	 * @return void
	 */
	public function test_only_target_instance_changes(): void {
		$option = array(
			2              => array(
				'title' => 'Other',
				'text'  => '[gravityform id="3"]',
			),
			3              => array(
				'title' => 'Contact',
				'text'  => '<p>Ask us</p>[gravityform id="3"]',
			),
			'_multiwidget' => 1,
		);

		$after = $this->swapper()->swap( $option, 'text', 3, 3, 77 );

		$this->assertSame( '<p>Ask us</p>[elementor-template id="77"]', $after[3]['text'] );
		$this->assertSame( 'Contact', $after[3]['title'] );
		$this->assertSame( $option[2], $after[2] );
		$this->assertSame( 1, $after['_multiwidget'] );
	}

	/**
	 * Block widgets use their "content" setting and get a Shortcode block.
	 *
	 * @return void
	 */
	public function test_block_widget(): void {
		$after = $this->swapper()->swap( array( 5 => array( 'content' => '<!-- wp:gravityforms/form {"formId":"3"} /-->' ) ), 'block', 5, 3, 77 );

		$this->assertSame( "<!-- wp:shortcode -->\n[elementor-template id=\"77\"]\n<!-- /wp:shortcode -->", $after[5]['content'] );
	}

	/**
	 * A missing instance throws.
	 *
	 * @return void
	 */
	public function test_missing_instance_throws(): void {
		$this->expectException( EmbedNotFound::class );

		$this->swapper()->swap( array( 2 => array( 'text' => '' ) ), 'text', 9, 3, 77 );
	}
}
