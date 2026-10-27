<?php
/**
 * Tests for ElementorDataSwapper.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Detector\ElementorLocation;
use GFToElementor\Swap\ElementorDataSwapper;
use GFToElementor\Swap\EmbedNotFound;
use GFToElementor\Swap\ShortcodeReplacer;
use PHPUnit\Framework\TestCase;

/**
 * Only the target element changes; whole widgets keep their ID and Advanced settings.
 */
final class ElementorDataSwapperTest extends TestCase {

	/**
	 * A minimal form widget as the converter builds it.
	 */
	private const FORM_WIDGET = array(
		'id'         => 'newform',
		'elType'     => 'widget',
		'widgetType' => 'form',
		'settings'   => array( 'form_name' => 'Contact' ),
		'elements'   => array(),
	);

	/**
	 * Builds a widget element.
	 *
	 * @param string               $id       Element ID.
	 * @param string               $type     Widget type.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, mixed> Element.
	 */
	private function widget( string $id, string $type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Builds a page: section > column > [shortcode widget, mixed text editor], plus a nested container.
	 *
	 * @return array<int, array<string, mixed>> Elements.
	 */
	private function page(): array {
		return array(
			array(
				'id'       => 'sec',
				'elType'   => 'section',
				'settings' => array( 'gap' => 'wide' ),
				'elements' => array(
					array(
						'id'       => 'col',
						'elType'   => 'column',
						'settings' => array(),
						'elements' => array(
							$this->widget(
								'sc1',
								'shortcode',
								array(
									'shortcode'    => '[gravityform id="3" ajax="true"]',
									'_css_classes' => 'contact-box',
									'_margin'      => array(
										'top'  => '20',
										'unit' => 'px',
									),
									'hide_mobile'  => 'hidden-mobile',
								)
							),
							$this->widget( 'te1', 'text-editor', array( 'editor' => '<p>Write to us:</p>[gravityform id="3"]<p>[gravityform id="4"]</p>' ) ),
						),
					),
				),
			),
			array(
				'id'       => 'con',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(
					array(
						'id'       => 'inner',
						'elType'   => 'container',
						'settings' => array(),
						'elements' => array( $this->widget( 'ea1', 'eael-gravity-form', array( 'contact_form_list' => '3' ) ) ),
					),
				),
			),
		);
	}

	/**
	 * Returns a swapper.
	 *
	 * @return ElementorDataSwapper Swapper.
	 */
	private function swapper(): ElementorDataSwapper {
		return new ElementorDataSwapper( new ShortcodeReplacer() );
	}

	/**
	 * A Shortcode widget becomes the form widget with the old ID and Advanced settings.
	 *
	 * @return void
	 */
	public function test_whole_widget_replace(): void {
		$after  = $this->swapper()->swap( $this->page(), 'sc1', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );
		$widget = $after[0]['elements'][0]['elements'][0];

		$this->assertSame( 'sc1', $widget['id'] );
		$this->assertSame( 'form', $widget['widgetType'] );
		$this->assertSame( 'Contact', $widget['settings']['form_name'] );
		$this->assertSame( 'contact-box', $widget['settings']['_css_classes'] );
		$this->assertSame( '20', $widget['settings']['_margin']['top'] );
		$this->assertSame( 'hidden-mobile', $widget['settings']['hide_mobile'] );
		$this->assertArrayNotHasKey( 'shortcode', $widget['settings'] );
	}

	/**
	 * Everything except the target element is unchanged.
	 *
	 * @return void
	 */
	public function test_other_elements_unchanged(): void {
		$before = $this->page();
		$after  = $this->swapper()->swap( $before, 'sc1', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );

		$this->assertSame( $before[1], $after[1] );
		$this->assertSame( $before[0]['settings'], $after[0]['settings'] );
		$this->assertSame( $before[0]['elements'][0]['elements'][1], $after[0]['elements'][0]['elements'][1] );
	}

	/**
	 * Mixed Text Editor content: only form 3's shortcode becomes the template shortcode.
	 *
	 * @return void
	 */
	public function test_inline_replace(): void {
		$after = $this->swapper()->swap( $this->page(), 'te1', ElementorLocation::INLINE_REPLACE, 3, self::FORM_WIDGET, 77 );

		$this->assertSame(
			'<p>Write to us:</p>[elementor-template id="77"]<p>[gravityform id="4"]</p>',
			$after[0]['elements'][0]['elements'][1]['settings']['editor']
		);
	}

	/**
	 * A deeply nested add-on widget is replaced.
	 *
	 * @return void
	 */
	public function test_nested_addon_widget(): void {
		$after = $this->swapper()->swap( $this->page(), 'ea1', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );

		$this->assertSame( 'form', $after[1]['elements'][0]['elements'][0]['widgetType'] );
		$this->assertSame( 'ea1', $after[1]['elements'][0]['elements'][0]['id'] );
	}

	/**
	 * Two embeds on one page are swapped one after the other.
	 *
	 * @return void
	 */
	public function test_two_embeds_on_one_page(): void {
		$after = $this->swapper()->swap( $this->page(), 'sc1', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );
		$after = $this->swapper()->swap( $after, 'ea1', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );

		$this->assertSame( 'form', $after[0]['elements'][0]['elements'][0]['widgetType'] );
		$this->assertSame( 'form', $after[1]['elements'][0]['elements'][0]['widgetType'] );
	}

	/**
	 * A missing element throws.
	 *
	 * @return void
	 */
	public function test_missing_element_throws(): void {
		$this->expectException( EmbedNotFound::class );

		$this->swapper()->swap( $this->page(), 'gone', ElementorLocation::WIDGET_REPLACE, 3, self::FORM_WIDGET, 0 );
	}

	/**
	 * An inline swap of a widget without the form's shortcode throws.
	 *
	 * @return void
	 */
	public function test_inline_without_shortcode_throws(): void {
		$this->expectException( EmbedNotFound::class );

		$this->swapper()->swap( $this->page(), 'te1', ElementorLocation::INLINE_REPLACE, 9, self::FORM_WIDGET, 77 );
	}
}
