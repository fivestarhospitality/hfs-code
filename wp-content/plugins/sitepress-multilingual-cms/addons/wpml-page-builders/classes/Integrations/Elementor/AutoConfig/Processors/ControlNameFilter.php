<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

class ControlNameFilter {

	/**
	 * @return array
	 */
	private static function getExcludeNeedles() {
		return [ '_css', '_js', '_id' ];
	}

	/**
	 * @param string $controlName
	 *
	 * @return bool
	 */
	public static function shouldExclude( $controlName ) {
		$needles = self::getExcludeNeedles();

		foreach ( $needles as $needle ) {
			if ( strpos( $controlName, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
