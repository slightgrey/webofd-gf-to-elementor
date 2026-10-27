<?php
/**
 * Finds Gravity Forms embeds in Elementor page data.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Detector;

/**
 * Walks the _elementor_data JSON tree and detects:
 * - add-on Gravity Forms widgets, by reading the form ID from their settings;
 * - shortcodes inside any widget's text settings (Shortcode, Text Editor, HTML...).
 */
class ElementorDetector {

	/**
	 * Known add-on widget types mapped to the setting that holds the form ID.
	 */
	private const DEFAULT_WIDGET_MAP = array(
		'eael-gravity-form' => 'contact_form_list',
		'pp-gravity-forms'  => 'contact_form_list',
	);

	/**
	 * Core widgets whose single text setting can hold nothing but a shortcode.
	 */
	private const CONTENT_SETTINGS = array(
		'shortcode'   => 'shortcode',
		'text-editor' => 'editor',
		'html'        => 'html',
	);

	/**
	 * Setting keys tried for unmapped widgets whose type mentions "gravity".
	 */
	private const FALLBACK_SETTING_KEYS = array( 'form_id', 'contact_form_list', 'gravity_form', 'form_list', 'select_form' );

	/**
	 * Shortcode detector used for widget text settings.
	 *
	 * @var ShortcodeDetector
	 */
	private ShortcodeDetector $shortcodes;

	/**
	 * Creates the detector.
	 *
	 * @param ShortcodeDetector $shortcodes Shortcode detector.
	 */
	public function __construct( ShortcodeDetector $shortcodes ) {
		$this->shortcodes = $shortcodes;
	}

	/**
	 * Finds every Gravity Forms embed in an _elementor_data JSON string.
	 *
	 * @param string $json Raw _elementor_data meta value.
	 *
	 * @return array<int, EmbedMatch> Unique matches; empty for empty or malformed JSON.
	 */
	public function detect( string $json ): array {
		if ( false === stripos( $json, 'gravity' ) ) {
			return array();
		}

		$elements = json_decode( $json, true );

		if ( ! is_array( $elements ) ) {
			return array();
		}

		return EmbedMatch::unique( $this->detect_in_elements( $elements ) );
	}

	/**
	 * Finds every widget that embeds one form, with the swap action each one needs.
	 *
	 * @param array<mixed> $elements Decoded _elementor_data.
	 * @param int          $form_id  Gravity Forms form ID.
	 *
	 * @return array<int, ElementorLocation> Locations in document order.
	 */
	public function locate( array $elements, int $form_id ): array {
		$locations = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$location = 'widget' === ( $element['elType'] ?? '' ) ? $this->locate_in_widget( $element, $form_id ) : null;

			if ( null !== $location ) {
				$locations[] = $location;
			}

			$locations = array_merge( $locations, $this->locate( is_array( $element['elements'] ?? null ) ? $element['elements'] : array(), $form_id ) );
		}

		return $locations;
	}

	/**
	 * Returns the location of the form in one widget, or null if the widget doesn't embed it.
	 *
	 * @param array<string, mixed> $widget  Widget element.
	 * @param int                  $form_id Form ID.
	 *
	 * @return ElementorLocation|null Location.
	 */
	private function locate_in_widget( array $widget, int $form_id ): ?ElementorLocation {
		$matches = array_filter( $this->detect_in_widget( $widget ), static fn( EmbedMatch $hit ): bool => $form_id === $hit->form_id() );

		if ( array() === $matches ) {
			return null;
		}

		$type      = is_string( $widget['widgetType'] ?? null ) ? $widget['widgetType'] : '';
		$id        = is_scalar( $widget['id'] ?? null ) ? (string) $widget['id'] : '';
		$is_widget = in_array( 'elementor_widget', array_map( static fn( EmbedMatch $hit ): string => $hit->embed_method(), $matches ), true );
		$whole     = $is_widget || $this->is_shortcode_only( $type, is_array( $widget['settings'] ?? null ) ? $widget['settings'] : array() );

		return new ElementorLocation( $id, $type, $whole ? ElementorLocation::WIDGET_REPLACE : ElementorLocation::INLINE_REPLACE );
	}

	/**
	 * Tells whether a Shortcode, Text Editor or HTML widget holds one GF shortcode and nothing else.
	 *
	 * @param string               $type     Widget type.
	 * @param array<string, mixed> $settings Widget settings.
	 *
	 * @return bool True if the widget can be replaced as a whole.
	 */
	private function is_shortcode_only( string $type, array $settings ): bool {
		$content_key = self::CONTENT_SETTINGS[ $type ] ?? null;
		$content     = null === $content_key ? null : ( $settings[ $content_key ] ?? null );

		if ( ! is_string( $content ) ) {
			return false;
		}

		$pattern = '/' . get_shortcode_regex( array( 'gravityform', 'gravityforms' ) ) . '/i';
		$rest    = (string) preg_replace( $pattern, '', $content, -1, $count );
		$rest    = str_replace( array( '&nbsp;', "\xC2\xA0" ), '', wp_strip_all_tags( $rest ) );

		return 1 === $count && '' === trim( $rest );
	}

	/**
	 * Returns the widget-type => setting-key map, filterable by other plugins.
	 *
	 * @return array<string, string> Widget map.
	 */
	private function widget_map(): array {
		$map = apply_filters( 'gf2el_elementor_widget_map', self::DEFAULT_WIDGET_MAP );

		return is_array( $map ) ? $map : self::DEFAULT_WIDGET_MAP;
	}

	/**
	 * Walks a list of Elementor elements depth-first.
	 *
	 * @param array<mixed> $elements Elements (sections, columns, containers, widgets).
	 *
	 * @return array<int, EmbedMatch> Matches.
	 */
	private function detect_in_elements( array $elements ): array {
		$matches = array();

		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$matches = array_merge( $matches, $this->detect_in_element( $element ) );
			}
		}

		return $matches;
	}

	/**
	 * Collects matches from one element and its children.
	 *
	 * @param array<string, mixed> $element Elementor element.
	 *
	 * @return array<int, EmbedMatch> Matches.
	 */
	private function detect_in_element( array $element ): array {
		$children = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$matches  = array();

		if ( 'widget' === ( $element['elType'] ?? '' ) ) {
			$matches = $this->detect_in_widget( $element );
		}

		return array_merge( $matches, $this->detect_in_elements( $children ) );
	}

	/**
	 * Detects the form setting and any shortcodes in one widget.
	 *
	 * @param array<string, mixed> $widget Widget element.
	 *
	 * @return array<int, EmbedMatch> Matches.
	 */
	private function detect_in_widget( array $widget ): array {
		$type     = is_string( $widget['widgetType'] ?? null ) ? $widget['widgetType'] : '';
		$settings = is_array( $widget['settings'] ?? null ) ? $widget['settings'] : array();
		$matches  = array();

		foreach ( $this->string_settings( $settings ) as $text ) {
			$matches = array_merge( $matches, $this->shortcodes->detect( $text, 'elementor_shortcode', $type ) );
		}

		$widget_match = $this->match_form_widget( $type, $settings );

		if ( null !== $widget_match ) {
			$matches[] = $widget_match;
		}

		return $matches;
	}

	/**
	 * Reads the form ID from a Gravity Forms add-on widget's settings.
	 *
	 * @param string               $type     Widget type.
	 * @param array<string, mixed> $settings Widget settings.
	 *
	 * @return EmbedMatch|null Match, or null if the widget isn't a form widget or has no form chosen.
	 */
	private function match_form_widget( string $type, array $settings ): ?EmbedMatch {
		foreach ( $this->setting_keys_for( $type ) as $key ) {
			$value = $settings[ $key ] ?? '';
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( ctype_digit( $value ) && (int) $value > 0 ) {
				return new EmbedMatch( (int) $value, $value, 'elementor_widget', $type );
			}
		}

		return null;
	}

	/**
	 * Lists the setting keys that may hold a form ID for a widget type.
	 *
	 * @param string $type Widget type.
	 *
	 * @return array<int, string> Setting keys, most specific first.
	 */
	private function setting_keys_for( string $type ): array {
		$map = $this->widget_map();

		if ( isset( $map[ $type ] ) ) {
			return array( (string) $map[ $type ] );
		}

		if ( false !== stripos( $type, 'gravity' ) ) {
			return self::FALLBACK_SETTING_KEYS;
		}

		return array();
	}

	/**
	 * Flattens every string value in a (possibly nested) settings array.
	 *
	 * @param array<mixed> $settings Widget settings, including repeater rows.
	 *
	 * @return array<int, string> String values that might contain a shortcode.
	 */
	private function string_settings( array $settings ): array {
		$strings = array();

		array_walk_recursive(
			$settings,
			static function ( $value ) use ( &$strings ): void {
				if ( is_string( $value ) && false !== stripos( $value, '[gravityform' ) ) {
					$strings[] = $value;
				}
			}
		);

		return $strings;
	}
}
