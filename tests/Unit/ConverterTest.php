<?php
/**
 * Tests for the Converter facade.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ConversionContext;
use GFToElementor\Convert\Converter;
use GFToElementor\Convert\ResultStatus;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Whole-form conversion of the real forms and the kitchen sink.
 */
final class ConverterTest extends FieldMapperTestCase {

	/**
	 * Builds a test context.
	 *
	 * @param array<int, string>  $feeds Feed slugs.
	 * @param array<string, bool> $keys  reCAPTCHA key status.
	 *
	 * @return ConversionContext Context.
	 */
	private function context( array $feeds = array(), array $keys = array() ): ConversionContext {
		$counter = 0;

		return new ConversionContext(
			'admin@example.test',
			static fn( int $id ): string => 'https://example.test/?page_id=' . $id,
			static function () use ( &$counter ): string {
				++$counter;
				return sprintf( '%07x', $counter );
			},
			$keys,
			$feeds
		);
	}

	/**
	 * Lists the five real forms.
	 *
	 * @return array<string, array{string}> Cases.
	 */
	public static function real_forms(): array {
		return array(
			'form-1' => array( 'form-1' ),
			'form-2' => array( 'form-2' ),
			'form-3' => array( 'form-3' ),
			'form-4' => array( 'form-4' ),
			'form-5' => array( 'form-5' ),
		);
	}

	/**
	 * Each real form converts with nothing dropped, one email action and no warnings.
	 *
	 * @param string $name Fixture name.
	 *
	 * @return void
	 */
	#[DataProvider( 'real_forms' )]
	public function test_real_forms_convert_cleanly( string $name ): void {
		$result = Converter::create()->convert( $this->form( $name ), $this->context() );

		$this->assertSame( 0, $result->counts()['dropped'] );
		$this->assertSame( array( 'save-to-database', 'email' ), $result->widget['settings']['submit_actions'] );
		$this->assertSame( array(), $result->warnings );
	}

	/**
	 * Every repeater item and element gets a distinct random ID.
	 *
	 * @return void
	 */
	public function test_ids_are_unique(): void {
		$result = Converter::create()->convert( $this->form( 'kitchen-sink' ), $this->context() );
		$ids    = array_column( $result->widget['settings']['form_fields'], '_id' );
		$ids[]  = $result->widget['id'];

		$this->assertSame( $ids, array_unique( $ids ) );
		$this->assertSame(
			array_column( $result->widget['settings']['form_fields'], 'custom_id' ),
			array_unique( array_column( $result->widget['settings']['form_fields'], 'custom_id' ) )
		);
	}

	/**
	 * The kitchen sink warns about feeds, missing reCAPTCHA keys and dropped items.
	 *
	 * @return void
	 */
	public function test_kitchen_sink_warnings(): void {
		$result = Converter::create()->convert( $this->form( 'kitchen-sink' ), $this->context( array( 'gravityformsmailchimp' ) ) );

		$this->assertCount( 3, $result->warnings );
		$this->assertStringContainsString( 'gravityformsmailchimp', $result->warnings[0] );
		$this->assertStringContainsString( 'reCAPTCHA keys are not set', $result->warnings[1] );
		$this->assertMatchesRegularExpression( '/^\d+ items could not be carried over/', $result->warnings[2] );
	}

	/**
	 * With reCAPTCHA keys set, there is no CAPTCHA warning.
	 *
	 * @return void
	 */
	public function test_no_captcha_warning_with_keys(): void {
		$result = Converter::create()->convert( $this->form( 'kitchen-sink' ), $this->context( array(), array( 'recaptcha' => true ) ) );

		$this->assertCount( 1, $result->warnings );
	}

	/**
	 * Button conditional logic and form-level settings are reported.
	 *
	 * @return void
	 */
	public function test_form_settings_are_reported(): void {
		$form                 = $this->form( 'kitchen-sink' );
		$form['requireLogin'] = true;
		$form['save']         = array( 'enabled' => true );
		$result               = Converter::create()->convert( $form, $this->context() );
		$form_entries         = array_values( array_filter( $result->entries, static fn( $entry ): bool => 'form' === $entry->subject && ResultStatus::Dropped === $entry->status ) );

		$this->assertSame( array( 'Require login', 'Save and Continue', 'Submit button' ), array_column( array_map( static fn( $entry ): array => $entry->to_array(), $form_entries ), 'gf_ref' ) );
	}

	/**
	 * The field map lets merge tags reach the right fields, and the report round-trips.
	 *
	 * @return void
	 */
	public function test_field_map_and_report(): void {
		$result = Converter::create()->convert( $this->form( 'form-4' ), $this->context() );
		$report = $result->to_report();

		$this->assertSame( 'your_name_first', $result->field_map['11.3'] );
		$this->assertSame( $result->counts(), $report['counts'] );
		$this->assertSame( 'form', $report['widget']['widgetType'] );
		$this->assertSame( count( $result->entries ), count( $report['entries'] ) );
	}
}
