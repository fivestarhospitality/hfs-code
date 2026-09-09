<?php

namespace WPML\PB\Gutenberg\ConvertIdsInBlock;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks as WpHooks;
use WPML\PB\Gutenberg\Integration;
use function WPML\FP\spreadArgs;

class Hooks implements Integration {

	/** @var \WPML_Gutenberg_Config_Option $config */
	private $config;

	public function __construct( \WPML_Gutenberg_Config_Option $config ) {
		$this->config = $config;
	}

	public function add_hooks() {
		if ( $this->config->get_ids_in_blocks() ) {
			WpHooks::onFilter( 'render_block_data', - PHP_INT_MAX )
				->then( spreadArgs( [ $this, 'filterIdsInBlock' ] ) );
		}
	}

	/**
	 * @param array $block
	 *
	 * @return array
	 */
	public function filterIdsInBlock( array $block ) {
		$blockConfig = $this->getBlockConfig( $block );

		if ( $blockConfig ) {
			return (
				new Composite(
					array_merge(
						$this->getBlockAttributesConverter( $blockConfig ),
						$this->getTagAttributesConverter( $blockConfig )
					)
				)
			)->convert( $block );
		}

		return $block;
	}

	/**
	 * @param array $block
	 *
	 * @return array
	 */
	private function getBlockConfig( $block ) {
		$blockName = Obj::prop( 'blockName', $block );
		$config    = $this->config->get_ids_in_blocks();
		if ( ! $blockName || ! $config ) {
			return [];
		}

		list( $namespace ) = explode( '/', $blockName, 2 );

		return array_merge(
			Obj::propOr( [], $namespace, $config ),
			Obj::propOr( [], $blockName, $config )
		);
	}

	/**
	 * @param array $blockConfig
	 *
	 * @return BlockAttributes[]
	 */
	private function getBlockAttributesConverter( $blockConfig ) {
		$keyConfig = wpml_collect( (array) Obj::prop( 'key', $blockConfig ) )
			->map(
				function ( $slug, $path ) {
					return [
						'path' => $path,
						'slug' => $slug,
					];
				}
			)->toArray();

		return $keyConfig ? [ new BlockAttributes( $keyConfig ) ] : [];
	}

	/**
	 * @param array $blockConfig
	 *
	 * @return TagAttributes[]
	 */
	private function getTagAttributesConverter( $blockConfig ) {
		$xpathConfig = wpml_collect( (array) Obj::prop( 'xpath', $blockConfig ) )
			->map(
				function ( $slug, $xpath ) {
					return [
						'xpath' => $xpath,
						'slug'  => $slug,
					];
				}
			)->toArray();

		return $xpathConfig ? [ new TagAttributes( $xpathConfig ) ] : [];
	}
}
