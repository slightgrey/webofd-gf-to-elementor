<?php
/**
 * Converts a whole Gravity Forms form into an Elementor Pro Form widget.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Runs the field, notification and confirmation mappers, reports form-level
 * settings Elementor can't reproduce, and collects headline warnings.
 */
final class Converter {

	/**
	 * Creates the converter.
	 *
	 * @param array<string, string> $countries Country code => name, for address fields.
	 */
	public function __construct( private readonly array $countries ) {
	}

	/**
	 * Builds a converter with the bundled country list.
	 *
	 * @return self Converter.
	 */
	public static function create(): self {
		return new self( require __DIR__ . '/data/countries.php' );
	}

	/**
	 * Converts a form.
	 *
	 * @param array<string, mixed> $form    Normalized GF form.
	 * @param ConversionContext    $context Site facts.
	 *
	 * @return ConversionResult Widget, field map, entries and warnings.
	 */
	public function convert( array $form, ConversionContext $context ): ConversionResult {
		$ids    = new IdGenerator();
		$items  = new ItemFactory( $context->random_id );
		$fields = ( new FieldMapper( $ids, $items, $this->countries ) )->map_form( $form );
		$tags   = new MergeTagConverter( $ids->map(), $context->admin_email, ItemFactory::plain( (string) ( $form['title'] ?? '' ) ) );
		$logic  = new ConditionalLogicDescriber();
		$all    = is_array( $form['fields'] ?? null ) ? $form['fields'] : array();
		$emails = ( new NotificationMapper( $tags, $logic, $all ) )->map( (array) ( $form['notifications'] ?? array() ) );
		$thanks = ( new ConfirmationMapper( $tags, $logic, $all, $context->permalink ) )->map( (array) ( $form['confirmations'] ?? array() ) );
		$widget = ( new FormWidgetBuilder( $items ) )->build( $form, $fields, $emails, $thanks );

		$entries = array_merge( $fields->entries, $emails->entries, $thanks->entries, ( new FormSettingsReporter( $logic ) )->entries( $form ) );

		return new ConversionResult( $widget, $ids->map(), $entries, $this->warnings( $fields, $entries, $context ) );
	}

	/**
	 * Builds the headline warnings shown above the preview.
	 *
	 * @param FieldResult             $fields  Mapped fields.
	 * @param array<int, ReportEntry> $entries All entries.
	 * @param ConversionContext       $context Site facts.
	 *
	 * @return array<int, string> Warnings.
	 */
	private function warnings( FieldResult $fields, array $entries, ConversionContext $context ): array {
		$warnings = array();

		if ( array() !== $context->feeds ) {
			/* translators: %s: comma-separated add-on names, e.g. "gravityformsmailchimp". */
			$warnings[] = sprintf( __( 'This form has add-on feeds (%s). They are not migrated; set up the matching Elementor actions by hand.', 'webofd-gf-to-elementor' ), implode( ', ', $context->feeds ) );
		}

		foreach ( $fields->requirements as $requirement ) {
			if ( empty( $context->recaptcha_keys[ $requirement ] ) ) {
				/* translators: %s: "reCAPTCHA" or "reCAPTCHA v3". */
				$warnings[] = sprintf( __( 'The %s keys are not set in Elementor → Settings → Integrations, so the CAPTCHA will not work until they are.', 'webofd-gf-to-elementor' ), 'recaptcha_v3' === $requirement ? 'reCAPTCHA v3' : 'reCAPTCHA' );
			}
		}

		$dropped = count( array_filter( $entries, static fn( ReportEntry $entry ): bool => ResultStatus::Dropped === $entry->status ) );

		if ( $dropped > 0 ) {
			/* translators: %d: number of items. */
			$warnings[] = sprintf( _n( '%d item could not be carried over; see the details below.', '%d items could not be carried over; see the details below.', $dropped, 'webofd-gf-to-elementor' ), $dropped );
		}

		return $warnings;
	}
}
