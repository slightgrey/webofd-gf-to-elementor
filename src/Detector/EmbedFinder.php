<?php
/**
 * Finds every place a form is embedded.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Detector;

use WP_Post;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One candidate query per preview; WP_Query can't search meta values with LIKE across all keys.

/**
 * Searches posts (content, Elementor data, other meta) and sidebar widgets for one form,
 * and can re-check a single location by its key just before it is swapped.
 *
 * Elementor-built posts are read only from _elementor_data: their post_content is a
 * rendered copy that Elementor rewrites on every save.
 */
class EmbedFinder {

	/**
	 * Post statuses searched (trash and auto-drafts are left alone).
	 */
	public const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Non-public post types that can hold embeds.
	 */
	private const EXTRA_POST_TYPES = array( 'elementor_library', 'wp_block', 'wp_template', 'wp_template_part' );

	/**
	 * Widget base => setting that holds its content.
	 */
	public const WIDGET_FIELDS = array(
		'text'        => 'text',
		'custom_html' => 'content',
		'block'       => 'content',
	);

	/**
	 * Creates the finder.
	 *
	 * @param BlockDetector     $blocks     Block and shortcode detector for post content.
	 * @param ElementorDetector $elementor  Elementor detector.
	 * @param MetaDetector      $meta       Meta detector.
	 * @param ShortcodeDetector $shortcodes Shortcode detector for widgets.
	 */
	public function __construct(
		private readonly BlockDetector $blocks,
		private readonly ElementorDetector $elementor,
		private readonly MetaDetector $meta,
		private readonly ShortcodeDetector $shortcodes
	) {
	}

	/**
	 * Builds a finder with default detectors.
	 *
	 * @return self Finder.
	 */
	public static function create(): self {
		$shortcodes = new ShortcodeDetector();

		return new self( new BlockDetector( $shortcodes ), new ElementorDetector( $shortcodes ), new MetaDetector( $shortcodes ), $shortcodes );
	}

	/**
	 * Finds every embed of a form.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 *
	 * @return array<int, Embed> Embeds: posts by ID, then widgets.
	 */
	public function find( int $form_id ): array {
		$embeds = array();

		foreach ( $this->candidate_ids() as $post_id ) {
			$embeds = array_merge( $embeds, $this->in_post( $post_id, $form_id ) );
		}

		return array_merge( $embeds, $this->in_widgets( $form_id ) );
	}

	/**
	 * Re-detects one embed by key, e.g. just before swapping it.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $key     Embed key.
	 *
	 * @return Embed|null The embed, or null if it is no longer there.
	 */
	public function find_one( int $form_id, string $key ): ?Embed {
		$parts   = explode( ':', $key );
		$scope   = 'widget' === $parts[0] ? $this->in_widgets( $form_id ) : $this->in_post( (int) ( $parts[1] ?? 0 ), $form_id );
		$matches = array_filter( $scope, static fn( Embed $embed ): bool => $key === $embed->key );

		return array() === $matches ? null : reset( $matches );
	}

	/**
	 * Finds a form's embeds in one post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $form_id Form ID.
	 *
	 * @return array<int, Embed> Embeds.
	 */
	public function in_post( int $post_id, int $form_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_status, self::STATUSES, true ) ) {
			return array();
		}

		$meta    = get_post_meta( $post_id );
		$meta    = is_array( $meta ) ? $meta : array();
		$primary = self::is_elementor_built( $post_id ) ? $this->elementor_embeds( $post_id, $meta, $form_id ) : $this->content_embeds( $post, $form_id );

		return array_merge( $primary, $this->meta_embeds( $post_id, $meta, $form_id ) );
	}

	/**
	 * Tells whether a post is edited with Elementor.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if _elementor_edit_mode is "builder".
	 */
	public static function is_elementor_built( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Lists the post types searched.
	 *
	 * @return array<int, string> Post types.
	 */
	public function post_types(): array {
		$types = array_values( get_post_types( array( 'public' => true ) ) );

		foreach ( self::EXTRA_POST_TYPES as $extra ) {
			if ( post_type_exists( $extra ) ) {
				$types[] = $extra;
			}
		}

		$types = apply_filters( 'gf2el_post_types', array_values( array_diff( array_unique( $types ), array( 'attachment' ) ) ) );

		return array_values( array_filter( (array) $types, 'is_string' ) );
	}

	/**
	 * Builds embeds for an Elementor document.
	 *
	 * @param int                               $post_id Post ID.
	 * @param array<string, array<int, string>> $meta    Raw post meta.
	 * @param int                               $form_id Form ID.
	 *
	 * @return array<int, Embed> Embeds.
	 */
	private function elementor_embeds( int $post_id, array $meta, int $form_id ): array {
		$elements = json_decode( (string) ( $meta['_elementor_data'][0] ?? '' ), true );
		$embeds   = array();

		foreach ( $this->elementor->locate( is_array( $elements ) ? $elements : array(), $form_id ) as $location ) {
			$method   = ElementorLocation::WIDGET_REPLACE === $location->action && ! in_array( $location->widget_type, array( 'shortcode', 'text-editor', 'html' ), true ) ? 'elementor_widget' : 'elementor_shortcode';
			$embeds[] = new Embed( "post:{$post_id}:elementor:{$location->element_id}", $form_id, Embed::KIND_ELEMENTOR, $post_id, $location->element_id, $location->action, $method, $location->widget_type );
		}

		return $embeds;
	}

	/**
	 * Builds the content embed of a non-Elementor post (one per post, covering every occurrence).
	 *
	 * @param WP_Post $post    Post.
	 * @param int     $form_id Form ID.
	 *
	 * @return array<int, Embed> Zero or one embed.
	 */
	private function content_embeds( WP_Post $post, int $form_id ): array {
		$matches = array_filter( $this->blocks->detect( $post->post_content ), static fn( EmbedMatch $hit ): bool => $form_id === $hit->form_id() );

		if ( array() === $matches ) {
			return array();
		}

		$methods = array_unique( array_map( static fn( EmbedMatch $hit ): string => $hit->embed_method(), $matches ) );

		return array( new Embed( "post:{$post->ID}:content", $form_id, Embed::KIND_CONTENT, (int) $post->ID, '', Embed::ACTION_TEMPLATE, implode( '+', $methods ) ) );
	}

	/**
	 * Builds report-only embeds for other meta keys.
	 *
	 * @param int                               $post_id Post ID.
	 * @param array<string, array<int, string>> $meta    Raw post meta.
	 * @param int                               $form_id Form ID.
	 *
	 * @return array<int, Embed> Embeds.
	 */
	private function meta_embeds( int $post_id, array $meta, int $form_id ): array {
		$embeds = array();

		foreach ( $meta as $meta_key => $values ) {
			foreach ( (array) $values as $value ) {
				$found = array_filter( $this->meta->detect( (string) $meta_key, (string) $value ), static fn( EmbedMatch $hit ): bool => $form_id === $hit->form_id() );

				if ( array() !== $found ) {
					$embeds[ $meta_key ] = new Embed( "meta:{$post_id}:{$meta_key}", $form_id, Embed::KIND_META, $post_id, (string) $meta_key, Embed::ACTION_REPORT_ONLY, 'meta', (string) $meta_key );
				}
			}
		}

		return array_values( $embeds );
	}

	/**
	 * Finds a form's embeds in sidebar widgets.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<int, Embed> Embeds.
	 */
	private function in_widgets( int $form_id ): array {
		$embeds   = array();
		$sidebars = $this->widget_sidebars();

		foreach ( self::WIDGET_FIELDS as $base => $field ) {
			$option = get_option( 'widget_' . $base, array() );

			foreach ( is_array( $option ) ? $option : array() as $number => $instance ) {
				$content = is_int( $number ) && is_array( $instance ) ? ( $instance[ $field ] ?? '' ) : '';
				$matches = 'block' === $base ? $this->blocks->detect( (string) $content ) : $this->shortcodes->detect( (string) $content );
				$found   = array_filter( $matches, static fn( EmbedMatch $hit ): bool => $form_id === $hit->form_id() );

				if ( array() !== $found ) {
					$widget_id = $base . '-' . $number;
					$embeds[]  = new Embed( "widget:{$base}:{$number}", $form_id, Embed::KIND_WIDGET, 0, $widget_id, Embed::ACTION_TEMPLATE, 'widget', $sidebars[ $widget_id ] ?? '' );
				}
			}
		}

		return $embeds;
	}

	/**
	 * Maps widget IDs to their sidebar.
	 *
	 * @return array<string, string> Widget ID => sidebar ID.
	 */
	private function widget_sidebars(): array {
		$sidebars = get_option( 'sidebars_widgets', array() );
		$map      = array();

		foreach ( is_array( $sidebars ) ? $sidebars : array() as $sidebar_id => $widget_ids ) {
			foreach ( is_array( $widget_ids ) ? $widget_ids : array() as $widget_id ) {
				$map[ (string) $widget_id ] = (string) $sidebar_id;
			}
		}

		return $map;
	}

	/**
	 * Lists posts whose content or meta mention Gravity Forms.
	 *
	 * @return array<int, int> Post IDs, ascending.
	 */
	private function candidate_ids(): array {
		global $wpdb;

		$types = $this->post_types();

		if ( array() === $types ) {
			return array();
		}

		$type_list   = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$status_list = implode( ', ', array_fill( 0, count( self::STATUSES ), '%s' ) );
		$content_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$type_list}) AND post_status IN ({$status_list}) AND ( post_content LIKE %s OR post_content LIKE %s )", array_merge( $types, self::STATUSES, array( '%' . $wpdb->esc_like( '[gravityform' ) . '%', '%' . $wpdb->esc_like( 'wp:gravityforms/' ) . '%' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder lists are built from counts; replacements are one merged array.
		$meta_ids    = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ({$type_list}) AND p.post_status IN ({$status_list}) AND pm.meta_value LIKE %s", array_merge( $types, self::STATUSES, array( '%' . $wpdb->esc_like( 'gravity' ) . '%' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder lists are built from counts; replacements are one merged array.
		$ids         = array_values( array_unique( array_map( 'intval', array_merge( $content_ids, $meta_ids ) ) ) );

		sort( $ids );

		return $ids;
	}
}
