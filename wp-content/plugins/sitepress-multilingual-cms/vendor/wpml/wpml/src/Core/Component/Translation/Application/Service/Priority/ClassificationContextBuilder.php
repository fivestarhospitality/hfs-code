<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\SiteSettingsQueryInterface;
use WPML\Core\Component\Translation\Domain\Priority\Classifier\ClassificationContext;

/**
 * Builds ClassificationContext from WordPress data.
 */
class ClassificationContextBuilder {

    /**
     * Default string domain priorities.
     * Lower number = higher priority.
     */
    const DEFAULT_STRING_DOMAIN_PRIORITIES = [
        'admin_texts_theme_mods_*' => 1,
        'admin_texts_widget_*'     => 2,
        'WordPress'                => 3,
        'default'                  => 4,
    ];

    /**
     * Default string context priorities for menus/header/footer.
     */
    const DEFAULT_STRING_CONTEXT_PRIORITIES = [
        'menu'   => 1,
        'header' => 2,
        'footer' => 3,
        'nav'    => 4,
    ];

    /** @var array<string, int> */
    private $stringDomainPriorities;

    /** @var array<string, int> */
    private $stringContextPriorities;

    /** @var array<string, int> */
    private $cptPriorities;

    /** @var SiteSettingsQueryInterface|null */
    private $siteSettingsQuery;


    /**
     * @param SiteSettingsQueryInterface|null $siteSettingsQuery
     * @param array<string, int>              $stringDomainPriorities
     * @param array<string, int>              $stringContextPriorities
     * @param array<string, int>              $cptPriorities
     */
    public function __construct(
        ?SiteSettingsQueryInterface $siteSettingsQuery = null,
        array $stringDomainPriorities = [],
        array $stringContextPriorities = [],
        array $cptPriorities = []
    ) {
        $this->siteSettingsQuery       = $siteSettingsQuery;
        $this->stringDomainPriorities  = $stringDomainPriorities ?: self::DEFAULT_STRING_DOMAIN_PRIORITIES;
        $this->stringContextPriorities = $stringContextPriorities ?: self::DEFAULT_STRING_CONTEXT_PRIORITIES;
        $this->cptPriorities           = $cptPriorities;
    }


    /**
     * Build classification context.
     *
     * @param array<int, int> $parentMap Map of post_id => post_parent
     *
     * @return ClassificationContext
     */
    public function build( array $parentMap = [] ): ClassificationContext {
        $homePageId = $this->getHomePageId();

        $homepageSubtreeDepths = [];
      if ( $homePageId !== null ) {
          $homepageSubtreeDepths = $this->buildHomepageSubtreeDepths( $homePageId, $parentMap );
      }

        return new ClassificationContext(
          $homePageId,
          $parentMap,
          $homepageSubtreeDepths,
          [],
          $this->stringDomainPriorities,
          $this->stringContextPriorities,
          $this->cptPriorities
        );
    }


    /**
     * Get the homepage ID from site settings.
     *
     * @return int|null
     */
    private function getHomePageId() {
      if ( $this->siteSettingsQuery === null ) {
          return null;
      }

        return $this->siteSettingsQuery->getHomePageId();
    }


    /**
     * Build a map of post_id => depth from homepage for all descendants.
     *
     * @param int             $homePageId
     * @param array<int, int> $parentMap
     *
     * @return array<int, int>
     */
    private function buildHomepageSubtreeDepths( int $homePageId, array $parentMap ): array {
        $childrenMap = $this->buildChildrenMap( $parentMap );
        $depths      = [];

        $this->traverseSubtree( $homePageId, $childrenMap, 1, $depths );

        return $depths;
    }


    /**
     * Build a map of parent_id => [child_ids].
     *
     * @param array<int, int> $parentMap
     *
     * @return array<int, array<int>>
     */
    private function buildChildrenMap( array $parentMap ): array {
        $childrenMap = [];

      foreach ( $parentMap as $postId => $parentId ) {
        if ( ! isset( $childrenMap[ $parentId ] ) ) {
            $childrenMap[ $parentId ] = [];
        }
          $childrenMap[ $parentId ][] = $postId;
      }

        return $childrenMap;
    }


    /**
     * Recursively traverse the subtree and record depths.
     *
     * @param int                      $parentId
     * @param array<int, array<int>>   $childrenMap
     * @param int                      $currentDepth
     * @param array<int, int>          $depths
     *
     * @return void
     */
    private function traverseSubtree(
        int $parentId,
        array $childrenMap,
        int $currentDepth,
        array &$depths
    ) {
      if ( ! isset( $childrenMap[ $parentId ] ) ) {
          return;
      }

      foreach ( $childrenMap[ $parentId ] as $childId ) {
          $depths[ $childId ] = $currentDepth;
          $this->traverseSubtree( $childId, $childrenMap, $currentDepth + 1, $depths );
      }
    }


}
