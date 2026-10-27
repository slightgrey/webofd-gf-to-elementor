<?php
/**
 * The list of Gravity Forms forms and their migration state.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Migration\MigrationRepository;
use GFToElementor\Migration\MigrationStatus;
use GFToElementor\Source\GFFormReader;

/**
 * WP_List_Table of forms. Only load this file after wp-admin/includes/class-wp-list-table.php.
 */
class FormsTable extends \WP_List_Table {

	/**
	 * Creates the table.
	 *
	 * @param GFFormReader        $reader     Gravity Forms reader.
	 * @param MigrationRepository $migrations Migration records.
	 * @param EmbedCounter        $counter    Embed counter.
	 */
	public function __construct(
		private readonly GFFormReader $reader,
		private readonly MigrationRepository $migrations,
		private readonly EmbedCounter $counter
	) {
		parent::__construct(
			array(
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Returns the column headers.
	 *
	 * @return array<string, string> Column key => label.
	 */
	public function get_columns(): array {
		return array(
			'id'      => __( 'ID', 'webofd-gf-to-elementor' ),
			'title'   => __( 'Form', 'webofd-gf-to-elementor' ),
			'active'  => __( 'Active', 'webofd-gf-to-elementor' ),
			'fields'  => __( 'Fields', 'webofd-gf-to-elementor' ),
			'embeds'  => __( 'Embeds', 'webofd-gf-to-elementor' ),
			'status'  => __( 'Migration', 'webofd-gf-to-elementor' ),
			'updated' => __( 'Date', 'webofd-gf-to-elementor' ),
		);
	}

	/**
	 * Loads the rows.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$forms       = $this->reader->list_forms();
		$latest      = $this->migrations->latest_by_form();
		$counts      = $this->counter->counts( array_column( $forms, 'id' ) );
		$this->items = array();

		foreach ( $forms as $form ) {
			$this->items[] = $form + array(
				'embeds'    => $counts[ $form['id'] ] ?? 0,
				'migration' => $latest[ $form['id'] ] ?? null,
			);
		}

		$this->_column_headers = array( $this->get_columns(), array(), array(), $this->get_default_primary_column_name() );
	}

	/**
	 * Makes the form title the primary column (row actions and the mobile toggle live there).
	 *
	 * @return string Column key.
	 */
	protected function get_default_primary_column_name(): string {
		return 'title';
	}

	/**
	 * Shows the empty-state message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No Gravity Forms forms were found.', 'webofd-gf-to-elementor' );
	}

	/**
	 * Renders a cell without a dedicated method.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column key.
	 *
	 * @return string Escaped HTML.
	 */
	protected function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'id'      => esc_html( (string) $item['id'] ),
			'active'  => $item['is_active'] ? esc_html__( 'Yes', 'webofd-gf-to-elementor' ) : esc_html__( 'No', 'webofd-gf-to-elementor' ),
			'fields'  => esc_html( number_format_i18n( (int) $item['field_count'] ) ),
			'embeds'  => esc_html( number_format_i18n( (int) $item['embeds'] ) ),
			'status'  => $this->status_badge( $item['migration'] ),
			'updated' => null === $item['migration'] ? '&mdash;' : esc_html( $this->local_date( (string) $item['migration']['updated_at'] ) ),
			default   => '',
		};
	}

	/**
	 * Renders the title cell with its row actions.
	 *
	 * @param array<string, mixed> $item Row.
	 *
	 * @return string Escaped HTML.
	 */
	protected function column_title( $item ): string {
		$preview = AdminPage::url(
			array(
				'action' => 'preview',
				'form'   => (int) $item['id'],
			)
		);
		$title   = sprintf( '<strong><a class="row-title" href="%1$s">%2$s</a></strong>', esc_url( $preview ), esc_html( (string) $item['title'] ) );

		return $title . $this->row_actions( $this->actions_for( $item, $preview ) );
	}

	/**
	 * Builds a row's actions.
	 *
	 * @param array<string, mixed> $item    Row.
	 * @param string               $preview Preview URL.
	 *
	 * @return array<string, string> Action key => HTML link.
	 */
	private function actions_for( array $item, string $preview ): array {
		$actions   = array( 'preview' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $preview ), esc_html__( 'Preview & Migrate', 'webofd-gf-to-elementor' ) ) );
		$migration = $item['migration'];

		if ( null === $migration ) {
			return $actions;
		}

		$report            = AdminPage::url(
			array(
				'action'    => 'report',
				'migration' => (int) $migration['id'],
			)
		);
		$actions['report'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $report ), esc_html__( 'View report', 'webofd-gf-to-elementor' ) );

		if ( in_array( $migration['status'], array( MigrationStatus::Running, MigrationStatus::Migrated, MigrationStatus::Partial, MigrationStatus::Failed ), true ) ) {
			$actions['rollback'] = sprintf( '<a href="#" class="gf2el-rollback" data-migration="%1$d">%2$s</a>', (int) $migration['id'], esc_html__( 'Roll back', 'webofd-gf-to-elementor' ) );
		}

		return $actions;
	}

	/**
	 * Renders a migration status badge.
	 *
	 * @param array<string, mixed>|null $migration Latest migration summary.
	 *
	 * @return string Escaped HTML.
	 */
	private function status_badge( ?array $migration ): string {
		if ( null === $migration ) {
			return '<span class="gf2el-badge gf2el-badge--none">' . esc_html__( 'Not migrated', 'webofd-gf-to-elementor' ) . '</span>';
		}

		$status = $migration['status'];

		return sprintf( '<span class="gf2el-badge gf2el-badge--%1$s">%2$s</span>', esc_attr( $status->value ), esc_html( $status->label() ) );
	}

	/**
	 * Formats a UTC MySQL date in the site's time zone and format.
	 *
	 * @param string $gmt_date "Y-m-d H:i:s" in UTC.
	 *
	 * @return string Date.
	 */
	private function local_date( string $gmt_date ): string {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $gmt_date . ' UTC' ) );
	}
}
