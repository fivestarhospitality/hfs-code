<?php

namespace WPML\Translation\TranslationElements;

class FieldCompression {

	/**
	 * Checks if the provided data is already compressed with gzcompress and base64 encoded.
	 *
	 * @param string|null $data The data to check.
	 *
	 * @return bool True if the data is already compressed, false otherwise.
	 */
	public static function isCompressed( $data ) {
		if ( $data === null || $data === '' || ! ZlibAvailabilityChecker::isGzuncompressAvailable() ) {
			return false;
		}

		// Try to base64 decode the data
		$decoded = base64_decode( $data, true );
		if ( $decoded === false ) {
			return false;
		}

		// Simply try to decompress and check if it succeeds
		$decompressed = @gzuncompress( $decoded );
		return $decompressed !== false;
	}

	/**
	 * Checks for and fixes double compression in data.
	 * If the data is compressed twice, it will decompress it once to return singly-compressed data.
	 * If the data is not double-compressed, it returns the original data.
	 *
	 * @param string|null $data The potentially double-compressed data.
	 *
	 * @return array{
	 *     data: string|null,
	 *     was_double_compressed: bool
	 * } The fixed data and whether it was double-compressed.
	 */
	public static function fixDoubleCompression( $data ) {
		if ( $data === null || $data === '' || ! ZlibAvailabilityChecker::isGzuncompressAvailable() ) {
			return [
				'data'                  => $data,
				'was_double_compressed' => false
			];
		}

		if ( ! self::isCompressed( $data ) ) {
			return [
				'data'                  => $data,
				'was_double_compressed' => false
			];
		}

		$decompressed_once = self::decompress( $data, true );

		// Check if the result is still compressed
		if ( self::isCompressed( $decompressed_once ) ) {
			// It was double-compressed, return the singly-compressed version
			return [
				'data'                  => $decompressed_once,
				'was_double_compressed' => true
			];
		}

		// It was only compressed once, return the original data
		return [
			'data'                  => $data,
			'was_double_compressed' => false
		];
	}

	/**
	 * @param string|null $data
	 * @param bool $isAlreadyBase64Compressed
	 *
	 * @return string|null
	 */
	public static function compress( $data, bool $isAlreadyBase64Compressed = true ) {
		if ( $data === null ) {
			return null;
		}

		if ( self::isCompressed( $data ) ) {
			return $data;
		}

		if ( ! ZlibAvailabilityChecker::isGzcompressAvailable() || $data === '' ) {
			return $isAlreadyBase64Compressed ? $data : base64_encode( $data );
		}

		$decoded = $isAlreadyBase64Compressed ? base64_decode( $data ) : $data;
		if ( $decoded === false ) {
			return $data;
		}

		$compressed = gzcompress( $decoded );
		if ( $compressed === false ) {
			return $data;
		}

		return base64_encode( $compressed );
	}

	/**
	 * Compress data and track the job_id if compression succeeds.
	 *
	 * @param string|null $data The data to compress.
	 * @param bool $isAlreadyBase64Compressed Whether the data is already base64 encoded.
	 * @param int|null $job_id Job ID to track if compression succeeds.
	 *
	 * @return string|null The compressed data.
	 */
	public static function compressAndTrack( $data, bool $isAlreadyBase64Compressed = true, $job_id = null ) {
		$compressed = self::compress( $data, $isAlreadyBase64Compressed );

		// Only track if:
		// 1. We have a job_id
		// 2. Compression actually happened (data changed)
		// 3. gzcompress is available
		// 4. Data is not null or empty
		if (
			$job_id !== null
			&& ZlibAvailabilityChecker::isGzcompressAvailable()
			&& $compressed !== $data
			&& $data !== null
			&& $data !== ''
		) {
			CompressionTracker::recordCompression( $job_id );
		}

		return $compressed;
	}

	/**
	 * @param string|null $data
	 * @param bool $preserveBase64Encoding
	 *
	 * @return string|null
	 */
	public static function decompress( $data, bool $preserveBase64Encoding = false ) {
		if ( $data === null ) {
			return null;
		}

		if ( ! ZlibAvailabilityChecker::isGzuncompressAvailable() || $data === '' ) {
			return $preserveBase64Encoding ? $data : base64_decode( $data );
		}

		$decoded = base64_decode( $data );
		if ( $decoded === false ) {
			return $data;
		}

		$decompressed = @gzuncompress( $decoded );
		if ( $decompressed === false ) {
			return $preserveBase64Encoding ? $data : $decoded;
		}

		return $preserveBase64Encoding ? base64_encode( $decompressed ) : $decompressed;
	}
}