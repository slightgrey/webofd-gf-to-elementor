<?php
/**
 * Seeds (or removes) end-to-end fixtures for Web of D GF to Elementor.
 *
 * Usage (from anywhere):
 *   php seed.php [--cleanup] [--host=plugin.test] [--wp=C:/laragon/www/plugin]
 *
 * Creates the kitchen-sink Gravity Forms form (tests/fixtures/gf/kitchen-sink.json)
 * and embeds it in every supported way. Every seeded post carries the _gf2el_fixture
 * meta key, the text widget is instance 960, and the form ID is kept in the
 * gf2el_fixture_seed option, so --cleanup removes exactly what this created
 * (plus any template or migration record made from the seeded form).
 *
 * Expected embeds of the kitchen-sink form (key: action):
 *   post:{elementor page}:elementor:sc00001     widget_replace     Shortcode widget
 *   post:{elementor page}:elementor:te00001     inline_replace     Text Editor with text around the shortcode
 *   post:{popup}:elementor:pp00001              widget_replace     Elementor popup (elementor_library)
 *   post:{block page}:content                   template_shortcode GF block between paragraphs
 *   post:{classic page}:content                 template_shortcode Classic [gravityform] shortcode
 *   widget:text:960                             template_shortcode Text widget in the first sidebar
 *   meta:{acf page}:page_sections               report_only        Serialized ACF-style meta
 *
 * @package GFToElementor
 */

// phpcs:disable -- CLI test fixture, not shipped in the release zip.

declare( strict_types=1 );

$gf2el_args = array(
	'cleanup' => false,
	'host'    => 'plugin.test',
	'wp'      => dirname( __DIR__, 5 ),
);

foreach ( array_slice( $argv, 1 ) as $gf2el_arg ) {
	if ( '--cleanup' === $gf2el_arg ) {
		$gf2el_args['cleanup'] = true;
	} elseif ( preg_match( '/^--(host|wp)=(.+)$/', $gf2el_arg, $gf2el_m ) ) {
		$gf2el_args[ $gf2el_m[1] ] = $gf2el_m[2];
	}
}

$_SERVER['HTTP_HOST']      = $gf2el_args['host'];
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['REQUEST_URI']    = '/';

require rtrim( (string) $gf2el_args['wp'], '/\\' ) . '/wp-load.php';

wp_set_current_user( 1 );

const GF2EL_FIXTURE_META   = '_gf2el_fixture';
const GF2EL_FIXTURE_OPTION = 'gf2el_fixture_seed';
const GF2EL_FIXTURE_WIDGET = 960;

/**
 * Inserts a fixture page or post.
 *
 * @param string               $title   Title.
 * @param string               $content Content.
 * @param array<string, mixed> $extra   Extra wp_insert_post args.
 *
 * @return int Post ID.
 */
function gf2el_fixture_post( string $title, string $content, array $extra = array() ): int {
	$id = (int) wp_insert_post(
		$extra + array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
	add_post_meta( $id, GF2EL_FIXTURE_META, 'seed' );

	return $id;
}

/**
 * Saves widgets into an Elementor document through Elementor's own Document API.
 *
 * @param int                                  $post_id Post ID.
 * @param array<string, array<string, mixed>>  $widgets Element ID => [ widgetType, settings ].
 * @param string                               $type    Elementor template type for library posts, '' for pages.
 *
 * @return void
 */
function gf2el_fixture_elementor( int $post_id, array $widgets, string $type = '' ): void {
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

	if ( '' !== $type ) {
		update_post_meta( $post_id, '_elementor_template_type', $type );
		wp_set_object_terms( $post_id, $type, 'elementor_library_type' );
	}

	$elements = array();

	foreach ( $widgets as $id => $widget ) {
		$elements[] = array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $widget[0],
			'settings'   => $widget[1],
			'elements'   => array(),
		);
	}

	\Elementor\Plugin::$instance->documents->get( $post_id, false )->save(
		array(
			'elements' => array(
				array(
					'id'       => 'cn' . $post_id,
					'elType'   => 'container',
					'settings' => array(),
					'elements' => $elements,
					'isInner'  => false,
				),
			),
		)
	);
}

/**
 * Creates the kitchen-sink form with its confirmation pointing at a seeded page.
 *
 * @param int $thanks_page Confirmation page ID.
 *
 * @return int Form ID.
 */
function gf2el_fixture_form( int $thanks_page ): int {
	$form = json_decode( (string) file_get_contents( __DIR__ . '/gf/kitchen-sink.json' ), true );
	unset( $form['id'], $form['is_active'], $form['is_trash'] );
	$form['confirmations']['c1']['pageId'] = (string) $thanks_page;

	$form_id = GFAPI::add_form( $form );

	if ( is_wp_error( $form_id ) ) {
		fwrite( STDERR, $form_id->get_error_message() . "\n" );
		exit( 1 );
	}

	return (int) $form_id;
}

/**
 * Adds the text widget to the first registered sidebar.
 *
 * @param string $text Widget text.
 *
 * @return string Sidebar ID.
 */
function gf2el_fixture_widget( string $text ): string {
	$widgets                         = (array) get_option( 'widget_text', array() );
	$widgets[ GF2EL_FIXTURE_WIDGET ] = array(
		'title'  => 'Fixture contact',
		'text'   => $text,
		'filter' => true,
		'visual' => true,
	);
	update_option( 'widget_text', $widgets );

	$sidebars = (array) get_option( 'sidebars_widgets', array() );
	$sidebar  = 'wp_inactive_widgets';

	foreach ( array_keys( $sidebars ) as $id ) {
		if ( 'wp_inactive_widgets' !== $id && 'array_version' !== $id && is_array( $sidebars[ $id ] ) ) {
			$sidebar = (string) $id;
			break;
		}
	}

	$sidebars[ $sidebar ]   = (array) ( $sidebars[ $sidebar ] ?? array() );
	$sidebars[ $sidebar ][] = 'text-' . GF2EL_FIXTURE_WIDGET;
	update_option( 'sidebars_widgets', $sidebars );

	return $sidebar;
}

/**
 * Removes every fixture this script (or a migration of the seeded form) created.
 *
 * @return void
 */
function gf2el_fixture_cleanup(): void {
	global $wpdb;

	$form_id = (int) get_option( GF2EL_FIXTURE_OPTION, 0 );
	$posts   = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", GF2EL_FIXTURE_META ) );

	if ( $form_id > 0 ) {
		$posts = array_merge( $posts, $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gf2el_source_form' AND meta_value = %d", $form_id ) ) );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}gf2el_migrations WHERE gf_form_id = %d", $form_id ) );

		foreach ( $ids as $migration_id ) {
			$wpdb->delete( $wpdb->prefix . 'gf2el_backups', array( 'migration_id' => (int) $migration_id ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gf2el_migrations', array( 'gf_form_id' => $form_id ) );
		GFAPI::delete_form( $form_id );
	}

	foreach ( array_unique( array_map( 'intval', $posts ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	$widgets = (array) get_option( 'widget_text', array() );
	unset( $widgets[ GF2EL_FIXTURE_WIDGET ] );
	update_option( 'widget_text', $widgets );

	$sidebars = (array) get_option( 'sidebars_widgets', array() );

	foreach ( $sidebars as $id => $widget_ids ) {
		if ( is_array( $widget_ids ) ) {
			$sidebars[ $id ] = array_values( array_diff( $widget_ids, array( 'text-' . GF2EL_FIXTURE_WIDGET ) ) );
		}
	}

	update_option( 'sidebars_widgets', $sidebars );
	delete_option( GF2EL_FIXTURE_OPTION );
	delete_transient( 'gf2el_embed_counts' );

	printf( "Removed %d fixture posts, form #%d and widget text-%d.\n", count( array_unique( $posts ) ), $form_id, GF2EL_FIXTURE_WIDGET );
}

if ( $gf2el_args['cleanup'] ) {
	gf2el_fixture_cleanup();
	return;
}

if ( get_option( GF2EL_FIXTURE_OPTION ) ) {
	echo "Fixtures already exist; run with --cleanup first.\n";
	return;
}

$gf2el_thanks = gf2el_fixture_post( 'Fixture: thanks', '<!-- wp:paragraph --><p>Thanks!</p><!-- /wp:paragraph -->' );
$gf2el_form   = gf2el_fixture_form( $gf2el_thanks );
$gf2el_sc     = '[gravityform id="' . $gf2el_form . '" title="false" ajax="true"]';

$gf2el_elementor = gf2el_fixture_post( 'Fixture: Elementor page', '' );
gf2el_fixture_elementor(
	$gf2el_elementor,
	array(
		'sc00001' => array( 'shortcode', array( 'shortcode' => $gf2el_sc, '_css_classes' => 'fixture-form' ) ),
		'te00001' => array( 'text-editor', array( 'editor' => '<p>Prefer email? Use this form:</p>' . $gf2el_sc ) ),
	)
);

$gf2el_popup = gf2el_fixture_post( 'Fixture: Elementor popup', '', array( 'post_type' => 'elementor_library' ) );
gf2el_fixture_elementor( $gf2el_popup, array( 'pp00001' => array( 'shortcode', array( 'shortcode' => $gf2el_sc ) ) ), 'popup' );

$gf2el_blocks  = gf2el_fixture_post( 'Fixture: block page', "<!-- wp:paragraph -->\n<p>Before the form.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:gravityforms/form {\"formId\":\"{$gf2el_form}\",\"title\":false} /-->\n\n<!-- wp:paragraph -->\n<p>After the form.</p>\n<!-- /wp:paragraph -->" );
$gf2el_classic = gf2el_fixture_post( 'Fixture: classic page', "Get in touch.\n\n{$gf2el_sc}\n\nWe reply within a day." );
$gf2el_acf     = gf2el_fixture_post( 'Fixture: ACF sections', '' );
add_post_meta( $gf2el_acf, 'page_sections', array( array( 'layout' => 'contact', 'content' => $gf2el_sc ) ) );
$gf2el_sidebar = gf2el_fixture_widget( '<p>Quick contact:</p>' . $gf2el_sc );

update_option( GF2EL_FIXTURE_OPTION, $gf2el_form, false );
delete_transient( 'gf2el_embed_counts' );

printf( "Kitchen-sink form #%d\n", $gf2el_form );
printf( "Expected embeds:\n" );
printf( "  post:%d:elementor:sc00001  widget_replace      Elementor page, Shortcode widget\n", $gf2el_elementor );
printf( "  post:%d:elementor:te00001  inline_replace      Elementor page, mixed Text Editor\n", $gf2el_elementor );
printf( "  post:%d:elementor:pp00001  widget_replace      Elementor popup\n", $gf2el_popup );
printf( "  post:%d:content            template_shortcode  block page\n", $gf2el_blocks );
printf( "  post:%d:content            template_shortcode  classic page\n", $gf2el_classic );
printf( "  widget:text:%d             template_shortcode  text widget in %s\n", GF2EL_FIXTURE_WIDGET, $gf2el_sidebar );
printf( "  meta:%d:page_sections      report_only         ACF-style meta\n", $gf2el_acf );
