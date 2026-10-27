<?php
/**
 * Tests for the Capability helper.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Capability;
use PHPUnit\Framework\TestCase;

/**
 * Capability::required() honours the gf2el_capability filter and rejects junk values.
 */
final class CapabilityTest extends TestCase {

	/**
	 * Removes test filters after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['gf2el_test_filters'] = array();
	}

	/**
	 * Defaults to manage_options.
	 *
	 * @return void
	 */
	public function test_default_is_manage_options(): void {
		$this->assertSame( 'manage_options', Capability::required() );
	}

	/**
	 * A filtered capability is returned.
	 *
	 * @return void
	 */
	public function test_filtered_capability_is_used(): void {
		$GLOBALS['gf2el_test_filters']['gf2el_capability'] = static fn() => 'edit_pages';

		$this->assertSame( 'edit_pages', Capability::required() );
	}

	/**
	 * Empty or non-string filter results fall back to manage_options.
	 *
	 * @return void
	 */
	public function test_invalid_filter_result_falls_back(): void {
		$GLOBALS['gf2el_test_filters']['gf2el_capability'] = static fn() => array( 'nope' );
		$this->assertSame( 'manage_options', Capability::required() );

		$GLOBALS['gf2el_test_filters']['gf2el_capability'] = static fn() => '';
		$this->assertSame( 'manage_options', Capability::required() );
	}
}
