<?php

namespace WPML\PB\Integrations\Divi;

class Helper {

	/**
	 * @return bool
	 */
	public static function isRunningDivi5() {
		$theme  = wp_get_theme();
		$parent = $theme->parent() ?: $theme;

		list( $version ) = explode( '-', trim( $parent->get( 'Version' ) ), 2 );

		return version_compare( $version, '5.0', '>=' );
	}

	/**
	 * @param int $postId
	 *
	 * @return bool
	 */
	public static function isPostUsingDivi5( $postId ) {
		return (bool) get_post_meta( $postId, '_et_pb_use_divi_5', true );
	}

	/**
	 * @return bool
	 */
	public static function isInDiviBuilder() {
		return function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled();
	}

	/**
	 * Check if we're in the Divi builder main window.
	 * When app_window is NOT set, we're in the main builder window.
	 * When app_window IS set, we're in the iframe preview.
	 *
	 * @return bool
	 */
	public static function isInDiviBuilderMainWindow() {
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended */
		return self::isInDiviBuilder() && empty( $_GET['app_window'] );
	}
}
