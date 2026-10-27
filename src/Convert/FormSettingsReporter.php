<?php
/**
 * Reports form-level Gravity Forms settings that Elementor forms can't reproduce.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Login requirement, entry limits, scheduling, Save and Continue, button logic
 * and side-by-side labels each get a report entry when used.
 */
final class FormSettingsReporter {

	/**
	 * Creates the reporter.
	 *
	 * @param ConditionalLogicDescriber $logic Conditional logic describer.
	 */
	public function __construct( private readonly ConditionalLogicDescriber $logic ) {
	}

	/**
	 * Returns an entry for each form setting that was not carried over.
	 *
	 * @param array<string, mixed> $form GF form.
	 *
	 * @return array<int, ReportEntry> Entries.
	 */
	public function entries( array $form ): array {
		$entries = array();

		foreach ( $this->dropped_settings( $form ) as $setting => $reason ) {
			$entries[] = new ReportEntry( 'form', $setting, ResultStatus::Dropped, $reason );
		}

		$placement = (string) ( $form['labelPlacement'] ?? 'top_label' );

		if ( in_array( $placement, array( 'left_label', 'right_label' ), true ) ) {
			$entries[] = new ReportEntry( 'form', __( 'Label placement', 'webofd-gf-to-elementor' ), ResultStatus::Approximated, __( 'Labels beside fields are now shown above them.', 'webofd-gf-to-elementor' ) );
		}

		return $entries;
	}

	/**
	 * Lists the enabled settings with no Elementor equivalent.
	 *
	 * @param array<string, mixed> $form GF form.
	 *
	 * @return array<string, string> Setting name => reason.
	 */
	private function dropped_settings( array $form ): array {
		$dropped = array();

		if ( ! empty( $form['requireLogin'] ) ) {
			$dropped[ __( 'Require login', 'webofd-gf-to-elementor' ) ] = __( 'Anyone can now submit the form, logged in or not.', 'webofd-gf-to-elementor' );
		}

		if ( ! empty( $form['limitEntries'] ) ) {
			$dropped[ __( 'Entry limit', 'webofd-gf-to-elementor' ) ] = __( 'The form no longer closes after a number of entries.', 'webofd-gf-to-elementor' );
		}

		if ( ! empty( $form['scheduleForm'] ) ) {
			$dropped[ __( 'Form schedule', 'webofd-gf-to-elementor' ) ] = __( 'The form is always open; its start and end dates were dropped.', 'webofd-gf-to-elementor' );
		}

		if ( ! empty( $form['save']['enabled'] ) ) {
			$dropped[ __( 'Save and Continue', 'webofd-gf-to-elementor' ) ] = __( 'Visitors can no longer save a partly filled form and finish it later.', 'webofd-gf-to-elementor' );
		}

		$button_logic = $form['button']['conditionalLogic'] ?? null;

		if ( ConditionalLogicDescriber::has_rules( $button_logic ) ) {
			/* translators: %s: the rule in words. */
			$dropped[ __( 'Submit button', 'webofd-gf-to-elementor' ) ] = sprintf( __( 'Conditional logic dropped, so the button is always shown (was: %s).', 'webofd-gf-to-elementor' ), $this->logic->describe( $button_logic, (array) ( $form['fields'] ?? array() ) ) );
		}

		return $dropped;
	}
}
