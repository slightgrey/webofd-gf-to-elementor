<?php
/**
 * Tests for FormWidgetBuilder.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ConditionalLogicDescriber;
use GFToElementor\Convert\ConfirmationMapper;
use GFToElementor\Convert\FormWidgetBuilder;
use GFToElementor\Convert\ItemFactory;
use GFToElementor\Convert\MergeTagConverter;
use GFToElementor\Convert\NotificationMapper;

/**
 * The built widget only uses settings Elementor Pro actually registers.
 */
final class FormWidgetBuilderTest extends FieldMapperTestCase {

	/**
	 * Builds the widget for a fixture form.
	 *
	 * @param string $name Fixture name.
	 *
	 * @return array<string, mixed> Widget element.
	 */
	private function build( string $name ): array {
		$form   = $this->form( $name );
		$fields = $this->mapper()->map_form( $form );
		$tags   = new MergeTagConverter( $this->ids->map(), 'admin@example.test', (string) $form['title'] );
		$logic  = new ConditionalLogicDescriber();
		$notes  = ( new NotificationMapper( $tags, $logic, $form['fields'] ) )->map( $form['notifications'] );
		$conf   = ( new ConfirmationMapper( $tags, $logic, $form['fields'], static fn(): string => 'https://example.test/thanks/' ) )->map( $form['confirmations'] );

		return ( new FormWidgetBuilder( new ItemFactory( static fn(): string => 'abc1234' ) ) )->build( $form, $fields, $notes, $conf );
	}

	/**
	 * Loads the control names Elementor Pro registers for the form widget and its fields.
	 *
	 * @return array{widget: array<int, string>, field: array<int, string>} Names.
	 */
	private function control_names(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor/form-control-names.json' ), true );
	}

	/**
	 * Every widget setting and field item key is a real Elementor control (or the repeater _id).
	 *
	 * @return void
	 */
	public function test_keys_are_real_controls(): void {
		$widget   = $this->build( 'kitchen-sink' );
		$controls = $this->control_names();
		$item_key = array();

		foreach ( $widget['settings']['form_fields'] as $item ) {
			$item_key = array_merge( $item_key, array_keys( $item ) );
		}

		$this->assertSame( array(), array_values( array_diff( array_keys( $widget['settings'] ), $controls['widget'] ) ) );
		$this->assertSame( array(), array_values( array_diff( array_unique( $item_key ), $controls['field'], array( '_id' ) ) ) );
	}

	/**
	 * The element is a form widget; Collect Submissions comes first, then emails, then redirect.
	 *
	 * @return void
	 */
	public function test_element_and_actions(): void {
		$widget = $this->build( 'kitchen-sink' );

		$this->assertSame( 'abc1234', $widget['id'] );
		$this->assertSame( 'widget', $widget['elType'] );
		$this->assertSame( 'form', $widget['widgetType'] );
		$this->assertSame( array( 'save-to-database', 'email', 'email2', 'redirect' ), $widget['settings']['submit_actions'] );
		$this->assertSame( 'Send it', $widget['settings']['button_text'] );
		$this->assertSame( 'Kitchen Sink', $widget['settings']['form_name'] );
	}

	/**
	 * A real form always collects submissions.
	 *
	 * @return void
	 */
	public function test_real_form_collects_submissions(): void {
		$widget = $this->build( 'form-2' );

		$this->assertSame( array( 'save-to-database', 'email' ), $widget['settings']['submit_actions'] );
		$this->assertSame( 'Submit', $widget['settings']['button_text'] );
	}
}
