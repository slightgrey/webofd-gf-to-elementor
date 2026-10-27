<?php
/**
 * Sanity checks for the Gravity Forms and Elementor fixtures.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every fixture decodes and has the parts the converters read.
 */
final class FixturesTest extends TestCase {

	/**
	 * Lists every Gravity Forms fixture file.
	 *
	 * @return array<string, array{string}> Name => [path].
	 */
	public static function gf_fixtures(): array {
		$files = glob( dirname( __DIR__ ) . '/fixtures/gf/*.json' );
		$cases = array();

		foreach ( (array) $files as $file ) {
			$cases[ basename( $file ) ] = array( $file );
		}

		return $cases;
	}

	/**
	 * A GF fixture has fields, notifications and confirmations.
	 *
	 * @param string $file Fixture path.
	 *
	 * @return void
	 */
	#[DataProvider( 'gf_fixtures' )]
	public function test_gf_fixture_has_required_parts( string $file ): void {
		$form = json_decode( (string) file_get_contents( $file ), true );

		$this->assertIsArray( $form );
		$this->assertNotEmpty( $form['fields'] );
		$this->assertArrayHasKey( 'notifications', $form );
		$this->assertArrayHasKey( 'confirmations', $form );
	}

	/**
	 * Six GF fixtures exist: the five real forms and the kitchen sink.
	 *
	 * @return void
	 */
	public function test_all_gf_fixtures_exist(): void {
		$this->assertCount( 6, self::gf_fixtures() );
	}

	/**
	 * The Elementor reference form is one container holding one form widget.
	 *
	 * @return void
	 */
	public function test_elementor_reference_form_shape(): void {
		$data = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor/reference-form.json' ), true );

		$this->assertSame( 'container', $data[0]['elType'] );
		$this->assertSame( 'form', $data[0]['elements'][0]['widgetType'] );
		$this->assertNotEmpty( $data[0]['elements'][0]['settings']['form_fields'] );
	}
}
