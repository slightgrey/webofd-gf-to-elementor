<?php
/**
 * Converts the Gravity Forms default confirmation into Elementor's success message or redirect.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Elementor has one success message and one redirect per form, so only GF's default
 * confirmation is used; conditional confirmations are reported.
 */
final class ConfirmationMapper {

	/**
	 * Matches Elementor field shortcodes, which success messages do not evaluate.
	 */
	private const SHORTCODES = '/\[(?:field id="[^"]*"|all-fields)\]/';

	/**
	 * Resolves a page ID to its URL; returns false when the page is gone.
	 *
	 * @var callable(int): (string|false)
	 */
	private $permalink;

	/**
	 * Creates the mapper.
	 *
	 * @param MergeTagConverter                $tags      Merge tag converter.
	 * @param ConditionalLogicDescriber        $logic     Conditional logic describer.
	 * @param array<int, array<string, mixed>> $fields    Form fields, for logic labels.
	 * @param callable(int): (string|false)    $permalink Page ID => URL, e.g. get_permalink.
	 */
	public function __construct(
		private readonly MergeTagConverter $tags,
		private readonly ConditionalLogicDescriber $logic,
		private readonly array $fields,
		callable $permalink
	) {
		$this->permalink = $permalink;
	}

	/**
	 * Maps a form's confirmations.
	 *
	 * @param array<string, array<string, mixed>> $confirmations GF confirmations keyed by ID.
	 *
	 * @return SettingsResult Success message or redirect settings, actions and entries.
	 */
	public function map( array $confirmations ): SettingsResult {
		$default = $this->default_id( $confirmations );
		$result  = new SettingsResult();
		$entries = array();

		foreach ( $confirmations as $id => $confirmation ) {
			if ( (string) $id !== $default ) {
				$entries[] = $this->dropped_entry( $confirmation );
				continue;
			}

			$result    = $this->map_default( $confirmation );
			$entries[] = $result->entries[0];
		}

		return new SettingsResult( $result->settings, $result->actions, $entries );
	}

	/**
	 * Finds the default confirmation's ID (the first one if none is flagged).
	 *
	 * @param array<string, array<string, mixed>> $confirmations Confirmations.
	 *
	 * @return string ID, or "" if there are none.
	 */
	private function default_id( array $confirmations ): string {
		foreach ( $confirmations as $id => $confirmation ) {
			if ( ! empty( $confirmation['isDefault'] ) ) {
				return (string) $id;
			}
		}

		return (string) array_key_first( $confirmations );
	}

	/**
	 * Maps the default confirmation by type.
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 *
	 * @return SettingsResult Result with one entry.
	 */
	private function map_default( array $confirmation ): SettingsResult {
		return match ( (string) ( $confirmation['type'] ?? 'message' ) ) {
			'page'     => $this->redirect( $confirmation, $this->page_url( $confirmation ) ),
			'redirect' => $this->redirect( $confirmation, (string) ( $confirmation['url'] ?? '' ) ),
			default    => $this->message( $confirmation ),
		};
	}

	/**
	 * Maps a message confirmation to Elementor's success message (plain text, no merge tags).
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 *
	 * @return SettingsResult Result.
	 */
	private function message( array $confirmation ): SettingsResult {
		$converted = $this->tags->convert( (string) ( $confirmation['message'] ?? '' ) );
		$without   = (string) preg_replace( self::SHORTCODES, '', $converted->text );
		$spaced    = (string) preg_replace( '#<(?:br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $without );
		$plain     = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $spaced ), ENT_QUOTES, 'UTF-8' ) ) );
		$notes     = new Mapped( array() );

		if ( $converted->has_problems() || $without !== $converted->text ) {
			$notes = $notes->with_note( __( 'Merge tags were removed: Elementor success messages cannot show submitted values.', 'webofd-gf-to-elementor' ) );
		}

		if ( wp_strip_all_tags( $without ) !== $without ) {
			$notes = $notes->with_note( __( 'HTML formatting was removed: Elementor shows the success message as plain text.', 'webofd-gf-to-elementor' ) );
		}

		$settings = '' === $plain ? array() : array(
			'success_message' => $plain,
			'custom_messages' => 'yes',
		);

		return new SettingsResult( $settings, array(), array( $this->entry( $confirmation, $notes, __( 'Success message', 'webofd-gf-to-elementor' ) ) ) );
	}

	/**
	 * Maps a page or redirect confirmation to Elementor's Redirect action.
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 * @param string               $url          Target URL ("" if the page is gone).
	 *
	 * @return SettingsResult Result.
	 */
	private function redirect( array $confirmation, string $url ): SettingsResult {
		if ( '' === $url ) {
			$notes = Mapped::dropped( __( 'The confirmation page no longer exists, so visitors see the default success message.', 'webofd-gf-to-elementor' ) );

			return new SettingsResult( array(), array(), array( $this->entry( $confirmation, $notes, '' ) ) );
		}

		$notes     = new Mapped( array() );
		$converted = $this->tags->convert( $this->with_query( $url, (string) ( $confirmation['queryString'] ?? '' ) ) );

		if ( $converted->has_problems() ) {
			/* translators: %s: comma-separated Gravity Forms merge tags. */
			$notes = $notes->with_note( sprintf( __( 'Merge tags removed or simplified: %s.', 'webofd-gf-to-elementor' ), implode( ', ', $converted->problems ) ) );
		}

		return new SettingsResult( array( 'redirect_to' => $converted->text ), array( 'redirect' ), array( $this->entry( $confirmation, $notes, __( 'Redirect', 'webofd-gf-to-elementor' ) ) ) );
	}

	/**
	 * Resolves a page confirmation's page URL.
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 *
	 * @return string URL, or "" if the page is gone.
	 */
	private function page_url( array $confirmation ): string {
		$page_id = (int) ( $confirmation['pageId'] ?? 0 );
		$url     = $page_id > 0 ? ( $this->permalink )( $page_id ) : false;

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Appends GF's query string (stored HTML-escaped) to a URL.
	 *
	 * @param string $url          URL.
	 * @param string $query_string Query string without "?".
	 *
	 * @return string URL with the query string.
	 */
	private function with_query( string $url, string $query_string ): string {
		$query_string = ltrim( html_entity_decode( trim( $query_string ), ENT_QUOTES, 'UTF-8' ), '?&' );

		if ( '' === $query_string ) {
			return $url;
		}

		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $query_string;
	}

	/**
	 * Builds the entry for a confirmation that is not the default.
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 *
	 * @return ReportEntry Dropped entry.
	 */
	private function dropped_entry( array $confirmation ): ReportEntry {
		$reason = __( 'Elementor forms have one confirmation, so only the default one was used.', 'webofd-gf-to-elementor' );

		if ( ConditionalLogicDescriber::has_rules( $confirmation['conditionalLogic'] ?? null ) ) {
			/* translators: %s: the rule in words. */
			$reason .= ' ' . sprintf( __( 'This one applied %s.', 'webofd-gf-to-elementor' ), lcfirst( $this->logic->describe( $confirmation['conditionalLogic'], $this->fields, true ) ) );
		}

		return new ReportEntry( 'confirmation', $this->name( $confirmation ), ResultStatus::Dropped, $reason );
	}

	/**
	 * Builds the entry for the default confirmation.
	 *
	 * @param array<string, mixed> $confirmation  GF confirmation.
	 * @param Mapped               $notes         Status and notes.
	 * @param string               $elementor_ref What it became.
	 *
	 * @return ReportEntry Entry.
	 */
	private function entry( array $confirmation, Mapped $notes, string $elementor_ref ): ReportEntry {
		return new ReportEntry( 'confirmation', $this->name( $confirmation ), $notes->status, implode( ' ', $notes->notes ), $elementor_ref );
	}

	/**
	 * Returns a confirmation's display name.
	 *
	 * @param array<string, mixed> $confirmation GF confirmation.
	 *
	 * @return string Name.
	 */
	private function name( array $confirmation ): string {
		return (string) ( $confirmation['name'] ?? $confirmation['id'] ?? '' );
	}
}
