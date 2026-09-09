<?php

namespace WPML\Compatibility\Divi\V5\WooCommerce;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

/**
 * Clears Divi v5 product description transient cache when WPML completes a product translation.
 *
 * Divi v5 caches WooCommerce product descriptions (both long and short) in transients for 1 hour.
 * This class ensures the cache is cleared when a product is translated so Divi regenerates
 * the cached content with the translated version.
 *
 * @see \ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductDescription\WooCommerceProductDescriptionModule::get_description()
 */
class ProductDescriptionCache implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const CACHE_KEY_PREFIX = 'divi_wc_product_desc_';

	public function add_hooks() {
		Hooks::onAction( 'wpml_pro_translation_completed' )
			->then( spreadArgs( [ $this, 'clearProductDescriptionCache' ] ) );
	}

	/**
	 * @param int $newPostId
	 */
	public function clearProductDescriptionCache( $newPostId ) {
		if ( 'product' !== get_post_type( $newPostId ) ) {
			return;
		}

		$this->deleteTransient( $newPostId, 'description' );
		$this->deleteTransient( $newPostId, 'short_description' );
	}

	/**
	 * @param int    $productId
	 * @param string $descriptionType
	 */
	private function deleteTransient( $productId, $descriptionType ) {
		$cacheKey = self::CACHE_KEY_PREFIX . md5( $productId . '_' . $descriptionType );
		delete_transient( $cacheKey );
	}
}
