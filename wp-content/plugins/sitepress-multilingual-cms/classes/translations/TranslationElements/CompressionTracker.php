<?php

namespace WPML\Translation\TranslationElements;

/**
 * Tracks when translation jobs use field compression.
 * Stores the latest compressed job_id in wp_options for error detection.
 */
class CompressionTracker {

	const OPTION_NAME = 'wpml_tm_last_compressed_job_id';

	/**
	 * Record that a job used compression successfully.
	 *
	 * @param int $job_id The job ID that used compression.
	 *
	 * @return bool Whether the option was updated.
	 */
	public static function recordCompression( $job_id ) {
		if ( ! is_numeric( $job_id ) || $job_id <= 0 ) {
			return false;
		}

		$current = get_option( self::OPTION_NAME, null );

		// Only update if this is a newer job_id
		if ( $current === null || $job_id > (int) $current['job_id'] ) {
			$data = [
				'job_id'    => (int) $job_id,
				'timestamp' => time(),
				'version'   => defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : 'unknown',
			];

			return update_option( self::OPTION_NAME, $data, false );
		}

		return false;
	}

	/**
	 * Check if a job might have compressed data and gzuncompress is unavailable.
	 *
	 * We recorded the last job which used compression. So if the input job is greater than
	 * the last compressed, then we know that it does not contain compressed data, so there is no need to
	 * display the error notice.
	 *
	 * @param int $job_id The job ID to check.
	 *
	 * @return bool True if we should show the missing zlib error.
	 */
	public static function shouldShowMissingZlibError( $job_id ) {
		// If gzuncompress exists, no problem
		if ( ZlibAvailabilityChecker::isGzuncompressAvailable() ) {
			return false;
		}

		$tracked = get_option( self::OPTION_NAME, null );

		// No compression ever used
		if ( $tracked === null ) {
			return false;
		}

		// This job was created before or during the compressed job period
		return (int) $job_id <= (int) $tracked['job_id'];
	}
}
