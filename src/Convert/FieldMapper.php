<?php
/**
 * Converts Gravity Forms fields into Elementor form fields.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Maps every GF field of a form to Elementor form_fields repeater items, and records
 * a report entry for each one: Converted, Approximated (with why) or Dropped.
 */
final class FieldMapper {

	/**
	 * GF types that map onto one Elementor field of a fixed type.
	 */
	private const SIMPLE_TYPES = array(
		'text'     => 'text',
		'textarea' => 'textarea',
		'website'  => 'url',
		'hidden'   => 'hidden',
	);

	/**
	 * GF pricing field types.
	 */
	private const PRICING_TYPES = array( 'product', 'option', 'quantity', 'shipping', 'total', 'donation' );

	/**
	 * GF types with no Elementor equivalent at all.
	 */
	private const DROPPED_TYPES = array( 'signature', 'creditcard', 'repeater' );

	/**
	 * GF types that inputType-based fields (survey, quiz, poll, post fields...) can fall back to.
	 */
	private const FALLBACK_INPUT_TYPES = array( 'text', 'textarea', 'select', 'multiselect', 'radio', 'checkbox', 'number', 'email', 'date', 'time', 'hidden', 'website', 'phone' );

	/**
	 * GF field types whose description is part of the converted output.
	 */
	private const DESCRIPTION_KEPT_TYPES = array( 'section', 'html', 'page' );

	/**
	 * Name and address mapper.
	 *
	 * @var CompositeMapper
	 */
	private CompositeMapper $composite;

	/**
	 * Pricing field mapper.
	 *
	 * @var PricingMapper
	 */
	private PricingMapper $pricing;

	/**
	 * Conditional logic describer.
	 *
	 * @var ConditionalLogicDescriber
	 */
	private ConditionalLogicDescriber $logic;

	/**
	 * Creates the mapper.
	 *
	 * @param IdGenerator           $ids       custom_id generator (shared with the merge tag converter).
	 * @param ItemFactory           $items     Repeater item factory.
	 * @param array<string, string> $countries Country code => name, for address fields.
	 */
	public function __construct(
		private readonly IdGenerator $ids,
		private readonly ItemFactory $items,
		array $countries
	) {
		$this->composite = new CompositeMapper( $ids, $items, $countries );
		$this->pricing   = new PricingMapper( $ids, $items );
		$this->logic     = new ConditionalLogicDescriber();
	}

	/**
	 * Maps every field of a form, adding Elementor steps for GF pages and a honeypot if enabled.
	 *
	 * @param array<string, mixed> $form Normalized GF form.
	 *
	 * @return FieldResult Items, report entries and requirements.
	 */
	public function map_form( array $form ): FieldResult {
		$fields = is_array( $form['fields'] ?? null ) ? $form['fields'] : array();
		$steps  = new StepPlan( $form );
		$result = $steps->has_pages() ? $this->step( $steps, 0, null ) : new FieldResult();

		foreach ( $fields as $field ) {
			$result = $result->merge(
				'page' === ( $field['type'] ?? '' )
					? $this->step( $steps, $steps->index_of( $field ), $field, $fields )
					: $this->map_field( $field, $fields )
			);
		}

		return $this->with_honeypot( $result, $form );
	}

	/**
	 * Maps one non-page field and builds its report entries.
	 *
	 * @param array<string, mixed>             $field  GF field.
	 * @param array<int, array<string, mixed>> $fields All form fields, for conditional logic labels.
	 *
	 * @return FieldResult Result for this field.
	 */
	public function map_field( array $field, array $fields ): FieldResult {
		$mapped = $this->apply_common_notes( $this->dispatch( $field ), $field );

		return new FieldResult( $mapped->items, $this->entries( $field, $mapped, $fields, 'field' ), $mapped->requirements );
	}

	/**
	 * Picks the handler for a GF field type.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return Mapped Result.
	 */
	private function dispatch( array $field ): Mapped {
		$type = (string) ( $field['type'] ?? '' );

		if ( isset( self::SIMPLE_TYPES[ $type ] ) ) {
			return $this->simple( $field, self::SIMPLE_TYPES[ $type ] );
		}

		if ( in_array( $type, self::PRICING_TYPES, true ) ) {
			return $this->pricing->map( $field );
		}

		return match ( $type ) {
			'email'       => $this->email( $field ),
			'phone'       => $this->phone( $field ),
			'number'      => $this->number( $field ),
			'password'    => $this->password( $field ),
			'select'      => $this->select( $field, false ),
			'multiselect' => $this->select( $field, true ),
			'radio'       => $this->radio_checkbox( $field, 'radio' ),
			'checkbox'    => $this->radio_checkbox( $field, 'checkbox' ),
			'date'        => $this->date( $field ),
			'time'        => $this->time( $field ),
			'html'        => $this->html( $field ),
			'section'     => $this->section( $field ),
			'fileupload'  => $this->upload( $field ),
			'consent'     => $this->consent( $field ),
			'captcha'     => $this->captcha( $field ),
			'name'        => $this->composite->name( $field ),
			'address'     => $this->composite->address( $field ),
			'list'        => $this->simple( $field, 'textarea' )->with_note( __( 'List field became a plain text area; visitors type one item per line.', 'webofd-gf-to-elementor' ) ),
			default       => $this->fallback( $field ),
		};
	}

	/**
	 * Maps a field onto one Elementor field of the given type.
	 *
	 * @param array<string, mixed> $field     GF field.
	 * @param string               $type      Elementor type.
	 * @param array<string, mixed> $overrides Extra item settings.
	 *
	 * @return Mapped Result.
	 */
	private function simple( array $field, string $type, array $overrides = array() ): Mapped {
		$id = $this->field_id( $field );
		$this->alias_inputs( $field, $id );

		return new Mapped( array( $this->items->make( $type, $id, $field, $overrides ) ) );
	}

	/**
	 * Maps an email field; a confirmation input is dropped.
	 *
	 * @param array<string, mixed> $field GF email field.
	 *
	 * @return Mapped Result.
	 */
	private function email( array $field ): Mapped {
		$mapped = $this->simple( $field, 'email' );

		return empty( $field['emailConfirmEnabled'] ) ? $mapped : $mapped->with_note( __( 'The "confirm email" box was dropped; visitors enter their email once.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps a phone field; the US input mask is not enforced.
	 *
	 * @param array<string, mixed> $field GF phone field.
	 *
	 * @return Mapped Result.
	 */
	private function phone( array $field ): Mapped {
		$mapped = $this->simple( $field, 'tel' );

		return 'standard' === ( $field['phoneFormat'] ?? '' ) ? $mapped->with_note( __( 'The US (###) ###-#### format is no longer enforced.', 'webofd-gf-to-elementor' ) ) : $mapped;
	}

	/**
	 * Maps a number field with its range.
	 *
	 * @param array<string, mixed> $field GF number field.
	 *
	 * @return Mapped Result.
	 */
	private function number( array $field ): Mapped {
		$overrides = array_filter(
			array(
				'field_min' => is_numeric( $field['rangeMin'] ?? null ) ? $field['rangeMin'] + 0 : null,
				'field_max' => is_numeric( $field['rangeMax'] ?? null ) ? $field['rangeMax'] + 0 : null,
			),
			static fn( $value ): bool => null !== $value
		);
		$mapped    = $this->simple( $field, 'number', $overrides );

		if ( ! empty( $field['enableCalculation'] ) ) {
			$mapped = $mapped->with_note( __( 'The calculation formula was dropped; visitors type the number.', 'webofd-gf-to-elementor' ) );
		}

		return 'currency' === ( $field['numberFormat'] ?? '' ) ? $mapped->with_note( __( 'Currency formatting was dropped.', 'webofd-gf-to-elementor' ) ) : $mapped;
	}

	/**
	 * Maps a password field.
	 *
	 * @param array<string, mixed> $field GF password field.
	 *
	 * @return Mapped Result.
	 */
	private function password( array $field ): Mapped {
		$mapped = $this->simple( $field, 'password' )->with_note( __( 'Elementor stores the password in plain text with the submission.', 'webofd-gf-to-elementor' ) );

		return count( (array) ( $field['inputs'] ?? array() ) ) > 1 ? $mapped->with_note( __( 'The "confirm password" box was dropped.', 'webofd-gf-to-elementor' ) ) : $mapped;
	}

	/**
	 * Maps a drop-down or multi-select; a GF placeholder becomes an empty first option.
	 *
	 * @param array<string, mixed> $field    GF field.
	 * @param bool                 $multiple Whether several options can be chosen.
	 *
	 * @return Mapped Result.
	 */
	private function select( array $field, bool $multiple ): Mapped {
		$options     = Choices::options( $field['choices'] ?? array() );
		$placeholder = ItemFactory::plain( (string) ( $field['placeholder'] ?? '' ) );
		$selected    = Choices::selected_values( $field['choices'] ?? array() );
		$overrides   = array(
			'field_options' => '' === $placeholder ? $options : $placeholder . "|\n" . $options,
			'field_value'   => $multiple ? implode( ',', $selected ) : (string) ( $selected[0] ?? '' ),
		);

		if ( $multiple ) {
			$overrides['allow_multiple'] = 'true';
		}

		return $this->simple( $field, 'select', array_filter( $overrides, static fn( $value ): bool => '' !== $value ) );
	}

	/**
	 * Maps radio buttons or checkboxes.
	 *
	 * @param array<string, mixed> $field GF field.
	 * @param string               $type  "radio" or "checkbox".
	 *
	 * @return Mapped Result.
	 */
	private function radio_checkbox( array $field, string $type ): Mapped {
		$selected = Choices::selected_values( $field['choices'] ?? array() );
		$mapped   = $this->simple(
			$field,
			$type,
			array_filter(
				array(
					'field_options' => Choices::options( $field['choices'] ?? array() ),
					'field_value'   => (string) ( $selected[0] ?? '' ),
				)
			)
		);

		if ( count( $selected ) > 1 ) {
			$mapped = $mapped->with_note( __( 'Only the first pre-selected option stays selected.', 'webofd-gf-to-elementor' ) );
		}

		return empty( $field['enableOtherChoice'] ) ? $mapped : $mapped->with_note( __( 'The "Other" choice with its own text box was dropped.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps a date field; non-picker styles become a date picker.
	 *
	 * @param array<string, mixed> $field GF date field.
	 *
	 * @return Mapped Result.
	 */
	private function date( array $field ): Mapped {
		$mapped = $this->simple( $field, 'date' );

		return 'datepicker' === ( $field['dateType'] ?? 'datepicker' ) ? $mapped : $mapped->with_note( __( 'Separate day/month/year inputs became a single date picker.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps a time field; GF's separate hour/minute inputs become one time picker.
	 *
	 * @param array<string, mixed> $field GF time field.
	 *
	 * @return Mapped Result.
	 */
	private function time( array $field ): Mapped {
		return $this->simple( $field, 'time' )->with_note( __( 'Separate hour/minute inputs became a single time picker.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps an HTML block.
	 *
	 * @param array<string, mixed> $field GF HTML field.
	 *
	 * @return Mapped Result.
	 */
	private function html( array $field ): Mapped {
		return $this->simple( $field, 'html', array( 'field_html' => (string) ( $field['content'] ?? '' ) ) );
	}

	/**
	 * Maps a section break to an HTML heading.
	 *
	 * @param array<string, mixed> $field GF section field.
	 *
	 * @return Mapped Result.
	 */
	private function section( array $field ): Mapped {
		$description = trim( (string) ( $field['description'] ?? '' ) );
		$html        = '<h3>' . esc_html( ItemFactory::plain( (string) ( $field['label'] ?? '' ) ) ) . '</h3>';

		if ( '' !== $description ) {
			$html .= '<p>' . wp_kses_post( $description ) . '</p>';
		}

		return $this->simple( $field, 'html', array( 'field_html' => $html ) )->with_note( __( 'Section break shown as an HTML heading.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Maps a file upload with its allowed types, size and count.
	 *
	 * @param array<string, mixed> $field GF file upload field.
	 *
	 * @return Mapped Result.
	 */
	private function upload( array $field ): Mapped {
		$types    = preg_split( '/[\s,]+/', strtolower( str_replace( '.', '', (string) ( $field['allowedExtensions'] ?? '' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		$multiple = ! empty( $field['multipleFiles'] );

		return $this->simple(
			$field,
			'upload',
			array_filter(
				array(
					'file_types'            => implode( ',', (array) $types ),
					'file_sizes'            => is_numeric( $field['maxFileSize'] ?? null ) ? (int) $field['maxFileSize'] : null,
					'allow_multiple_upload' => $multiple ? 'yes' : null,
					'max_files'             => $multiple && is_numeric( $field['maxFiles'] ?? null ) ? (int) $field['maxFiles'] : null,
				)
			)
		);
	}

	/**
	 * Maps a consent checkbox to an acceptance field.
	 *
	 * @param array<string, mixed> $field GF consent field.
	 *
	 * @return Mapped Result.
	 */
	private function consent( array $field ): Mapped {
		return $this->simple( $field, 'acceptance', array( 'acceptance_text' => wp_kses_post( (string) ( $field['checkboxLabel'] ?? '' ) ) ) );
	}

	/**
	 * Maps a CAPTCHA: reCAPTCHA keeps its kind, math/simple CAPTCHAs become a honeypot.
	 *
	 * @param array<string, mixed> $field GF captcha field.
	 *
	 * @return Mapped Result.
	 */
	private function captcha( array $field ): Mapped {
		$kind = (string) ( $field['captchaType'] ?? '' );

		if ( in_array( $kind, array( 'math', 'simple_captcha' ), true ) ) {
			return $this->simple( $field, 'honeypot' )->with_note( __( 'The math/simple CAPTCHA became an invisible honeypot.', 'webofd-gf-to-elementor' ) );
		}

		if ( 'invisible' === ( $field['captchaSize'] ?? '' ) ) {
			return $this->simple( $field, 'recaptcha_v3' )->requiring( 'recaptcha_v3' )->with_note( __( 'Invisible reCAPTCHA v2 became reCAPTCHA v3, which needs v3 keys in Elementor.', 'webofd-gf-to-elementor' ) );
		}

		$overrides = array(
			'recaptcha_size'  => 'compact' === ( $field['captchaSize'] ?? '' ) ? 'compact' : 'normal',
			'recaptcha_style' => 'dark' === ( $field['captchaTheme'] ?? '' ) ? 'dark' : 'light',
		);

		return $this->simple( $field, 'recaptcha', $overrides )->requiring( 'recaptcha' );
	}

	/**
	 * Handles types without a dedicated mapping: drop, reuse their inputType, or fall back to text.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return Mapped Result.
	 */
	private function fallback( array $field ): Mapped {
		$type       = (string) ( $field['type'] ?? '' );
		$input_type = (string) ( $field['inputType'] ?? '' );

		if ( in_array( $type, self::DROPPED_TYPES, true ) ) {
			/* translators: %s: Gravity Forms field type, e.g. "signature". */
			return Mapped::dropped( sprintf( __( 'Elementor forms have no equivalent of the "%s" field.', 'webofd-gf-to-elementor' ), $type ) );
		}

		if ( $input_type !== $type && in_array( $input_type, self::FALLBACK_INPUT_TYPES, true ) ) {
			/* translators: 1: Gravity Forms field type, 2: input type it was converted as. */
			return $this->dispatch( array_merge( $field, array( 'type' => $input_type ) ) )->with_note( sprintf( __( 'The "%1$s" field was converted as a plain "%2$s" field.', 'webofd-gf-to-elementor' ), $type, $input_type ) );
		}

		$elementor_type = in_array( $type, array( 'post_content', 'post_excerpt' ), true ) ? 'textarea' : 'text';

		/* translators: 1: Gravity Forms field type, 2: Elementor field type. */
		return $this->simple( $field, $elementor_type )->with_note( sprintf( __( 'Unsupported "%1$s" field became a %2$s field.', 'webofd-gf-to-elementor' ), $type, $elementor_type ) );
	}

	/**
	 * Builds the Elementor step for page $index (0 = the first page, before any page break).
	 *
	 * @param StepPlan                         $steps  Page plan.
	 * @param int                              $index  Page index.
	 * @param array<string, mixed>|null        $field  GF page field, null for the first page.
	 * @param array<int, array<string, mixed>> $fields All form fields.
	 *
	 * @return FieldResult Step item and entries.
	 */
	private function step( StepPlan $steps, int $index, ?array $field, array $fields = array() ): FieldResult {
		$label = $steps->label( $index );
		$id    = $this->ids->for_field( null === $field ? 'page_1' : (string) $field['id'], '' !== $label ? $label : 'step' );
		$item  = $this->items->make( 'step', $id, array( 'label' => $label ), $steps->buttons( $index ) );

		if ( null === $field ) {
			/* translators: %s: page name. */
			$entry = new ReportEntry( 'page', sprintf( __( 'First page "%s"', 'webofd-gf-to-elementor' ), $label ), ResultStatus::Converted, __( 'Starts the first step.', 'webofd-gf-to-elementor' ), 'step: ' . $id );

			return new FieldResult( array( $item ), array( $entry ) );
		}

		return new FieldResult( array( $item ), $this->entries( $field, new Mapped( array( $item ) ), $fields, 'page' ) );
	}

	/**
	 * Adds a honeypot field when the form enables GF's anti-spam honeypot.
	 *
	 * @param FieldResult          $result Result so far.
	 * @param array<string, mixed> $form   GF form.
	 *
	 * @return FieldResult Result with a honeypot, if needed.
	 */
	private function with_honeypot( FieldResult $result, array $form ): FieldResult {
		if ( empty( $form['enableHoneypot'] ) || $result->has_type( 'honeypot' ) ) {
			return $result;
		}

		$id    = $this->ids->for_field( 'honeypot', 'honeypot' );
		$item  = $this->items->make( 'honeypot', $id, array( 'label' => '' ) );
		$entry = new ReportEntry( 'form', __( 'Anti-spam honeypot', 'webofd-gf-to-elementor' ), ResultStatus::Converted, '', 'honeypot: ' . $id );

		return $result->merge( new FieldResult( array( $item ), array( $entry ) ) );
	}

	/**
	 * Adds the notes every field type shares: description, length limit, dynamic values, visibility.
	 *
	 * @param Mapped               $mapped Result so far.
	 * @param array<string, mixed> $field  GF field.
	 *
	 * @return Mapped Result with notes.
	 */
	private function apply_common_notes( Mapped $mapped, array $field ): Mapped {
		if ( ResultStatus::Dropped === $mapped->status ) {
			return $mapped;
		}

		foreach ( $this->common_notes( $field ) as $note ) {
			$mapped = $mapped->with_note( $note );
		}

		return in_array( $field['visibility'] ?? 'visible', array( 'administrative', 'hidden' ), true ) ? $this->hide( $mapped ) : $mapped;
	}

	/**
	 * Lists the shared notes that apply to a field.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return array<int, string> Notes.
	 */
	private function common_notes( array $field ): array {
		$notes       = array();
		$description = trim( wp_strip_all_tags( (string) ( $field['description'] ?? '' ) ) );
		$default     = (string) ( $field['defaultValue'] ?? '' );

		if ( '' !== $description && ! in_array( $field['type'] ?? '', self::DESCRIPTION_KEPT_TYPES, true ) ) {
			/* translators: %s: field description. */
			$notes[] = sprintf( __( 'The description "%s" is not shown: Elementor fields have no description.', 'webofd-gf-to-elementor' ), mb_strimwidth( $description, 0, 80, '…' ) );
		}

		if ( is_numeric( $field['maxLength'] ?? null ) && (int) $field['maxLength'] > 0 ) {
			/* translators: %d: maximum number of characters. */
			$notes[] = sprintf( __( 'The %d-character limit is not enforced.', 'webofd-gf-to-elementor' ), (int) $field['maxLength'] );
		}

		if ( ItemFactory::is_dynamic_value( $default ) ) {
			/* translators: %s: Gravity Forms merge tag, e.g. "{user:display_name}". */
			$notes[] = sprintf( __( 'The dynamic default value %s was left empty: Elementor cannot evaluate Gravity Forms merge tags.', 'webofd-gf-to-elementor' ), $default );
		}

		if ( ! empty( $field['allowsPrepopulate'] ) && '' !== (string) ( $field['inputName'] ?? '' ) ) {
			/* translators: %s: URL parameter name. */
			$notes[] = sprintf( __( 'Filling the field from the "%s" URL parameter is not carried over.', 'webofd-gf-to-elementor' ), (string) $field['inputName'] );
		}

		return $notes;
	}

	/**
	 * Turns an admin-only field's items into hidden fields.
	 *
	 * @param Mapped $mapped Result.
	 *
	 * @return Mapped Result with hidden items.
	 */
	private function hide( Mapped $mapped ): Mapped {
		$hidden = $mapped->map_items(
			static function ( array $item ): array {
				unset( $item['width'], $item['required'], $item['placeholder'] );
				$item['field_type'] = 'hidden';

				return $item;
			}
		);

		return $hidden->with_note( __( 'Admin-only field became a hidden field.', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Builds the report entries for a field: its own, plus one for dropped conditional logic.
	 *
	 * @param array<string, mixed>             $field   GF field.
	 * @param Mapped                           $mapped  Mapping result.
	 * @param array<int, array<string, mixed>> $fields  All form fields.
	 * @param string                           $subject Entry subject ("field" or "page").
	 *
	 * @return array<int, ReportEntry> Entries.
	 */
	private function entries( array $field, Mapped $mapped, array $fields, string $subject ): array {
		$gf_ref  = $this->gf_ref( $field );
		$entries = array( new ReportEntry( $subject, $gf_ref, $mapped->status, implode( ' ', $mapped->notes ), $this->elementor_ref( $mapped ) ) );

		if ( ConditionalLogicDescriber::has_rules( $field['conditionalLogic'] ?? null ) ) {
			/* translators: %s: the rule in words, e.g. Show if "Topic" is "sales". */
			$entries[] = new ReportEntry( 'logic', $gf_ref, ResultStatus::Dropped, sprintf( __( 'Conditional logic dropped: %s.', 'webofd-gf-to-elementor' ), $this->logic->describe( $field['conditionalLogic'], $fields ) ) );
		}

		return $entries;
	}

	/**
	 * Builds the report's name for a GF field: "Label (#id, type)".
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return string Reference.
	 */
	private function gf_ref( array $field ): string {
		$label = ItemFactory::plain( (string) ( $field['label'] ?? '' ) );

		return sprintf( '%s (#%s, %s)', '' === $label ? '—' : $label, (string) ( $field['id'] ?? '' ), (string) ( $field['type'] ?? '' ) );
	}

	/**
	 * Lists the produced Elementor fields: "custom_id (type), ...".
	 *
	 * @param Mapped $mapped Mapping result.
	 *
	 * @return string Reference.
	 */
	private function elementor_ref( Mapped $mapped ): string {
		$parts = array_map( static fn( array $item ): string => $item['custom_id'] . ' (' . $item['field_type'] . ')', $mapped->items );

		return implode( ', ', $parts );
	}

	/**
	 * Assigns the custom_id for a whole field.
	 *
	 * @param array<string, mixed> $field GF field.
	 *
	 * @return string custom_id.
	 */
	private function field_id( array $field ): string {
		return $this->ids->for_field( (string) ( $field['id'] ?? '' ), (string) ( $field['label'] ?? '' ) );
	}

	/**
	 * Maps every input ID of a multi-input field (checkbox, consent, email confirm...) to one custom_id.
	 *
	 * @param array<string, mixed> $field GF field.
	 * @param string               $id    custom_id.
	 *
	 * @return void
	 */
	private function alias_inputs( array $field, string $id ): void {
		foreach ( (array) ( $field['inputs'] ?? array() ) as $input ) {
			if ( is_array( $input ) && '' !== (string) ( $input['id'] ?? '' ) ) {
				$this->ids->alias( (string) $input['id'], $id );
			}
		}
	}
}
