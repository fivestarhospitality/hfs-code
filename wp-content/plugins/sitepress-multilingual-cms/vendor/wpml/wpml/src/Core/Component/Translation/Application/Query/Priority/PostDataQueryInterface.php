<?php

namespace WPML\Core\Component\Translation\Application\Query\Priority;

/**
 * Interface for querying post data needed for priority sorting.
 */
interface PostDataQueryInterface {


    /**
     * Get post data for the given post IDs.
     *
     * @param int[] $postIds
     *
     * @return array<array{
     *   ID: int,
     *   post_type: string,
     *   post_parent: int,
     *   menu_order: int,
     *   post_title: string,
     *   post_modified_gmt: string,
     *   is_featured?: bool,
     *   stock_status?: string|null
     * }>
     */
  public function getPostsData( array $postIds ): array;


    /**
     * Get parent map for the given post IDs.
     *
     * @param int[] $postIds
     *
     * @return array<int, int> Map of post_id => post_parent
     */
  public function getParentMap( array $postIds ): array;


}
