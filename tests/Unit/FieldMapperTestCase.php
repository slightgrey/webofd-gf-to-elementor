<?php
/**
 * Shared helpers for FieldMapper tests.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\FieldMapper;
use GFToElementor\Convert\FieldResult;
use GFToElementor\Convert\IdGenerator;
use GFToElementor\Convert\ItemFactory;
use GFToElementor\Convert\ReportEntry;
use PHPUnit\Framework\TestCase;

/**
 * Builds mappers with predictable IDs and loads GF fixtures.
 */
abstract class FieldMapperTestCase extends TestCase {

	/**
	 * custom_id generator of the last mapper built.
	 *
	 * @var IdGenerator
	 */
	protected IdGenerator $ids;

	/**
	 * Builds a mapper whose random IDs are r1, r2, r3...
	 *
	 * @return FieldMapper Mapper.
	 */
	protected function mapper(): FieldMapper {
		$counter   = 0;
		$this->ids = new IdGenerator();
		$countries = require dirname( __DIR__, 2 ) . '/src/Convert/data/countries.php';

		return new FieldMapper(
			$this->ids,
			new ItemFactory(
				static function () use ( &$counter ): string {
					++$counter;
					return 'r' . $counter;
				}
			),
			$countries
		);
	}

	/**
	 * Loads a GF fixture form.
	 *
	 * @param string $name File name without extension, e.g. "form-4" or "kitchen-sink".
	 *
	 * @return array<string, mixed> Form.
	 */
	protected function form( string $name ): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/gf/' . $name . '.json' ), true );
	}

	/**
	 * Returns one field of a fixture form by GF ID.
	 *
	 * @param string $form_name Fixture name.
	 * @param int    $field_id  GF field ID.
	 *
	 * @return array<string, mixed> Field.
	 */
	protected function field( string $form_name, int $field_id ): array {
		foreach ( $this->form( $form_name )['fields'] as $field ) {
			if ( $field_id === (int) $field['id'] ) {
				return $field;
			}
		}

		$this->fail( "Field $field_id not found in $form_name." );
	}

	/**
	 * Maps a single field (no other fields in the form).
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return FieldResult Result.
	 */
	protected function map_one( array $field ): FieldResult {
		return $this->mapper()->map_field( $field, array( $field ) );
	}

	/**
	 * Returns the first report entry with the given subject.
	 *
	 * @param FieldResult $result  Result.
	 * @param string      $subject Entry subject.
	 *
	 * @return ReportEntry Entry.
	 */
	protected function entry( FieldResult $result, string $subject = 'field' ): ReportEntry {
		foreach ( $result->entries as $entry ) {
			if ( $subject === $entry->subject ) {
				return $entry;
			}
		}

		$this->fail( "No $subject entry." );
	}
}
