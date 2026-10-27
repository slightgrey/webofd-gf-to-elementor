<?php
/**
 * Runs a form migration: preview, start, swap each embed, finalize.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Migration;

use GFToElementor\Backup\BackupRepository;
use GFToElementor\Convert\ConversionContext;
use GFToElementor\Convert\Converter;
use GFToElementor\Detector\Embed;
use GFToElementor\Detector\EmbedFinder;
use GFToElementor\Source\GFFormReader;
use GFToElementor\Swap\EmbedNotFound;
use GFToElementor\Swap\EmbedSwapService;

/**
 * The migration is split into small steps so each REST request stays short:
 * start() converts and records, swap_embed() changes one location, finalize() sets the status.
 */
class Migrator {

	/**
	 * Embed result: swapped.
	 */
	public const EMBED_DONE = 'done';

	/**
	 * Embed result: gone or changed since the preview, left alone.
	 */
	public const EMBED_SKIPPED = 'skipped';

	/**
	 * Embed result: an error stopped the swap.
	 */
	public const EMBED_FAILED = 'failed';

	/**
	 * Creates the migrator.
	 *
	 * @param GFFormReader        $reader     Gravity Forms reader.
	 * @param Converter           $converter  Form converter.
	 * @param EmbedFinder         $finder     Embed finder.
	 * @param MigrationRepository $migrations Migration records.
	 * @param EmbedSwapService    $swaps      Embed swapper.
	 * @param EmbedPresenter      $presenter  Embed display data.
	 */
	public function __construct(
		private readonly GFFormReader $reader,
		private readonly Converter $converter,
		private readonly EmbedFinder $finder,
		private readonly MigrationRepository $migrations,
		private readonly EmbedSwapService $swaps,
		private readonly EmbedPresenter $presenter
	) {
	}

	/**
	 * Builds a migrator with default collaborators.
	 *
	 * @return self Migrator.
	 */
	public static function create(): self {
		$migrations = new MigrationRepository();

		return new self( new GFFormReader(), Converter::create(), EmbedFinder::create(), $migrations, EmbedSwapService::create( $migrations, new BackupRepository() ), new EmbedPresenter() );
	}

	/**
	 * Shows what migrating a form would do, without writing anything.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string, mixed> Form, warnings, counts, entries and embeds.
	 *
	 * @throws \InvalidArgumentException When the form doesn't exist.
	 */
	public function preview( int $form_id ): array {
		$form       = $this->form( $form_id );
		$conversion = $this->converter->convert( $form, ConversionContext::from_site( $this->reader->get_feeds( $form_id ) ) );
		$report     = $conversion->to_report();

		unset( $report['widget'] );

		return array(
			'form'   => $this->form_summary( $form ),
			'embeds' => array_map( array( $this->presenter, 'describe' ), $this->finder->find( $form_id ) ),
		) + $report;
	}

	/**
	 * Converts the form, records the migration and returns the embeds to swap.
	 *
	 * An \InvalidArgumentException from form() passes through when the form doesn't exist.
	 *
	 * @param int                $form_id    Form ID.
	 * @param array<int, string> $embed_keys Keys the user selected.
	 *
	 * @return array{migration_id: int, queue: array<int, string>} Migration ID and the valid keys, in page order.
	 *
	 * @throws \RuntimeException When the record can't be saved.
	 */
	public function start( int $form_id, array $embed_keys ): array {
		$form       = $this->form( $form_id );
		$conversion = $this->converter->convert( $form, ConversionContext::from_site( $this->reader->get_feeds( $form_id ) ) );
		$embeds     = $this->finder->find( $form_id );
		$report     = $conversion->to_report() + array(
			'form'   => $this->form_summary( $form ),
			'embeds' => $this->initial_embed_results( $embeds, $embed_keys ),
		);

		$migration_id = $this->migrations->create( $form_id, (string) $form['title'], $conversion->field_map, $report );

		if ( 0 === $migration_id ) {
			throw new \RuntimeException( esc_html__( 'The migration record could not be saved.', 'webofd-gf-to-elementor' ) );
		}

		return array(
			'migration_id' => $migration_id,
			'queue'        => array_keys( array_filter( $report['embeds'], static fn( array $row ): bool => 'queued' === $row['status'] ) ),
		);
	}

	/**
	 * Swaps one embed and records the result in the report.
	 *
	 * @param int    $migration_id Migration ID.
	 * @param string $key          Embed key.
	 *
	 * @return array<string, mixed> The embed's result row.
	 *
	 * @throws \InvalidArgumentException When the migration doesn't exist or isn't running.
	 */
	public function swap_embed( int $migration_id, string $key ): array {
		$migration = $this->running( $migration_id );
		$embed     = $this->finder->find_one( $migration['gf_form_id'], $key );

		try {
			$this->swap_checked( $migration, $key, $embed );
			$result = array( 'status' => self::EMBED_DONE );
		} catch ( EmbedNotFound $e ) {
			$result = array(
				'status'  => self::EMBED_SKIPPED,
				'message' => $e->getMessage(),
			);
		} catch ( \Throwable $e ) {
			$result = array(
				'status'  => self::EMBED_FAILED,
				'message' => $e->getMessage(),
			);
		}

		return $this->record_embed_result( $migration_id, $key, $result );
	}

	/**
	 * Sets the final status from the embed results.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return MigrationStatus Final status.
	 *
	 * @throws \InvalidArgumentException When the migration doesn't exist or isn't running.
	 */
	public function finalize( int $migration_id ): MigrationStatus {
		$migration = $this->running( $migration_id );
		$results   = array_column( (array) ( $migration['report']['embeds'] ?? array() ), 'status' );
		$done      = count( array_keys( $results, self::EMBED_DONE, true ) );
		$failed    = count( array_keys( $results, self::EMBED_FAILED, true ) );
		$status    = match ( true ) {
			$failed > 0 && 0 === $done => MigrationStatus::Failed,
			$failed > 0                => MigrationStatus::Partial,
			default                    => MigrationStatus::Migrated,
		};

		if ( 0 === $done && MigrationStatus::Failed !== $status ) {
			// Nothing on the site shows the form yet: keep it as a template to place by hand.
			$this->swaps->template_for( $migration_id );
		}

		$this->migrations->update_status( $migration_id, $status );

		return $status;
	}

	/**
	 * Checks the embed and the user's rights, then swaps it.
	 *
	 * @param array<string, mixed> $migration Migration row.
	 * @param string               $key       Embed key.
	 * @param Embed|null           $embed     Freshly detected embed.
	 *
	 * @return void
	 *
	 * @throws EmbedNotFound     When the embed is gone or not selected.
	 * @throws \RuntimeException When the user may not edit the location.
	 */
	private function swap_checked( array $migration, string $key, ?Embed $embed ): void {
		if ( 'queued' !== ( $migration['report']['embeds'][ $key ]['status'] ?? '' ) ) {
			throw new EmbedNotFound( esc_html__( 'This embed was not selected for this migration, or was already processed.', 'webofd-gf-to-elementor' ) );
		}

		if ( null === $embed ) {
			throw new EmbedNotFound( esc_html__( 'The form is no longer embedded here; the page was probably edited after the preview.', 'webofd-gf-to-elementor' ) );
		}

		if ( ! $this->user_can_edit( $embed ) ) {
			throw new \RuntimeException( esc_html__( 'You are not allowed to edit this location.', 'webofd-gf-to-elementor' ) );
		}

		$this->swaps->swap( (int) $migration['id'], $embed, (array) ( $migration['report']['widget'] ?? array() ) );
	}

	/**
	 * Tells whether the current user may edit the embed's location.
	 *
	 * @param Embed $embed Embed.
	 *
	 * @return bool True if allowed.
	 */
	private function user_can_edit( Embed $embed ): bool {
		return Embed::KIND_WIDGET === $embed->kind ? current_user_can( 'edit_theme_options' ) : current_user_can( 'edit_post', $embed->post_id );
	}

	/**
	 * Builds the starting result row of every embed: queued, not selected or report only.
	 *
	 * @param array<int, Embed>  $embeds     Embeds found now.
	 * @param array<int, string> $embed_keys Selected keys.
	 *
	 * @return array<string, array<string, mixed>> Key => result row with display data.
	 */
	private function initial_embed_results( array $embeds, array $embed_keys ): array {
		$rows = array();

		foreach ( $embeds as $embed ) {
			$status = match ( true ) {
				! $embed->is_swappable()                      => 'report_only',
				in_array( $embed->key, $embed_keys, true ) => 'queued',
				default                                       => 'not_selected',
			};

			$rows[ $embed->key ] = array( 'status' => $status ) + $this->presenter->describe( $embed );
		}

		return $rows;
	}

	/**
	 * Saves one embed's result into the migration report.
	 *
	 * @param int                  $migration_id Migration ID.
	 * @param string               $key          Embed key.
	 * @param array<string, mixed> $result       Status and optional message.
	 *
	 * @return array<string, mixed> The updated row.
	 */
	private function record_embed_result( int $migration_id, string $key, array $result ): array {
		$migration = $this->migrations->find( $migration_id );
		$report    = null === $migration ? array() : $migration['report'];
		$row       = array_merge( (array) ( $report['embeds'][ $key ] ?? array( 'key' => $key ) ), $result, array( 'at' => current_time( 'mysql', true ) ) );

		$report['embeds'][ $key ] = $row;
		$this->migrations->save_report( $migration_id, $report );

		return $row;
	}

	/**
	 * Loads a running migration.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return array<string, mixed> Migration row.
	 *
	 * @throws \InvalidArgumentException When it doesn't exist or isn't running.
	 */
	private function running( int $migration_id ): array {
		$migration = $this->migrations->find( $migration_id );

		if ( null === $migration || MigrationStatus::Running !== $migration['status'] ) {
			throw new \InvalidArgumentException( esc_html__( 'This migration does not exist or has already finished.', 'webofd-gf-to-elementor' ) );
		}

		return $migration;
	}

	/**
	 * Loads a form or fails.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string, mixed> Form.
	 *
	 * @throws \InvalidArgumentException When the form doesn't exist.
	 */
	private function form( int $form_id ): array {
		$form = $this->reader->get_form( $form_id );

		if ( null === $form ) {
			/* translators: %d: form ID. */
			throw new \InvalidArgumentException( sprintf( esc_html__( 'Gravity Forms form #%d does not exist.', 'webofd-gf-to-elementor' ), (int) $form_id ) );
		}

		return $form;
	}

	/**
	 * Returns the form fields shown in previews and reports.
	 *
	 * @param array<string, mixed> $form Form.
	 *
	 * @return array{id: int, title: string, is_active: bool} Summary.
	 */
	private function form_summary( array $form ): array {
		return array(
			'id'        => (int) $form['id'],
			'title'     => (string) $form['title'],
			'is_active' => '1' === (string) ( $form['is_active'] ?? '1' ),
		);
	}
}
