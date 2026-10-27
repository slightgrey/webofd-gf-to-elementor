<?php
/**
 * Swaps one embed by picking the right swapper and writer.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

use GFToElementor\Backup\BackupRepository;
use GFToElementor\Convert\ConversionContext;
use GFToElementor\Detector\Embed;
use GFToElementor\Migration\MigrationRepository;
use GFToElementor\Swap\Writer\ElementorDocumentWriter;
use GFToElementor\Swap\Writer\PostContentWriter;
use GFToElementor\Swap\Writer\WidgetOptionWriter;

/**
 * Elementor embeds go through the Elementor writer, post content through the post
 * writer, widgets through the option writer. Each one backs up before writing.
 */
class EmbedSwapService {

	/**
	 * Creates the service.
	 *
	 * @param TemplateFactory         $templates       Template factory.
	 * @param ElementorDataSwapper    $elementor_swap  Elementor tree swapper.
	 * @param ContentSwapper          $content_swap    Content swapper.
	 * @param WidgetSwapper           $widget_swap     Widget swapper.
	 * @param ElementorDocumentWriter $elementor_write Elementor writer.
	 * @param PostContentWriter       $content_write   Post content writer.
	 * @param WidgetOptionWriter      $widget_write    Widget option writer.
	 */
	public function __construct(
		private readonly TemplateFactory $templates,
		private readonly ElementorDataSwapper $elementor_swap,
		private readonly ContentSwapper $content_swap,
		private readonly WidgetSwapper $widget_swap,
		private readonly ElementorDocumentWriter $elementor_write,
		private readonly PostContentWriter $content_write,
		private readonly WidgetOptionWriter $widget_write
	) {
	}

	/**
	 * Builds the service with its default collaborators.
	 *
	 * @param MigrationRepository $migrations Migration records.
	 * @param BackupRepository    $backups    Backup storage.
	 *
	 * @return self Service.
	 */
	public static function create( MigrationRepository $migrations, BackupRepository $backups ): self {
		$shortcodes = new ShortcodeReplacer();
		$documents  = new ElementorDocuments();
		$content    = new ContentSwapper( $shortcodes );

		return new self(
			new TemplateFactory( $migrations, $documents, array( ConversionContext::class, 'random_element_id' ) ),
			new ElementorDataSwapper( $shortcodes ),
			$content,
			new WidgetSwapper( $content ),
			new ElementorDocumentWriter( $backups, $documents ),
			new PostContentWriter( $backups ),
			new WidgetOptionWriter( $backups )
		);
	}

	/**
	 * Returns the migration's template, creating it if needed.
	 *
	 * @param int $migration_id Migration ID.
	 *
	 * @return int Template post ID.
	 */
	public function template_for( int $migration_id ): int {
		return $this->templates->get_or_create( $migration_id );
	}

	/**
	 * Swaps one embed.
	 *
	 * @param int                  $migration_id Migration ID.
	 * @param Embed                $embed        Embed, freshly detected.
	 * @param array<string, mixed> $form_widget  Converted form widget.
	 *
	 * @return void
	 *
	 * @throws EmbedNotFound When the embed is report-only or changed since detection.
	 */
	public function swap( int $migration_id, Embed $embed, array $form_widget ): void {
		$template_id = $embed->needs_template() ? $this->templates->get_or_create( $migration_id ) : 0;
		$form_id     = $embed->form_id;

		match ( $embed->kind ) {
			Embed::KIND_ELEMENTOR => $this->elementor_write->write( $migration_id, $embed->post_id, fn( array $elements ): array => $this->elementor_swap->swap( $elements, $embed->object_ref, $embed->action, $form_id, $form_widget, $template_id ) ),
			Embed::KIND_CONTENT   => $this->content_write->write( $migration_id, $embed->post_id, fn( string $content ): string => $this->content_swap->swap( $content, $form_id, $template_id ) ),
			Embed::KIND_WIDGET    => $this->swap_widget( $migration_id, $embed, $template_id ),
			default               => throw new EmbedNotFound( esc_html__( 'This embed is report-only and must be changed by hand.', 'webofd-gf-to-elementor' ) ),
		};
	}

	/**
	 * Swaps a sidebar widget embed.
	 *
	 * @param int   $migration_id Migration ID.
	 * @param Embed $embed        Widget embed (key "widget:{base}:{number}").
	 * @param int   $template_id  Template ID.
	 *
	 * @return void
	 */
	private function swap_widget( int $migration_id, Embed $embed, int $template_id ): void {
		list( , $base, $number ) = array_pad( explode( ':', $embed->key ), 3, '' );

		$this->widget_write->write( $migration_id, 'widget_' . $base, fn( array $option ): array => $this->widget_swap->swap( $option, $base, (int) $number, $embed->form_id, $template_id ) );
	}
}
