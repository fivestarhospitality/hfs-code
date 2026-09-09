<?php

namespace WPML\Compatibility\FusionBuilder;

use WPML\Compatibility\BaseDynamicContent;

class DynamicContent extends BaseDynamicContent {

	/** @var array */
	protected $positions = [ 'before', 'after', 'fallback', 'singular_text', 'plural_text' ];

	/**
	 * Sets $positions dynamic content to be translatable.
	 *
	 * @param string|array $string   The decoded string so far.
	 * @param string       $encoding The encoding used.
	 *
	 * @return string|array
	 */
	public function decode_dynamic_content( $string, $encoding ) {
		if ( ! $string || is_array( $string ) ) {
			return $string;
		}

		if ( $this->is_dynamic_content( $string ) ) {
			$decodedData = $this->decode_field( $string );

			$decodedContent = [
				'dynamic-content' => [
					'value'     => $string,
					'translate' => false,
				],
			];

			foreach ( $decodedData['content_keys'] as $contentKey ) {
				foreach ( $this->positions as $position ) {
					if ( ! empty( $decodedData['data'][ $contentKey ][ $position ] ) ) {
						$field_key                    = $contentKey . '-' . $position;
						$decodedContent[ $field_key ] = [
							'value'     => $decodedData['data'][ $contentKey ][ $position ],
							'translate' => true,
						];
					}
				}
			}

			return $decodedContent;
		}

		return $string;
	}

	/**
	 * Rebuilds dynamic content with translated strings.
	 *
	 * @param string|array $string   The field array or string.
	 * @param string       $encoding The encoding used.
	 *
	 * @return string
	 */
	public function encode_dynamic_content( $string, $encoding ) {
		if ( is_array( $string ) && isset( $string['dynamic-content'] ) ) {
			$decodedData = $this->decode_field( $string['dynamic-content'] );

			foreach ( $decodedData['content_keys'] as $contentKey ) {
				foreach ( $this->positions as $position ) {
					$field_key = $contentKey . '-' . $position;
					if ( isset( $string[ $field_key ] ) ) {
						$decodedData['data'][ $contentKey ][ $position ] = $string[ $field_key ];
					}
				}
			}

			return $this->encode_field( $decodedData );
		}

		return $string;
	}

	/**
	 * Check if a certain field contains dynamic content.
	 *
	 * @param string $string The string to check.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content( $string ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = json_decode( base64_decode( $string ), true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		return isset( $decoded['element_content'] ) || isset( $decoded['alt'] ) || isset( $decoded['link'] );
	}

	/**
	 * Decode a dynamic-content field.
	 *
	 * @param string $string The string to decode.
	 *
	 * @return array
	 */
	protected function decode_field( $string ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = json_decode( base64_decode( $string ), true );

		$content_keys = [];
		if ( isset( $decoded['element_content'] ) ) {
			$content_keys[] = 'element_content';
		}
		if ( isset( $decoded['alt'] ) ) {
			$content_keys[] = 'alt';
		}
		if ( isset( $decoded['link'] ) ) {
			$content_keys[] = 'link';
		}

		return [
			'data'         => $decoded,
			'content_keys' => $content_keys,
		];
	}

	/**
	 * Encode a dynamic-content field.
	 *
	 * @param array $decodedData The decoded data to encode.
	 *
	 * @return string
	 */
	protected function encode_field( $decodedData ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( wp_json_encode( $decodedData['data'], JSON_UNESCAPED_SLASHES ) );
	}
}
