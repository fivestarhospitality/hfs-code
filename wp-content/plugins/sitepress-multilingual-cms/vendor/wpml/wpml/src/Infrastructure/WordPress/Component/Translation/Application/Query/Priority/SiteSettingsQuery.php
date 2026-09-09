<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\SiteSettingsQueryInterface;

/**
 * WordPress implementation of SiteSettingsQueryInterface.
 */
class SiteSettingsQuery implements SiteSettingsQueryInterface {


    /**
     * @return int|null
     */
  public function getHomePageId() {
      $showOnFront = get_option( 'show_on_front' );
    if ( $showOnFront !== 'page' ) {
        return null;
    }

      /** @var string|int $pageOnFrontOption */
      $pageOnFrontOption = get_option( 'page_on_front' );
      $pageOnFront       = (int) $pageOnFrontOption;
    if ( $pageOnFront === 0 ) {
        return null;
    }

      return $pageOnFront;
  }


}
