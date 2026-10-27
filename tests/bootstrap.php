<?php
/**
 * PHPUnit bootstrap for Web of D GF to Elementor unit tests.
 *
 * Unit tests run without a database. WordPress hook functions are replaced by
 * stubs that record calls in $GLOBALS['gf2el_test_hooks'], options come from
 * $GLOBALS['gf2el_test_options'], and WordPress core's formatting, shortcode
 * and block parser files are loaded straight from wp-includes.
 *
 * Set GF2EL_WP_INCLUDES to point at a different wp-includes directory.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

$GLOBALS['gf2el_test_hooks'] = array();

/**
 * Filters registered by tests: hook name => callback.
 *
 * @var array<string, callable> $GLOBALS['gf2el_test_filters']
 */
$GLOBALS['gf2el_test_filters'] = array();

/**
 * Option values returned by the get_option() stub.
 *
 * @var array<string, mixed> $GLOBALS['gf2el_test_options']
 */
$GLOBALS['gf2el_test_options'] = array(
	'blog_charset' => 'UTF-8',
	'admin_email'  => 'admin@example.test',
);

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Runs a test-registered filter, or returns the value unchanged.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Extra arguments.
	 *
	 * @return mixed Filtered value.
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( isset( $GLOBALS['gf2el_test_filters'][ $hook ] ) ) {
			return $GLOBALS['gf2el_test_filters'][ $hook ]( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Records an action registration.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 *
	 * @return bool Always true.
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['gf2el_test_hooks'][] = array( 'action', $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Records a filter registration.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 *
	 * @return bool Always true.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['gf2el_test_hooks'][] = array( 'filter', $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Returns a value from $GLOBALS['gf2el_test_options'].
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Fallback.
	 *
	 * @return mixed Option value.
	 */
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['gf2el_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * Returns the test locale.
	 *
	 * @return string Locale.
	 */
	function get_locale() {
		return 'en_US';
	}
}

if ( ! function_exists( 'is_utf8_charset' ) ) {
	/**
	 * Tells whether a charset name means UTF-8 (functions.php is too heavy to load).
	 *
	 * @param string|null $blog_charset Charset name.
	 *
	 * @return bool True for UTF-8.
	 */
	function is_utf8_charset( $blog_charset = null ) {
		return in_array( strtolower( (string) ( $blog_charset ?? 'utf-8' ) ), array( 'utf-8', 'utf8' ), true );
	}
}

if ( ! function_exists( '_canonical_charset' ) ) {
	/**
	 * Returns the canonical charset name.
	 *
	 * @param string $charset Charset name.
	 *
	 * @return string Canonical name.
	 */
	function _canonical_charset( $charset ) {
		return is_utf8_charset( $charset ) ? 'UTF-8' : (string) $charset;
	}
}

if ( ! function_exists( 'wp_allowed_protocols' ) ) {
	/**
	 * Returns WordPress's default list of allowed URL protocols (used by kses).
	 *
	 * @return array<int, string> Protocols.
	 */
	function wp_allowed_protocols() {
		return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Returns the text untranslated.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 *
	 * @return string Text.
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- WordPress core name.
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Returns the text escaped for HTML, untranslated.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 *
	 * @return string Escaped text.
	 */
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- WordPress core name.
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Picks the singular or plural text, untranslated.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Count.
	 * @param string $domain Text domain.
	 *
	 * @return string Text.
	 */
	function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- WordPress core name.
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encodes JSON like WordPress does (no sanity checks needed in tests).
	 *
	 * @param mixed $value Value.
	 * @param int   $flags json_encode flags.
	 * @param int   $depth Depth.
	 *
	 * @return string|false JSON.
	 */
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This is the stub.
	}
}

$gf2el_wp_includes = getenv( 'GF2EL_WP_INCLUDES' ) ? rtrim( (string) getenv( 'GF2EL_WP_INCLUDES' ), '/\\' ) . '/' : ABSPATH . 'wp-includes/';

// The HTML API, in wp-settings.php order (kses uses the tag processor).
require_once $gf2el_wp_includes . 'class-wp-token-map.php';
foreach ( array( 'html5-named-character-references', 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-doctype-info', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor' ) as $gf2el_html_api_file ) {
	require_once $gf2el_wp_includes . 'html-api/' . $gf2el_html_api_file . '.php';
}

require_once $gf2el_wp_includes . 'compat-utf8.php';
require_once $gf2el_wp_includes . 'utf8.php';
require_once $gf2el_wp_includes . 'formatting.php';
require_once $gf2el_wp_includes . 'kses.php';
require_once $gf2el_wp_includes . 'shortcodes.php';
require_once $gf2el_wp_includes . 'class-wp-block-parser-block.php';
require_once $gf2el_wp_includes . 'class-wp-block-parser-frame.php';
require_once $gf2el_wp_includes . 'class-wp-block-parser.php';
require_once $gf2el_wp_includes . 'blocks.php';
