<?php
/**
 * Site facts the conversion needs, injectable for tests.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Admin email, page URLs, random element IDs, reCAPTCHA key status and the form's feeds.
 */
final class ConversionContext {

	/**
	 * Returns a page URL, or false when the page is gone.
	 *
	 * @var callable(int): (string|false)
	 */
	public readonly mixed $permalink;

	/**
	 * Returns a new random Elementor element ID.
	 *
	 * @var callable(): string
	 */
	public readonly mixed $random_id;

	/**
	 * Creates the context.
	 *
	 * @param string                        $admin_email    Replaces {admin_email}.
	 * @param callable(int): (string|false) $permalink      Page ID => URL.
	 * @param callable(): string            $random_id      Random element ID generator.
	 * @param array<string, bool>           $recaptcha_keys "recaptcha" / "recaptcha_v3" => whether Elementor has keys.
	 * @param array<int, string>            $feeds          Add-on slugs of the form's feeds.
	 */
	public function __construct(
		public readonly string $admin_email,
		callable $permalink,
		callable $random_id,
		public readonly array $recaptcha_keys = array(),
		public readonly array $feeds = array()
	) {
		$this->permalink = $permalink;
		$this->random_id = $random_id;
	}

	/**
	 * Builds the context from the live site.
	 *
	 * @param array<int, string> $feeds Add-on slugs of the form's feeds.
	 *
	 * @return self Context.
	 */
	public static function from_site( array $feeds ): self {
		return new self(
			(string) get_option( 'admin_email' ),
			static fn( int $page_id ): string|false => 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : false,
			array( self::class, 'random_element_id' ),
			array(
				'recaptcha'    => '' !== (string) get_option( 'elementor_pro_recaptcha_site_key' ),
				'recaptcha_v3' => '' !== (string) get_option( 'elementor_pro_recaptcha_v3_site_key' ),
			),
			$feeds
		);
	}

	/**
	 * Returns a random 7-character hex ID, the format Elementor uses for elements.
	 *
	 * @return string ID.
	 */
	public static function random_element_id(): string {
		if ( class_exists( '\Elementor\Utils' ) ) {
			return \Elementor\Utils::generate_random_string();
		}

		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}
}
