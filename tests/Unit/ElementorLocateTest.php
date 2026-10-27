<?php
/**
 * Tests for ElementorDetector::locate().
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Detector\ElementorDetector;
use GFToElementor\Detector\ElementorLocation;
use GFToElementor\Detector\ShortcodeDetector;
use PHPUnit\Framework\TestCase;

/**
 * locate() finds one form's widgets at any depth and picks whole-widget or inline swaps.
 */
final class ElementorLocateTest extends TestCase {

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
	 * Wraps children in a layout element.
	 *
	 * @param string                           $el_type  section, column or container.
	 * @param array<int, array<string, mixed>> $children Children.
	 *
	 * @return array<string, mixed> Element.
	 */
	private function layout( string $el_type, array $children ): array {
		return array(
			'id'       => $el_type . count( $children ),
			'elType'   => $el_type,
			'settings' => array(),
			'elements' => $children,
		);
	}

	/**
	 * Runs locate() and summarises the results as "id:action".
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @param int                              $form_id  Form ID.
	 *
	 * @return array<int, string> Summaries.
	 */
	private function located( array $elements, int $form_id = 3 ): array {
		$locations = ( new ElementorDetector( new ShortcodeDetector() ) )->locate( $elements, $form_id );

		return array_map( static fn( ElementorLocation $location ): string => $location->element_id . ':' . $location->action, $locations );
	}

	/**
	 * Whole-widget cases: Shortcode widget, Text Editor holding only the shortcode, add-on widget.
	 *
	 * @return void
	 */
	public function test_whole_widget_replacements(): void {
		$elements = array(
			$this->layout(
				'section',
				array(
					$this->layout(
						'column',
						array(
							$this->widget( 'sc1', 'shortcode', array( 'shortcode' => ' [gravityform id="3" title="false"] ' ) ),
							$this->widget( 'te1', 'text-editor', array( 'editor' => "<p>[gravityform id=\"3\"]</p>\n<p>&nbsp;</p>" ) ),
							$this->widget( 'ea1', 'eael-gravity-form', array( 'contact_form_list' => '3' ) ),
						)
					),
				)
			),
		);

		$this->assertSame( array( 'sc1:widget_replace', 'te1:widget_replace', 'ea1:widget_replace' ), $this->located( $elements ) );
	}

	/**
	 * Inline cases: text around the shortcode, or two GF shortcodes in one widget.
	 *
	 * @return void
	 */
	public function test_inline_replacements(): void {
		$elements = array(
			$this->layout(
				'container',
				array(
					$this->widget( 'te2', 'text-editor', array( 'editor' => '<p>Get in touch:</p>[gravityform id="3"]' ) ),
					$this->widget( 'te3', 'text-editor', array( 'editor' => '[gravityform id="3"][gravityform id="4"]' ) ),
					$this->widget( 'tab', 'tabs', array( 'tabs' => array( array( 'tab_content' => '[gravityform id="3"]' ) ) ) ),
				)
			),
		);

		$this->assertSame( array( 'te2:inline_replace', 'te3:inline_replace', 'tab:inline_replace' ), $this->located( $elements ) );
	}

	/**
	 * Other forms, deeply nested containers and malformed elements.
	 *
	 * @return void
	 */
	public function test_other_forms_and_nesting(): void {
		$deep     = $this->layout( 'container', array( $this->layout( 'container', array( $this->layout( 'container', array( $this->widget( 'deep', 'shortcode', array( 'shortcode' => '[gravityforms id=3]' ) ) ) ) ) ) ) );
		$elements = array( $deep, $this->widget( 'other', 'shortcode', array( 'shortcode' => '[gravityform id="4"]' ) ), 'junk', array( 'elType' => 'widget' ) );

		$this->assertSame( array( 'deep:widget_replace' ), $this->located( $elements ) );
		$this->assertSame( array( 'other:widget_replace' ), $this->located( $elements, 4 ) );
	}
}
