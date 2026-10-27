<?php
/**
 * Records, then compares, md5 hashes of everything a migration can change.
 *
 * Usage (from anywhere):
 *   php verify-rollback.php --record=hashes.json [--posts=12,34] [--host=plugin.test] [--wp=C:/laragon/www/plugin]
 *   php verify-rollback.php --compare=hashes.json [--host=...] [--wp=...]
 *
 * Covers post_content, _elementor_data and _elementor_version of every _gf2el_fixture
 * post (or of the --posts list), plus the raw widget_text, widget_custom_html,
 * widget_block and sidebars_widgets options. Run --record before migrating and
 * --compare after rolling back: every line must say "same".
 *
 * @package GFToElementor
 */

// phpcs:disable -- CLI test fixture, not shipped in the release zip.

declare( strict_types=1 );

$gf2el_args = array(
	'record'  => '',
	'compare' => '',
	'posts'   => '',
	'host'    => 'plugin.test',
	'wp'      => dirname( __DIR__, 5 ),
);

foreach ( array_slice( $argv, 1 ) as $gf2el_arg ) {
	if ( preg_match( '/^--(record|compare|posts|host|wp)=(.+)$/', $gf2el_arg, $gf2el_m ) ) {
		$gf2el_args[ $gf2el_m[1] ] = $gf2el_m[2];
	}
}

$_SERVER['HTTP_HOST']      = $gf2el_args['host'];
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['REQUEST_URI']    = '/';

require rtrim( (string) $gf2el_args['wp'], '/\\' ) . '/wp-load.php';

/**
 * Hashes every location a migration can touch.
 *
 * @param array<int, int> $post_ids Posts to cover.
 *
 * @return array<string, string> Location => md5 ("missing" when absent).
 */
function gf2el_verify_hashes( array $post_ids ): array {
	global $wpdb;

	$hashes = array();

	foreach ( $post_ids as $post_id ) {
		$content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		$hashes[ "post {$post_id} post_content" ] = null === $content ? 'missing' : md5( (string) $content );

		foreach ( array( '_elementor_data', '_elementor_version' ) as $key ) {
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key ) );
			$hashes[ "post {$post_id} {$key}" ] = null === $value ? 'missing' : md5( (string) $value );
		}
	}

	foreach ( array( 'widget_text', 'widget_custom_html', 'widget_block', 'sidebars_widgets' ) as $option ) {
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		$hashes[ "option {$option}" ] = null === $value ? 'missing' : md5( (string) $value );
	}

	return $hashes;
}

if ( '' !== $gf2el_args['record'] ) {
	global $wpdb;

	$gf2el_posts = '' !== $gf2el_args['posts']
		? array_map( 'intval', explode( ',', $gf2el_args['posts'] ) )
		: array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gf2el_fixture'" ) );

	file_put_contents( $gf2el_args['record'], (string) wp_json_encode( array( 'posts' => $gf2el_posts, 'hashes' => gf2el_verify_hashes( $gf2el_posts ) ), JSON_PRETTY_PRINT ) );
	printf( "Recorded %d locations for %d posts.\n", count( gf2el_verify_hashes( $gf2el_posts ) ), count( $gf2el_posts ) );
	return;
}

$gf2el_saved   = json_decode( (string) file_get_contents( $gf2el_args['compare'] ), true );
$gf2el_now     = gf2el_verify_hashes( (array) $gf2el_saved['posts'] );
$gf2el_changed = 0;

foreach ( (array) $gf2el_saved['hashes'] as $gf2el_location => $gf2el_hash ) {
	$gf2el_same     = ( $gf2el_now[ $gf2el_location ] ?? 'missing' ) === $gf2el_hash;
	$gf2el_changed += $gf2el_same ? 0 : 1;
	printf( "%s  %s\n", $gf2el_same ? 'same   ' : 'CHANGED', $gf2el_location );
}

printf( "%s: %d of %d locations changed.\n", 0 === $gf2el_changed ? 'ALL SAME' : 'DIFFERENT', $gf2el_changed, count( (array) $gf2el_saved['hashes'] ) );
exit( 0 === $gf2el_changed ? 0 : 1 );
