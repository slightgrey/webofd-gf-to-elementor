<?php
/**
 * Counts each form's embeds for the forms list, with a short cache.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Admin;

use GFToElementor\Detector\Embed;
use GFToElementor\Detector\EmbedFinder;

/**
 * Searching embeds reads every candidate post, so the counts are cached for a few
 * minutes and cleared whenever a migration changes pages.
 */
class EmbedCounter {

	/**
	 * Transient holding form ID => count.
	 */
	public const TRANSIENT = 'gf2el_embed_counts';

	/**
	 * Cache lifetime in seconds.
	 */
	private const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Returns the number of swappable embeds per form.
	 *
	 * @param array<int, int> $form_ids Form IDs.
	 *
	 * @return array<int, int> Form ID => count.
	 */
	public function counts( array $form_ids ): array {
		$cached = get_transient( self::TRANSIENT );
		$counts = is_array( $cached ) ? $cached : array();
		$finder = null;

		foreach ( $form_ids as $form_id ) {
			if ( ! isset( $counts[ $form_id ] ) ) {
				$finder             = $finder ?? EmbedFinder::create();
				$counts[ $form_id ] = count( array_filter( $finder->find( $form_id ), static fn( Embed $embed ): bool => $embed->is_swappable() ) );
			}
		}

		set_transient( self::TRANSIENT, $counts, self::TTL );

		return $counts;
	}

	/**
	 * Forgets the cached counts.
	 *
	 * @return void
	 */
	public static function flush(): void {
		delete_transient( self::TRANSIENT );
	}
}
