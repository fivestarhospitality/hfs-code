<?php

namespace WPML\Core\Component\Translation\Application\Query\Priority;

/**
 * Interface for querying site settings needed for priority sorting.
 */
interface SiteSettingsQueryInterface {


    /**
     * Get the homepage ID if a static page is set as the front page.
     *
     * @return int|null Homepage post ID or null if not set or using posts
     */
  public function getHomePageId();


}
