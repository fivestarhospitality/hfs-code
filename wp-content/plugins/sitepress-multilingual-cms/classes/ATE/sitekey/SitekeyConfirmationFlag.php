<?php

namespace WPML\TM\ATE\Sitekey;

use WPML\WP\OptionManager;

/**
 * Helper class for managing the site key confirmation flag.
 * This flag tracks whether site key has been successfully confirmed with AMS.
 */
class SitekeyConfirmationFlag {

	/**
	 * Mark site key confirmation as pending (needs to be confirmed).
	 *
	 * @return void
	 */
	public static function markAsPending() {
		OptionManager::update( 'TM-has-run', Sync::class, false );
	}

	/**
	 * Mark site key confirmation as completed (successfully confirmed with AMS).
	 *
	 * @return void
	 */
	public static function markAsCompleted() {
		OptionManager::update( 'TM-has-run', Sync::class, true );
	}

	/**
	 * Check if site key confirmation has been completed.
	 *
	 * @return bool True if confirmation completed, false otherwise
	 */
	public static function isCompleted(): bool {
		return OptionManager::getOr( false, 'TM-has-run', Sync::class );
	}
}
