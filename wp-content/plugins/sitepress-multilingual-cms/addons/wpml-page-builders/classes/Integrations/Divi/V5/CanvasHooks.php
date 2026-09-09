<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\FP\Lst;
use SitePress;

class CanvasHooks implements \IWPML_DIC_Action, \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const META_KEY  = '_divi_canvas_parent_post_id';
	const POST_TYPE = 'et_pb_canvas';

	/** @var SitePress */
	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'pre_get_posts', [ $this, 'handleCanvasQuery' ] );
		add_action( 'wpml_pro_translation_completed', [ $this, 'clearCachedCanvasesInParents' ] );
	}

	/**
	 * @param \WP_Query $query
	 */
	public function handleCanvasQuery( $query ) {
		if ( isset( $query->query_vars['post_type'] ) && self::POST_TYPE === $query->query_vars['post_type'] ) {
			$query->query_vars['suppress_filters'] = false;

			if ( ! empty( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
				$query->query_vars['meta_query'] = $this->translateClauses( $query->query_vars['meta_query'] );
			}
		}
	}

	/**
	 * @param array $clauses
	 *
	 * @return array
	 */
	private function translateClauses( $clauses ) {
		foreach ( $clauses as $index => $clause ) {
			if ( 'relation' !== $index && isset( $clause['key'] ) ) {
				$clauses[ $index ] = $this->maybeExpandClause( $clause );
			}
		}

		return $clauses;
	}

	/**
	 * @param array $clause
	 *
	 * @return array
	 */
	private function maybeExpandClause( $clause ) {
		if ( self::META_KEY !== $clause['key'] ) {
			return $clause;
		}

		$compare = strtoupper( $clause['compare'] ?? '=' );
		if ( ! in_array( $compare, [ '=', 'IN' ], true ) ) {
			return $clause;
		}

		$allIds = [];

		foreach ( (array) $clause['value'] as $id ) {
			$allIds = array_merge( $allIds, $this->getAllTranslationIds( (int) $id ) );
		}

		$clause['value']   = array_values( array_unique( $allIds ) );
		$clause['compare'] = 'IN';

		return $clause;
	}

	/**
	 * @param int $id
	 *
	 * @return array
	 */
	private function getAllTranslationIds( $id ) {
		if ( ! $id ) {
			return [ $id ];
		}

		$postType = get_post_type( $id );

		if ( ! $postType ) {
			return [ $id ];
		}

		$elementType = 'post_' . $postType;

		$trid = $this->sitepress->get_element_trid( $id, $elementType );

		if ( ! $trid ) {
			return [ $id ];
		}

		$translations = $this->sitepress->get_element_translations( $trid, $elementType );

		if ( empty( $translations ) ) {
			return [ $id ];
		}

		return Lst::pluck( 'element_id', array_values( $translations ) );
	}

	/**
	 * @param int $postId
	 */
	public function clearCachedCanvasesInParents( $postId ) {
		if ( self::POST_TYPE === get_post_type( $postId ) ) {
			$parent = get_post_meta( $postId, self::META_KEY, true );

			// Global Canvases have no parent.
			// They could be cached in any post, so we clear all caches.
			if ( ! $parent ) {
				$parent = 'all';
			}

			/* @phpstan-ignore-next-line */
			if ( class_exists( '\ET_Core_PageResource' ) && method_exists( '\ET_Core_PageResource', 'clear_post_meta_caches' ) ) {
				\ET_Core_PageResource::clear_post_meta_caches( $parent );
			}
		}
	}
}
