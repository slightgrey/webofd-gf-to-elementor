<?php
/**
 * Tests for MetaDetector.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Detector\EmbedMatch;
use GFToElementor\Detector\MetaDetector;
use GFToElementor\Detector\ShortcodeDetector;
use PHPUnit\Framework\TestCase;

/**
 * Meta fixtures from the PRD testing strategy.
 */
final class MetaDetectorTest extends TestCase {

	/**
	 * Detector under test.
	 *
	 * @var MetaDetector
	 */
	private MetaDetector $detector;

	/**
	 * Creates the detector and clears test filters.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['gf2el_test_filters'] = array();
		$this->detector               = new MetaDetector( new ShortcodeDetector() );
	}

	/**
	 * Clears test filters.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['gf2el_test_filters'] = array();
	}

	/**
	 * Returns "form_id|method|context" strings.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Raw value.
	 *
	 * @return array<int, string> Summaries.
	 */
	private function found( string $key, string $value ): array {
		return array_map(
			static fn( EmbedMatch $m ) => $m->form_id() . '|' . $m->embed_method() . '|' . $m->context(),
			$this->detector->detect( $key, $value )
		);
	}

	/**
	 * A serialized ACF flexible-content value with a shortcode is found.
	 *
	 * @return void
	 */
	public function test_serialized_acf_value(): void {
		$value = serialize(
			array(
				array( 'acf_fc_layout' => 'text', 'body' => 'Hello' ),
				array( 'acf_fc_layout' => 'form', 'body' => '<p>[gravityform id="3" ajax="true"]</p>' ),
			)
		);

		$this->assertSame( array( '3|meta|page_sections' ), $this->found( 'page_sections', $value ) );
	}

	/**
	 * A plain string value is found.
	 *
	 * @return void
	 */
	public function test_plain_string(): void {
		$this->assertSame( array( '4|meta|cta_text' ), $this->found( 'cta_text', 'Sign up: [gravityforms id="4"]' ) );
	}

	/**
	 * JSON-escaped quotes are unescaped before parsing.
	 *
	 * @return void
	 */
	public function test_json_escaped_value(): void {
		$value = (string) json_encode( array( 'html' => '[gravityform id="12" title="false"]' ) );

		$this->assertSame( array( '12|meta|builder_json' ), $this->found( 'builder_json', $value ) );
	}

	/**
	 * Elementor data and caches are skipped even if they contain a shortcode.
	 *
	 * @return void
	 */
	public function test_skipped_keys(): void {
		$this->assertSame( array(), $this->found( '_elementor_data', '[{"settings":{"shortcode":"[gravityform id=\"1\"]"}}]' ) );
		$this->assertSame( array(), $this->found( '_elementor_element_cache', '[gravityform id="1"]' ) );
		$this->assertTrue( $this->detector->is_skipped( '_edit_lock' ) );
	}

	/**
	 * The plugin's own meta (template source form, fixture tags) is never reported.
	 *
	 * @return void
	 */
	public function test_own_meta_is_skipped(): void {
		$this->assertSame( array(), $this->found( '_gf2el_source_form', '[gravityform id="1"]' ) );
		$this->assertTrue( $this->detector->is_skipped( '_gf2el_fixture' ) );
	}

	/**
	 * The skip list can be changed with the gf2el_skip_meta_keys filter.
	 *
	 * @return void
	 */
	public function test_skip_list_is_filterable(): void {
		$GLOBALS['gf2el_test_filters']['gf2el_skip_meta_keys'] = static fn( array $keys ) => array_merge( $keys, array( 'private_notes' ) );

		$this->assertSame( array(), $this->found( 'private_notes', '[gravityform id="1"]' ) );
		$this->assertSame( array( '1|meta|public_notes' ), $this->found( 'public_notes', '[gravityform id="1"]' ) );
	}
}
