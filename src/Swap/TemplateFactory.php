<?php
/**
 * Creates the saved Elementor template that holds a migrated form.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

use GFToElementor\Migration\MigrationRepository;

/**
 * Non-Elementor embeds (classic content, blocks, widgets, mixed text) show the form
 * through [elementor-template id="X"]. One template per migration, created on first need.
 */
class TemplateFactory {

	/**
	 * Post meta linking a template to its Gravity Forms form.
	 */
	public const SOURCE_META = '_gf2el_source_form';

	/**
	 * Returns a new random element ID.
	 *
	 * @var callable(): string
	 */
	private $random_id;

	/**
	 * Creates the factory.
	 *
	 * @param MigrationRepository $migrations Migration records.
	 * @param ElementorDocuments  $documents  Elementor document access.
	 * @param callable(): string  $random_id  Random element ID generator.
	 */
	public function __construct(
		private readonly MigrationRepository $migrations,
		private readonly ElementorDocuments $documents,
		callable $random_id
	) {
		$this->random_id = $random_id;
	}

	/**
	 * Returns the migration's template, creating it the first time.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return int Template post ID.
	 *
	 * @throws \RuntimeException When the migration is missing or the template can't be created.
	 */
	public function get_or_create( int $migration_id ): int {
		$migration = $this->migrations->find( $migration_id );

		if ( null === $migration ) {
			throw new \RuntimeException( esc_html__( 'The migration record is missing.', 'webofd-gf-to-elementor' ) );
		}

		if ( null !== $migration['template_id'] && 'elementor_library' === get_post_type( $migration['template_id'] ) ) {
			return $migration['template_id'];
		}

		$template_id = $this->create( $migration['gf_form_id'], $migration['gf_form_title'], (array) ( $migration['report']['widget'] ?? array() ) );
		$this->migrations->set_template_id( $migration_id, $template_id );

		return $template_id;
	}

	/**
	 * Creates and fills a template post.
	 *
	 * @param int                  $form_id     GF form ID.
	 * @param string               $form_title  GF form title.
	 * @param array<string, mixed> $form_widget Form widget element.
	 *
	 * @return int Template post ID.
	 *
	 * @throws \RuntimeException When the post can't be created.
	 */
	private function create( int $form_id, string $form_title, array $form_widget ): int {
		$type        = $this->uses_containers() ? 'container' : 'section';
		$template_id = wp_insert_post(
			array(
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				/* translators: 1: Gravity Forms form ID, 2: form title. */
				'post_title'  => sprintf( __( 'GF #%1$d – %2$s (migrated)', 'webofd-gf-to-elementor' ), $form_id, $form_title ),
			),
			true
		);

		if ( is_wp_error( $template_id ) ) {
			throw new \RuntimeException( esc_html( $template_id->get_error_message() ) );
		}

		update_post_meta( $template_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $template_id, '_elementor_template_type', $type );
		update_post_meta( $template_id, self::SOURCE_META, $form_id );
		wp_set_object_terms( $template_id, $type, 'elementor_library_type' );

		$form_widget['id'] = ( $this->random_id )();
		$this->documents->save_elements( $template_id, $this->wrap( $type, $form_widget ) );

		return $template_id;
	}

	/**
	 * Wraps the widget in a container, or in a section and column on sites without containers.
	 *
	 * @param string               $type        "container" or "section".
	 * @param array<string, mixed> $form_widget Form widget element.
	 *
	 * @return array<int, array<string, mixed>> Element tree.
	 */
	private function wrap( string $type, array $form_widget ): array {
		if ( 'container' === $type ) {
			return array( $this->layout( 'container', array( $form_widget ) ) );
		}

		$column             = $this->layout( 'column', array( $form_widget ) );
		$column['settings'] = array( '_column_size' => 100 );

		return array( $this->layout( 'section', array( $column ) ) );
	}

	/**
	 * Builds a layout element.
	 *
	 * @param string                           $el_type  container, section or column.
	 * @param array<int, array<string, mixed>> $children Children.
	 *
	 * @return array<string, mixed> Element.
	 */
	private function layout( string $el_type, array $children ): array {
		return array(
			'id'       => ( $this->random_id )(),
			'elType'   => $el_type,
			'settings' => array(),
			'elements' => $children,
			'isInner'  => false,
		);
	}

	/**
	 * Tells whether Elementor's Flexbox Container feature is active.
	 *
	 * @return bool True if containers are active.
	 */
	private function uses_containers(): bool {
		return \Elementor\Plugin::$instance->experiments->is_feature_active( 'container' );
	}
}
