<?php
/**
 * The preview & migrate screen for one form.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Dependencies;
use GFToElementor\Migration\MigrationRepository;
use GFToElementor\Migration\MigrationStatus;
use GFToElementor\Migration\Migrator;

/**
 * Shows what migrating a form would do, lets the user untick embeds, and hosts the
 * Migrate button (the migration itself runs from admin.js through the REST API).
 */
class PreviewView {

	/**
	 * Creates the view.
	 *
	 * @param Migrator            $migrator   Migrator (preview only here).
	 * @param MigrationRepository $migrations Migration records.
	 */
	public function __construct(
		private readonly Migrator $migrator,
		private readonly MigrationRepository $migrations
	) {
	}

	/**
	 * Renders the preview.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return void
	 */
	public function render( int $form_id ): void {
		try {
			$preview = $this->migrator->preview( $form_id );
		} catch ( \InvalidArgumentException $e ) {
			$this->back_link();
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $e->getMessage() ) );
			return;
		}

		/* translators: 1: form title, 2: form ID. */
		printf( '<h1>%s</h1>', esc_html( sprintf( __( 'Preview: %1$s (#%2$d)', 'webofd-gf-to-elementor' ), (string) $preview['form']['title'], $form_id ) ) );
		$this->back_link();
		$this->previous_migration_notice( $form_id );

		$tables = new ReportTables();
		$tables->warnings( (array) $preview['warnings'] );
		$tables->counts( (array) $preview['counts'] );
		$tables->entries( (array) $preview['entries'], ReportTables::FIELD_SUBJECTS, __( 'Fields', 'webofd-gf-to-elementor' ) );
		$tables->entries( (array) $preview['entries'], ReportTables::SETTINGS_SUBJECTS, __( 'Emails and confirmation', 'webofd-gf-to-elementor' ) );
		$this->migrate_form( $form_id, (array) $preview['embeds'], $tables );
	}

	/**
	 * Prints the embed checklist and the Migrate button.
	 *
	 * @param int                              $form_id Form ID.
	 * @param array<int, array<string, mixed>> $embeds  Embeds.
	 * @param ReportTables                     $tables  Table printer.
	 *
	 * @return void
	 */
	private function migrate_form( int $form_id, array $embeds, ReportTables $tables ): void {
		/* translators: %s: number of locations. */
		printf( '<h2>%s</h2>', esc_html( sprintf( _n( 'Where it is used (%s location)', 'Where it is used (%s locations)', count( $embeds ), 'webofd-gf-to-elementor' ), number_format_i18n( count( $embeds ) ) ) ) );
		printf( '<form id="gf2el-migrate" data-form="%d">', (int) $form_id );
		$tables->embeds( $embeds, true );

		echo '<p class="gf2el-actions">';
		printf(
			'<button type="submit" class="button button-primary"%1$s>%2$s</button> ',
			Dependencies::elementor_pro_active() ? '' : ' disabled',
			esc_html__( 'Migrate', 'webofd-gf-to-elementor' )
		);
		printf( '<span class="description">%s</span>', esc_html__( 'Every changed page is backed up first. The Gravity Forms form itself is not changed.', 'webofd-gf-to-elementor' ) );
		echo '</p></form>';
		echo '<div id="gf2el-progress" class="gf2el-progress" hidden aria-live="polite"></div>';
	}

	/**
	 * Mentions an earlier migration of the same form, with a link to its report.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return void
	 */
	private function previous_migration_notice( int $form_id ): void {
		$latest = $this->migrations->latest_for_form( $form_id );

		if ( null === $latest || MigrationStatus::RolledBack === $latest['status'] ) {
			return;
		}

		$report = AdminPage::url(
			array(
				'action'    => 'report',
				'migration' => $latest['id'],
			)
		);

		printf(
			'<div class="notice notice-info inline"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			/* translators: %s: migration status, e.g. "Migrated". */
			esc_html( sprintf( __( 'This form was migrated before (%s). Migrating again only changes places that still use the Gravity Form.', 'webofd-gf-to-elementor' ), $latest['status']->label() ) ),
			esc_url( $report ),
			esc_html__( 'View the earlier report', 'webofd-gf-to-elementor' )
		);
	}

	/**
	 * Prints the link back to the forms list.
	 *
	 * @return void
	 */
	private function back_link(): void {
		printf( '<p><a href="%1$s">&larr; %2$s</a></p>', esc_url( AdminPage::url() ), esc_html__( 'All forms', 'webofd-gf-to-elementor' ) );
	}
}
