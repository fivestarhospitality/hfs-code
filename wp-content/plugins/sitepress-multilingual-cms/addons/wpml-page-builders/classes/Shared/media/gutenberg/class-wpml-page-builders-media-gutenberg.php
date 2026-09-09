<?php

class WPML_Page_Builders_Media_Gutenberg {

	const TYPE_URL = 'media-url';
	const TYPE_IDS = 'media-ids';

	/** @var IWPML_PB_Media_Find_And_Translate $media_translate */
	private $media_translate;

	/** @var string $target_lang */
	private $target_lang;

	/** @var string $source_lang */
	private $source_lang;

	/** @var array $config */
	private $config;

	/** @var string $block_name */
	private $block_name;

	/**
	 * @param IWPML_PB_Media_Find_And_Translate $media_translate
	 * @param array                             $config
	 */
	public function __construct( IWPML_PB_Media_Find_And_Translate $media_translate, array $config ) {
		$this->media_translate = $media_translate;
		$this->config          = $config;
	}

	/**
	 * @param array $block
	 *
	 * @return array
	 */
	public function translate( array $block ) {
		if ( ! isset( $block['blockName'], $block['attrs'] ) ) {
			return $block;
		}

		$this->block_name = $block['blockName'];
		$block_config     = $this->get_block_config( $block['blockName'] );

		if ( $block_config && isset( $block_config['key'] ) ) {
			$block['attrs'] = $this->translate_attributes( $block['attrs'], $block_config['key'] );
		}

		return $block;
	}

	/**
	 * @param string $block_name
	 *
	 * @return array
	 */
	private function get_block_config( $block_name ) {
		list( $namespace ) = explode( '/', $block_name, 2 );

		return array_merge(
			isset( $this->config[ $namespace ] ) ? $this->config[ $namespace ] : [],
			isset( $this->config[ $block_name ] ) ? $this->config[ $block_name ] : []
		);
	}

	/**
	 * @param array $attrs
	 * @param array $keys_config
	 *
	 * @return array
	 */
	private function translate_attributes( array $attrs, array $keys_config ) {
		foreach ( $keys_config as $path => $type ) {
			if ( self::TYPE_URL === $type || self::TYPE_IDS === $type ) {
				$attrs = $this->translate_by_path( $attrs, explode( '>', $path ), $type );
			}
		}

		return $attrs;
	}

	/**
	 * @param array|string $attrs
	 * @param array        $path
	 * @param string       $type
	 *
	 * @return mixed
	 */
	private function translate_by_path( $attrs, $path, $type ) {
		$current_key = reset( $path );
		$next_path   = array_slice( $path, 1 );

		if ( $current_key && isset( $attrs[ $current_key ] ) ) {
			if ( $next_path ) {
				$attrs[ $current_key ] = $this->translate_by_path( $attrs[ $current_key ], $next_path, $type );
			} else {
				$attrs[ $current_key ] = $this->translate_value( $attrs[ $current_key ], $type );
			}
		}

		return $attrs;
	}

	/**
	 * @param mixed  $value
	 * @param string $type
	 *
	 * @return mixed
	 */
	private function translate_value( $value, $type ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		if ( self::TYPE_URL === $type ) {
			return $this->media_translate->translate_image_url( $value, $this->target_lang, $this->source_lang, $this->block_name );
		}

		if ( self::TYPE_IDS === $type ) {
			return $this->translate_ids( $value );
		}

		return $value;
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	private function translate_ids( $value ) {
		$ids = explode( ',', $value );

		foreach ( $ids as &$id ) {
			$id = $this->media_translate->translate_id( (int) $id, $this->target_lang );
		}

		return implode( ',', $ids );
	}

	/**
	 * @param string $target_lang
	 *
	 * @return self
	 */
	public function set_target_lang( $target_lang ) {
		$this->target_lang = $target_lang;

		return $this;
	}

	/**
	 * @param string $source_lang
	 *
	 * @return self
	 */
	public function set_source_lang( $source_lang ) {
		$this->source_lang = $source_lang;

		return $this;
	}

	/**
	 * @return array
	 */
	public function get_media() {
		return $this->media_translate->get_used_media_in_post();
	}
}
