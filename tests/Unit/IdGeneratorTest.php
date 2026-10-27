<?php
/**
 * Tests for IdGenerator.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\IdGenerator;
use PHPUnit\Framework\TestCase;

/**
 * custom_ids are slugged from labels, unique, and mapped back to GF input IDs.
 */
final class IdGeneratorTest extends TestCase {

	/**
	 * Labels become lower-case underscore slugs; accents are transliterated.
	 *
	 * @return void
	 */
	public function test_label_is_slugged(): void {
		$ids = new IdGenerator();

		$this->assertSame( 'mobile_telephone', $ids->for_field( '4', 'Mobile Telephone' ) );
		$this->assertSame( 'i_am_looking_to_rent_in', $ids->for_field( '15', 'I am looking to rent in:' ) );
		$this->assertSame( 'creme_brulee', $ids->for_field( '9', 'Crème <b>brûlée</b>' ) );
	}

	/**
	 * Duplicate labels get _2, _3 suffixes, as with the three "Phone"-ish labels on the real forms.
	 *
	 * @return void
	 */
	public function test_duplicates_get_numbered(): void {
		$ids = new IdGenerator();

		$this->assertSame( 'phone', $ids->for_field( '3', 'Phone' ) );
		$this->assertSame( 'phone_2', $ids->for_field( '4', 'Phone' ) );
		$this->assertSame( 'phone_3', $ids->for_field( '5', 'phone' ) );
	}

	/**
	 * Sub-inputs combine the field label with a suffix, and collide safely with whole fields.
	 *
	 * @return void
	 */
	public function test_sub_inputs(): void {
		$ids = new IdGenerator();

		$this->assertSame( 'your_name_first', $ids->for_input( '1.3', 'Your Name', 'First' ) );
		$this->assertSame( 'your_name_last', $ids->for_input( '1.6', 'Your Name', 'last' ) );
		$this->assertSame( 'your_name_first_2', $ids->for_field( '9', 'Your Name First' ) );
		$this->assertSame(
			array(
				'1.3' => 'your_name_first',
				'1.6' => 'your_name_last',
				'9'   => 'your_name_first_2',
			),
			$ids->map()
		);
	}

	/**
	 * Empty and non-Latin labels fall back to field_{id}.
	 *
	 * @return void
	 */
	public function test_fallbacks(): void {
		$ids = new IdGenerator();

		$this->assertSame( 'field_7', $ids->for_field( '7', '' ) );
		$this->assertSame( 'field_8', $ids->for_field( '8', 'Имя' ) );
		$this->assertSame( 'field_10', $ids->for_field( '10', '2024' ) );
		$this->assertSame( 'field_11_city', $ids->for_input( '11.3', 'Адрес', 'City' ) );
	}

	/**
	 * Long labels are cut to 40 characters without a trailing underscore.
	 *
	 * @return void
	 */
	public function test_long_label_is_trimmed(): void {
		$ids = new IdGenerator();
		$id  = $ids->for_field( '1', str_repeat( 'word ', 16 ) );

		$this->assertLessThanOrEqual( 40, strlen( $id ) );
		$this->assertStringEndsNotWith( '_', $id );
	}

	/**
	 * Aliases map extra GF input IDs onto an existing custom_id.
	 *
	 * @return void
	 */
	public function test_alias(): void {
		$ids = new IdGenerator();
		$id  = $ids->for_field( '11', 'Features' );
		$ids->alias( '11.1', $id );

		$this->assertSame( 'features', $ids->map()['11.1'] );
	}
}
