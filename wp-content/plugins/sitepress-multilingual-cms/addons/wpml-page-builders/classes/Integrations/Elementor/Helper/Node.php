<?php

namespace WPML\PB\Elementor\Helper;

class Node {

	/**
	 * @param array $element
	 *
	 * @return bool
	 */
	public static function isTranslatable( $element ) {
		if ( ! isset( $element['elType'] ) ) {
			return false;
		}

		$elType = $element['elType'];

		return in_array( $elType, [ 'widget', 'container' ], true ) || strpos( $elType, 'e-' ) === 0;
	}

	/**
	 * @param array $element
	 *
	 * @return bool
	 */
	public static function hasChildren( $element ) {
		return isset( $element['elements'] ) && count( $element['elements'] );
	}
}
