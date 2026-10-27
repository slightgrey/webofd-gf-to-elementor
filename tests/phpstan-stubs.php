<?php
/**
 * Minimal declarations of the Gravity Forms and Elementor APIs the plugin calls,
 * so PHPStan can analyse the code without scanning those plugins.
 *
 * @package GFToElementor
 */

// phpcs:ignoreFile -- Analysis stubs, never loaded at runtime.

namespace {

	/**
	 * Gravity Forms public API.
	 */
	class GFAPI {

		/**
		 * Returns a form.
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return array<string, mixed>|false Form or false.
		 */
		public static function get_form( $form_id ) {
			return false;
		}

		/**
		 * Returns feeds.
		 *
		 * @param int[]|null  $feed_ids   Feed IDs.
		 * @param int|null    $form_id    Form ID.
		 * @param string|null $addon_slug Add-on slug.
		 * @param bool|null   $is_active  Active filter.
		 *
		 * @return array<int, array<string, mixed>>|\WP_Error Feeds.
		 */
		public static function get_feeds( $feed_ids = null, $form_id = null, $addon_slug = null, $is_active = true ) {
			return array();
		}
	}
}

namespace Elementor {

	/**
	 * Elementor utilities.
	 */
	class Utils {

		/**
		 * Returns a random 7-character element ID.
		 *
		 * @return string ID.
		 */
		public static function generate_random_string() {
			return '';
		}
	}

	/**
	 * Elementor's main plugin instance.
	 */
	class Plugin {

		/**
		 * Singleton.
		 *
		 * @var Plugin
		 */
		public static $instance;

		/**
		 * Documents manager.
		 *
		 * @var Core\Documents_Manager
		 */
		public $documents;

		/**
		 * Experiments manager.
		 *
		 * @var Core\Experiments\Manager
		 */
		public $experiments;
	}
}

namespace Elementor\Core {

	/**
	 * Documents manager.
	 */
	class Documents_Manager {

		/**
		 * Returns a post's document.
		 *
		 * @param int  $post_id    Post ID.
		 * @param bool $from_cache Use the cache.
		 *
		 * @return Base\Document|false Document.
		 */
		public function get( $post_id, $from_cache = true ) {
			return false;
		}
	}
}

namespace Elementor\Core\Base {

	/**
	 * Elementor document.
	 */
	class Document {

		/**
		 * Saves document data.
		 *
		 * @param array<string, mixed> $data Data with "elements" and/or "settings".
		 *
		 * @return bool Success.
		 */
		public function save( $data ) {
			return true;
		}
	}
}

namespace Elementor\Core\Experiments {

	/**
	 * Experiments manager.
	 */
	class Manager {

		/**
		 * Tells whether a feature is active.
		 *
		 * @param string $feature_name Feature.
		 *
		 * @return bool Active.
		 */
		public function is_feature_active( $feature_name ) {
			return true;
		}
	}
}

namespace Elementor\Core\Files\CSS {

	/**
	 * A post's generated CSS file.
	 */
	class Post {

		/**
		 * Creates the CSS file object.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @return self CSS file.
		 */
		public static function create( $post_id ) {
			return new self();
		}

		/**
		 * Deletes the file.
		 *
		 * @return void
		 */
		public function delete() {
		}
	}
}
