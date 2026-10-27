<?php
/**
 * Assembles the Elementor Pro Form widget element.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Combines the mapped fields, email settings and confirmation into one `form` widget
 * element, ready to drop into _elementor_data.
 */
final class FormWidgetBuilder {

	/**
	 * Creates the builder.
	 *
	 * @param ItemFactory $items Source of random element IDs.
	 */
	public function __construct( private readonly ItemFactory $items ) {
	}

	/**
	 * Builds the widget element.
	 *
	 * @param array<string, mixed> $form          GF form.
	 * @param FieldResult          $fields        Mapped fields.
	 * @param SettingsResult       $notifications Email settings.
	 * @param SettingsResult       $confirmation  Success message or redirect settings.
	 *
	 * @return array<string, mixed> Elementor element (elType widget, widgetType form).
	 */
	public function build( array $form, FieldResult $fields, SettingsResult $notifications, SettingsResult $confirmation ): array {
		$settings = array_merge(
			$this->base_settings( $form, $fields ),
			$notifications->settings,
			$confirmation->settings,
			array( 'submit_actions' => $this->submit_actions( $notifications, $confirmation ) )
		);

		return array(
			'id'         => $this->items->random_id(),
			'elType'     => 'widget',
			'widgetType' => 'form',
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Builds the form-level settings.
	 *
	 * @param array<string, mixed> $form   GF form.
	 * @param FieldResult          $fields Mapped fields.
	 *
	 * @return array<string, mixed> Settings.
	 */
	private function base_settings( array $form, FieldResult $fields ): array {
		return array(
			'form_name'     => ItemFactory::plain( (string) ( $form['title'] ?? '' ) ),
			'form_fields'   => $fields->items,
			'button_text'   => $this->button_text( $form ),
			'show_labels'   => 'true',
			'mark_required' => 'yes',
		);
	}

	/**
	 * Returns the submit button text; image buttons fall back to "Submit".
	 *
	 * @param array<string, mixed> $form GF form.
	 *
	 * @return string Button text.
	 */
	private function button_text( array $form ): string {
		$button = is_array( $form['button'] ?? null ) ? $form['button'] : array();
		$text   = 'text' === ( $button['type'] ?? 'text' ) ? ItemFactory::plain( (string) ( $button['text'] ?? '' ) ) : '';

		return '' === $text ? __( 'Submit', 'webofd-gf-to-elementor' ) : $text;
	}

	/**
	 * Lists the submit actions: Collect Submissions always first, then email(s), then redirect.
	 *
	 * @param SettingsResult $notifications Email settings.
	 * @param SettingsResult $confirmation  Confirmation settings.
	 *
	 * @return array<int, string> Actions.
	 */
	private function submit_actions( SettingsResult $notifications, SettingsResult $confirmation ): array {
		return array_values( array_unique( array_merge( array( 'save-to-database' ), $notifications->actions, $confirmation->actions ) ) );
	}
}
