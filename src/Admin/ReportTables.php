<?php
/**
 * HTML for the warnings, counts, conversion entries and embeds shared by preview and report.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Convert\ResultStatus;

/**
 * Prints escaped tables. Entries and embeds are the plain arrays stored in reports.
 */
class ReportTables {

	/**
	 * Entry subjects shown in the fields table.
	 */
	public const FIELD_SUBJECTS = array( 'field', 'page', 'logic', 'form' );

	/**
	 * Entry subjects shown in the emails and confirmation table.
	 */
	public const SETTINGS_SUBJECTS = array( 'notification', 'confirmation' );

	/**
	 * Prints the headline warnings.
	 *
	 * @param array<int, string> $warnings Warnings.
	 *
	 * @return void
	 */
	public function warnings( array $warnings ): void {
		if ( array() === $warnings ) {
			return;
		}

		echo '<div class="notice notice-warning inline gf2el-warnings"><ul>';

		foreach ( $warnings as $warning ) {
			printf( '<li>%s</li>', esc_html( $warning ) );
		}

		echo '</ul></div>';
	}

	/**
	 * Prints the converted / approximated / dropped totals.
	 *
	 * @param array<string, int> $counts Status => count.
	 *
	 * @return void
	 */
	public function counts( array $counts ): void {
		echo '<p class="gf2el-counts">';

		foreach ( ResultStatus::cases() as $status ) {
			printf( '<span class="gf2el-badge gf2el-badge--%1$s">%2$s: %3$s</span> ', esc_attr( $status->value ), esc_html( $status->label() ), esc_html( number_format_i18n( (int) ( $counts[ $status->value ] ?? 0 ) ) ) );
		}

		echo '</p>';
	}

	/**
	 * Prints a table of conversion entries with the given subjects.
	 *
	 * @param array<int, array<string, string>> $entries  Entries.
	 * @param array<int, string>                $subjects Subjects to include.
	 * @param string                            $heading  Section heading.
	 *
	 * @return void
	 */
	public function entries( array $entries, array $subjects, string $heading ): void {
		$rows = array_filter( $entries, static fn( array $entry ): bool => in_array( $entry['subject'] ?? '', $subjects, true ) );

		if ( array() === $rows ) {
			return;
		}

		printf( '<h2>%s</h2>', esc_html( $heading ) );
		echo '<table class="widefat striped gf2el-entries"><thead><tr>';
		printf( '<th>%1$s</th><th>%2$s</th><th>%3$s</th><th>%4$s</th>', esc_html__( 'Gravity Forms', 'webofd-gf-to-elementor' ), esc_html__( 'Becomes', 'webofd-gf-to-elementor' ), esc_html__( 'Result', 'webofd-gf-to-elementor' ), esc_html__( 'Details', 'webofd-gf-to-elementor' ) );
		echo '</tr></thead><tbody>';

		foreach ( $rows as $entry ) {
			$this->entry_row( $entry );
		}

		echo '</tbody></table>';
	}

	/**
	 * Prints the embeds table, with checkboxes (preview) or results (report).
	 *
	 * @param array<int|string, array<string, mixed>> $embeds    Embed rows (describe() data, plus status/message in reports).
	 * @param bool                                    $selectable Show checkboxes instead of results.
	 *
	 * @return void
	 */
	public function embeds( array $embeds, bool $selectable ): void {
		if ( array() === $embeds ) {
			printf( '<p>%s</p>', esc_html__( 'This form is not embedded anywhere. Migrating creates a saved Elementor template you can place by hand.', 'webofd-gf-to-elementor' ) );
			return;
		}

		echo '<div class="gf2el-scroll"><table class="widefat striped gf2el-embeds"><thead><tr>';
		echo $selectable ? '<td class="check-column"><input type="checkbox" class="gf2el-select-all" checked aria-label="' . esc_attr__( 'Select all', 'webofd-gf-to-elementor' ) . '"></td>' : '';
		printf( '<th>%1$s</th><th>%2$s</th><th>%3$s</th><th>%4$s</th>', esc_html__( 'Location', 'webofd-gf-to-elementor' ), esc_html__( 'Type / status', 'webofd-gf-to-elementor' ), esc_html__( 'Embedded as', 'webofd-gf-to-elementor' ), $selectable ? esc_html__( 'What will happen', 'webofd-gf-to-elementor' ) : esc_html__( 'Result', 'webofd-gf-to-elementor' ) );
		echo '</tr></thead><tbody>';

		foreach ( $embeds as $embed ) {
			$this->embed_row( $embed, $selectable );
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Prints one entry row.
	 *
	 * @param array<string, string> $entry Entry.
	 *
	 * @return void
	 */
	private function entry_row( array $entry ): void {
		$status = ResultStatus::tryFrom( (string) ( $entry['status'] ?? '' ) ) ?? ResultStatus::Dropped;

		printf(
			'<tr><td>%1$s</td><td><code>%2$s</code></td><td><span class="gf2el-badge gf2el-badge--%3$s">%4$s</span></td><td>%5$s</td></tr>',
			esc_html( (string) ( $entry['gf_ref'] ?? '' ) ),
			esc_html( '' !== (string) ( $entry['elementor_ref'] ?? '' ) ? (string) $entry['elementor_ref'] : '—' ),
			esc_attr( $status->value ),
			esc_html( $status->label() ),
			esc_html( (string) ( $entry['reason'] ?? '' ) )
		);
	}

	/**
	 * Prints one embed row.
	 *
	 * @param array<string, mixed> $embed      Embed row.
	 * @param bool                 $selectable Show a checkbox.
	 *
	 * @return void
	 */
	private function embed_row( array $embed, bool $selectable ): void {
		$key        = (string) ( $embed['key'] ?? '' );
		$swappable  = 'report_only' !== ( $embed['action'] ?? '' );
		$last_cell  = $selectable ? esc_html( (string) ( $embed['planned'] ?? '' ) ) : $this->result_cell( $embed );
		$type_label = trim( (string) ( $embed['type_label'] ?? '' ) . ' · ' . (string) ( $embed['post_status'] ?? '' ), ' ·' );

		printf( '<tr data-key="%s">', esc_attr( $key ) );

		if ( $selectable ) {
			echo $swappable
				? '<th class="check-column"><input type="checkbox" name="embeds[]" value="' . esc_attr( $key ) . '" checked></th>'
				: '<th class="check-column"></th>';
		}

		printf(
			'<td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="gf2el-result">%4$s</td></tr>',
			$this->location_cell( $embed ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in location_cell().
			esc_html( $type_label ),
			esc_html( trim( (string) ( $embed['method'] ?? '' ) . ' ' . ( '' !== (string) ( $embed['context'] ?? '' ) ? '(' . (string) $embed['context'] . ')' : '' ) ) ),
			$last_cell // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above or in result_cell().
		);
	}

	/**
	 * Builds the location cell: title plus Edit / View links.
	 *
	 * @param array<string, mixed> $embed Embed row.
	 *
	 * @return string Escaped HTML.
	 */
	private function location_cell( array $embed ): string {
		$html  = '<strong>' . esc_html( (string) ( $embed['title'] ?? '' ) ) . '</strong>';
		$links = array();

		if ( '' !== (string) ( $embed['edit_url'] ?? '' ) ) {
			$links[] = '<a href="' . esc_url( (string) $embed['edit_url'] ) . '">' . esc_html__( 'Edit', 'webofd-gf-to-elementor' ) . '</a>';
		}

		if ( '' !== (string) ( $embed['view_url'] ?? '' ) ) {
			$links[] = '<a href="' . esc_url( (string) $embed['view_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'webofd-gf-to-elementor' ) . '</a>';
		}

		return $html . ( array() === $links ? '' : '<div class="row-actions visible">' . implode( ' | ', $links ) . '</div>' );
	}

	/**
	 * Builds a report's result cell: status badge and message.
	 *
	 * @param array<string, mixed> $embed Embed row.
	 *
	 * @return string Escaped HTML.
	 */
	private function result_cell( array $embed ): string {
		$status = (string) ( $embed['status'] ?? '' );
		$labels = array(
			'done'         => __( 'Swapped', 'webofd-gf-to-elementor' ),
			'skipped'      => __( 'Skipped', 'webofd-gf-to-elementor' ),
			'failed'       => __( 'Failed', 'webofd-gf-to-elementor' ),
			'queued'       => __( 'Not processed', 'webofd-gf-to-elementor' ),
			'not_selected' => __( 'Not selected', 'webofd-gf-to-elementor' ),
			'report_only'  => __( 'Change by hand', 'webofd-gf-to-elementor' ),
		);
		$html   = sprintf( '<span class="gf2el-badge gf2el-badge--%1$s">%2$s</span>', esc_attr( $status ), esc_html( $labels[ $status ] ?? $status ) );

		return '' === (string) ( $embed['message'] ?? '' ) ? $html : $html . ' ' . esc_html( (string) $embed['message'] );
	}
}
