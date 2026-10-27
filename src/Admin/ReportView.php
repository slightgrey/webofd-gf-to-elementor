<?php
/**
 * The saved report of one migration.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Migration\MigrationRepository;
use GFToElementor\Migration\MigrationStatus;

/**
 * Shows what a migration did: each location's result, the template, and the
 * conversion details. Hosts the Roll back button.
 */
class ReportView {

	/**
	 * Creates the view.
	 *
	 * @param MigrationRepository $migrations Migration records.
	 */
	public function __construct( private readonly MigrationRepository $migrations ) {
	}

	/**
	 * Renders the report.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return void
	 */
	public function render( int $migration_id ): void {
		$migration = $this->migrations->find( $migration_id );

		if ( null === $migration ) {
			printf( '<p><a href="%1$s">&larr; %2$s</a></p>', esc_url( AdminPage::url() ), esc_html__( 'All forms', 'webofd-gf-to-elementor' ) );
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html__( 'This migration report does not exist.', 'webofd-gf-to-elementor' ) );
			return;
		}

		$report = $migration['report'];
		$tables = new ReportTables();

		$this->header( $migration );
		$this->template_line( $migration['template_id'] );
		$this->rollback_button( $migration );

		printf( '<h2>%s</h2>', esc_html__( 'Locations', 'webofd-gf-to-elementor' ) );
		$tables->embeds( (array) ( $report['embeds'] ?? array() ), false );
		$tables->warnings( (array) ( $report['warnings'] ?? array() ) );
		$tables->counts( (array) ( $report['counts'] ?? array() ) );
		$tables->entries( (array) ( $report['entries'] ?? array() ), ReportTables::FIELD_SUBJECTS, __( 'Fields', 'webofd-gf-to-elementor' ) );
		$tables->entries( (array) ( $report['entries'] ?? array() ), ReportTables::SETTINGS_SUBJECTS, __( 'Emails and confirmation', 'webofd-gf-to-elementor' ) );
	}

	/**
	 * Prints the title, status, date and author.
	 *
	 * @param array<string, mixed> $migration Migration row.
	 *
	 * @return void
	 */
	private function header( array $migration ): void {
		$status = $migration['status'];
		$user   = get_userdata( (int) $migration['user_id'] );
		$date   = (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $migration['updated_at'] . ' UTC' ) );

		/* translators: 1: form title, 2: form ID. */
		printf( '<h1>%s</h1>', esc_html( sprintf( __( 'Migration report: %1$s (#%2$d)', 'webofd-gf-to-elementor' ), (string) $migration['gf_form_title'], (int) $migration['gf_form_id'] ) ) );
		printf( '<p><a href="%1$s">&larr; %2$s</a></p>', esc_url( AdminPage::url() ), esc_html__( 'All forms', 'webofd-gf-to-elementor' ) );
		printf(
			'<p class="gf2el-meta"><span class="gf2el-badge gf2el-badge--%1$s">%2$s</span> %3$s</p>',
			esc_attr( $status->value ),
			esc_html( $status->label() ),
			/* translators: 1: date and time, 2: user display name. */
			esc_html( sprintf( __( 'Last updated %1$s by %2$s', 'webofd-gf-to-elementor' ), $date, $user ? $user->display_name : __( 'an unknown user', 'webofd-gf-to-elementor' ) ) )
		);
	}

	/**
	 * Prints the template's links, when the migration created one.
	 *
	 * @param int|null $template_id Template post ID.
	 *
	 * @return void
	 */
	private function template_line( ?int $template_id ): void {
		if ( null === $template_id || null === get_post( $template_id ) ) {
			return;
		}

		printf(
			'<p>%1$s <strong>%2$s</strong> &middot; <a href="%3$s">%4$s</a> &middot; <code>[elementor-template id="%5$d"]</code></p>',
			esc_html__( 'Saved template:', 'webofd-gf-to-elementor' ),
			esc_html( get_the_title( $template_id ) ),
			esc_url( admin_url( 'post.php?post=' . $template_id . '&action=elementor' ) ),
			esc_html__( 'Edit in Elementor', 'webofd-gf-to-elementor' ),
			(int) $template_id
		);
	}

	/**
	 * Prints the Roll back button, or a note that it was rolled back.
	 *
	 * @param array<string, mixed> $migration Migration row.
	 *
	 * @return void
	 */
	private function rollback_button( array $migration ): void {
		if ( MigrationStatus::RolledBack === $migration['status'] ) {
			printf( '<div class="notice notice-info inline"><p>%s</p></div>', esc_html__( 'This migration was rolled back. The pages show the Gravity Form again.', 'webofd-gf-to-elementor' ) );
			return;
		}

		if ( MigrationStatus::Running === $migration['status'] ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html__( 'This migration did not finish. Locations marked "Not processed" still show the Gravity Form; you can roll back what was swapped.', 'webofd-gf-to-elementor' ) );
		}

		printf(
			'<p><button type="button" class="button gf2el-rollback" data-migration="%1$d">%2$s</button> <span class="description">%3$s</span></p>',
			(int) $migration['id'],
			esc_html__( 'Roll back', 'webofd-gf-to-elementor' ),
			esc_html__( 'Restores every changed page and widget exactly as it was before this migration.', 'webofd-gf-to-elementor' )
		);
	}
}
