<?php

namespace WPML\TM\ATE\API;

class ErrorHandler {

	/**
	 * Creates an error structure with both formatted message and raw response data
	 *
	 * @param array $message Formatted error message with 'header' and 'description' keys.
	 * @param mixed $rawResponse Raw response data (WP_Error, array, or other response data).
	 * @return array Error structure containing both formatted message and raw data
	 */
	public static function createError( $message, $rawResponse = null ) {

		if ( null !== $rawResponse ) {
			$message['raw_response'] = self::normalizeRawResponse( $rawResponse );
		}

		return $message;
	}

	/**
	 * Normalizes raw response data into a consistent structure
	 *
	 * @param mixed $rawResponse Raw response data.
	 * @return array Normalized response structure
	 */
	private static function normalizeRawResponse( $rawResponse ) {
		if ( is_wp_error( $rawResponse ) ) {
			return self::normalizeWpError( $rawResponse );
		}

		if ( is_array( $rawResponse ) ) {
			return self::normalizeHttpResponse( $rawResponse );
		}

		return [
			'type' => 'unknown',
			'data' => $rawResponse,
		];
	}

	/**
	 * Normalizes WP_Error into structured format
	 *
	 * @param \WP_Error $error WordPress error object.
	 * @return array Normalized error structure
	 */
	private static function normalizeWpError( $error ) {
		return [
			'type'          => 'wp_error',
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message(),
			'error_data'    => $error->get_error_data(),
		];
	}

	/**
	 * Normalizes HTTP response array into structured format
	 *
	 * @param array $response HTTP response array from wp_remote_request.
	 * @return array Normalized response structure
	 */
	private static function normalizeHttpResponse( $response ) {
		$normalized = [
			'type' => 'http_response',
		];

		if ( isset( $response['response'] ) ) {
			$normalized['status_code'] = isset( $response['response']['code'] ) ? $response['response']['code'] : null;
			$normalized['status_message'] = isset( $response['response']['message'] ) ? $response['response']['message'] : null;
		}

		if ( isset( $response['headers'] ) ) {
			$normalized['headers'] = is_object( $response['headers'] )
				? $response['headers']->getAll()
				: $response['headers'];
		}

		if ( isset( $response['body'] ) ) {
			$normalized['body'] = $response['body'];
		}

		return $normalized;
	}

}
