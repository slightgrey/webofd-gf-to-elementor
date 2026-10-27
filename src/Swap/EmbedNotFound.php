<?php
/**
 * Thrown when an embed is no longer where the migration expected it.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Swap;

/**
 * The page was edited after the preview, so the embed is gone or changed.
 */
class EmbedNotFound extends \RuntimeException {
}
