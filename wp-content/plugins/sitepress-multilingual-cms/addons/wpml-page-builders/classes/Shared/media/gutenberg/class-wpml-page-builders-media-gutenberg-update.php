<?php

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.LowLevelTranslationFunction
class WPML_Page_Builders_Media_Gutenberg_Update implements IWPML_PB_Media_Update {

	/** @var WPML_Translation_Element_Factory $element_factory */
	private $element_factory;

	/** @var WPML_Page_Builders_Media_Gutenberg $media_gutenberg */
	private $media_gutenberg;

	/** @var WPML_Page_Builders_Media_Usage|null $media_usage */
	private $media_usage;

	/**
	 * @param WPML_Translation_Element_Factory    $element_factory
	 * @param WPML_Page_Builders_Media_Gutenberg  $media_gutenberg
	 * @param WPML_Page_Builders_Media_Usage|null $media_usage
	 */
	public function __construct(
		WPML_Translation_Element_Factory $element_factory,
		WPML_Page_Builders_Media_Gutenberg $media_gutenberg,
		?WPML_Page_Builders_Media_Usage $media_usage = null
	) {
		$this->element_factory = $element_factory;
		$this->media_gutenberg = $media_gutenberg;
		$this->media_usage     = $media_usage;
	}

	/**
	 * @param WP_Post $post
	 */
	public function translate( $post ) {
		if ( ! has_blocks( $post->post_content ) ) {
			return;
		}

		$element = $this->element_factory->create_post( $post->ID );

		if ( ! $element->get_source_language_code() ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );

		$this->media_gutenberg
			->set_target_lang( $element->get_language_code() )
			->set_source_lang( $element->get_source_language_code() );

		$blocks = $this->translate_blocks_recursive( $blocks );

		if ( $this->media_usage ) {
			$this->media_usage->update( $element->get_source_element()->get_id() );
		}

		$post_content = serialize_blocks( $blocks );
		if ( $post->post_content !== $post_content ) {
			$post->post_content = $post_content;

			$tag_ids = wp_get_post_tags( $post->ID, [ 'fields' => 'ids' ] );
			$postarr = [
				'ID'           => $post->ID,
				'post_content' => $post->post_content,
				'tags_input'   => $tag_ids,
			];
			kses_remove_filters();
			wpml_update_escaped_post( $postarr, $element->get_language_code() );
			kses_init();
		}
	}

	/**
	 * @param array $blocks
	 *
	 * @return array
	 */
	private function translate_blocks_recursive( $blocks ) {
		foreach ( $blocks as &$block ) {
			$block = $this->media_gutenberg->translate( $block );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->translate_blocks_recursive( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * @param WP_Post $post
	 */
	public function find_media( $post ) {
		if ( ! has_blocks( $post->post_content ) ) {
			return;
		}

		$element = $this->element_factory->create_post( $post->ID );

		$blocks = parse_blocks( $post->post_content );

		$this->media_gutenberg
			->set_target_lang( $element->get_language_code() )
			->set_source_lang( $element->get_language_code() );

		$this->find_media_recursive( $blocks );
	}

	/**
	 * @param array $blocks
	 */
	private function find_media_recursive( $blocks ) {
		foreach ( $blocks as $block ) {
			$this->media_gutenberg->translate( $block );

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$this->find_media_recursive( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * @return array
	 */
	public function get_media() {
		return $this->media_gutenberg->get_media();
	}
}
