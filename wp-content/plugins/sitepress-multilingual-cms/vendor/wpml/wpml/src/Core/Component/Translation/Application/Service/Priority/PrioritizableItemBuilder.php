<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Domain\Priority\ItemType;
use WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem;

/**
 * Builds PrioritizableItem objects from various data sources.
 */
class PrioritizableItemBuilder {


    /**
     * Build a PrioritizableItem from a post array.
     *
     * @param array{
     *   ID: int,
     *   post_type: string,
     *   post_parent: int,
     *   menu_order: int,
     *   post_title: string,
     *   post_modified_gmt: string
     * } $post
     * @param bool        $isFeatured
     * @param string|null $stockStatus
     *
     * @return PrioritizableItem
     */
  public function buildFromPost( array $post, bool $isFeatured = false, $stockStatus = null ): PrioritizableItem {
      $modifiedGmtTimestamp = strtotime( $post['post_modified_gmt'] );
    if ( $modifiedGmtTimestamp === false ) {
        $modifiedGmtTimestamp = 0;
    }

      return new PrioritizableItem(
        $post['ID'],
        ItemType::post(),
        $post['post_type'],
        $post['post_parent'],
        $post['menu_order'],
        $post['post_title'],
        $modifiedGmtTimestamp,
        null,
        null,
        $isFeatured,
        $stockStatus
      );
  }


    /**
     * Build a PrioritizableItem from a string.
     *
     * @param int         $stringId
     * @param string|null $domain
     * @param string|null $context
     *
     * @return PrioritizableItem
     */
  public function buildFromString( int $stringId, $domain = null, $context = null ): PrioritizableItem {
      return new PrioritizableItem(
        $stringId,
        ItemType::string(),
        null,
        0,
        0,
        '',
        0,
        $domain,
        $context,
        false,
        null
      );
  }


    /**
     * Build a PrioritizableItem from a package.
     *
     * @param int         $packageId
     * @param string|null $domain
     * @param string|null $context
     *
     * @return PrioritizableItem
     */
  public function buildFromPackage( int $packageId, $domain = null, $context = null ): PrioritizableItem {
      return new PrioritizableItem(
        $packageId,
        ItemType::package(),
        null,
        0,
        0,
        '',
        0,
        $domain,
        $context,
        false,
        null
      );
  }


}
