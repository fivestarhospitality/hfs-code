<?php

namespace WPML\Translation\TranslationElements;

/**
 * Centralized checker for zlib extension function availability.
 * Supports wp-config.php constant override for testing scenarios.
 */
class ZlibAvailabilityChecker {

	/**
	 * Check if gzcompress function is available.
	 * Can be overridden via WPML_SIMULATE_MISSING_ZLIB constant for testing.
	 *
	 * @return bool True if gzcompress can be used
	 */
	public static function isGzcompressAvailable() {
		if ( defined( 'WPML_SIMULATE_MISSING_ZLIB' ) && WPML_SIMULATE_MISSING_ZLIB === true ) {
			return false;
		}
		return function_exists( 'gzcompress' );
	}

	/**
	 * Check if gzuncompress function is available.
	 * Can be overridden via WPML_SIMULATE_MISSING_ZLIB constant for testing.
	 *
	 * @return bool True if gzuncompress can be used
	 */
	public static function isGzuncompressAvailable() {
		if ( defined( 'WPML_SIMULATE_MISSING_ZLIB' ) && WPML_SIMULATE_MISSING_ZLIB === true ) {
			return false;
		}
		return function_exists( 'gzuncompress' );
	}
}
