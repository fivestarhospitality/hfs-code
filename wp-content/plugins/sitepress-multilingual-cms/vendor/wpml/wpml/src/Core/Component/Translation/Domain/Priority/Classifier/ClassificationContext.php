<?php

namespace WPML\Core\Component\Translation\Domain\Priority\Classifier;

/**
 * Context object containing data needed for classification.
 */
class ClassificationContext {

    /** @var int|null */
    private $homePageId;

    /** @var array<int, int> */
    private $parentMap;

    /** @var array<int, int> */
    private $homepageSubtreeDepths;

    /** @var array<int> */
    private $alreadyClassifiedIds;

    /** @var array<string, int> */
    private $stringDomainPriorities;

    /** @var array<string, int> */
    private $stringContextPriorities;

    /** @var array<string, int> */
    private $cptPriorities;


    /**
     * @param int|null           $homePageId
     * @param array<int, int>    $parentMap
     * @param array<int, int>    $homepageSubtreeDepths
     * @param array<int>         $alreadyClassifiedIds
     * @param array<string, int> $stringDomainPriorities
     * @param array<string, int> $stringContextPriorities
     * @param array<string, int> $cptPriorities
     */
  public function __construct(
        $homePageId = null,
        array $parentMap = [],
        array $homepageSubtreeDepths = [],
        array $alreadyClassifiedIds = [],
        array $stringDomainPriorities = [],
        array $stringContextPriorities = [],
        array $cptPriorities = []
    ) {
      $this->homePageId              = $homePageId;
      $this->parentMap               = $parentMap;
      $this->homepageSubtreeDepths   = $homepageSubtreeDepths;
      $this->alreadyClassifiedIds    = $alreadyClassifiedIds;
      $this->stringDomainPriorities  = $stringDomainPriorities;
      $this->stringContextPriorities = $stringContextPriorities;
      $this->cptPriorities           = $cptPriorities;
  }


    /**
     * @return int|null
     */
  public function getHomePageId() {
      return $this->homePageId;
  }


    /**
     * @return array<int, int>
     */
  public function getParentMap(): array {
      return $this->parentMap;
  }


    /**
     * @return array<int, int>
     */
  public function getHomepageSubtreeDepths(): array {
      return $this->homepageSubtreeDepths;
  }


    /**
     * @return array<int>
     */
  public function getAlreadyClassifiedIds(): array {
      return $this->alreadyClassifiedIds;
  }


    /**
     * @return array<string, int>
     */
  public function getStringDomainPriorities(): array {
      return $this->stringDomainPriorities;
  }


    /**
     * @return array<string, int>
     */
  public function getStringContextPriorities(): array {
      return $this->stringContextPriorities;
  }


    /**
     * @return array<string, int>
     */
  public function getCptPriorities(): array {
      return $this->cptPriorities;
  }


  public function isHomePage( int $postId ): bool {
      return $this->homePageId !== null && $this->homePageId === $postId;
  }


  public function isInHomepageSubtree( int $postId ): bool {
      return isset( $this->homepageSubtreeDepths[ $postId ] );
  }


    /**
     * @return int|null
     */
  public function getHomepageSubtreeDepth( int $postId ) {
      return $this->homepageSubtreeDepths[ $postId ] ?? null;
  }


  public function isAlreadyClassified( int $itemId ): bool {
      return in_array( $itemId, $this->alreadyClassifiedIds, true );
  }


  public function withClassifiedId( int $itemId ): self {
      $newClassifiedIds   = $this->alreadyClassifiedIds;
      $newClassifiedIds[] = $itemId;

      return new self(
        $this->homePageId,
        $this->parentMap,
        $this->homepageSubtreeDepths,
        $newClassifiedIds,
        $this->stringDomainPriorities,
        $this->stringContextPriorities,
        $this->cptPriorities
      );
  }


  public function getStringDomainPriority( string $domain ): int {
      return $this->stringDomainPriorities[ $domain ] ?? PHP_INT_MAX;
  }


  public function getStringContextPriority( string $context ): int {
      return $this->stringContextPriorities[ $context ] ?? PHP_INT_MAX;
  }


  public function getCptPriority( string $postType ): int {
      return $this->cptPriorities[ $postType ] ?? PHP_INT_MAX;
  }


    /**
     * Calculate hierarchy depth from root (post_parent = 0).
     *
     * @param int $postId
     *
     * @return int
     */
  public function getHierarchyDepthFromRoot( int $postId ): int {
      $depth     = 0;
      $currentId = $postId;
      $visited   = [];

    while ( isset( $this->parentMap[ $currentId ] ) && $this->parentMap[ $currentId ] !== 0 ) {
      if ( isset( $visited[ $currentId ] ) ) {
        break;
      }
        $visited[ $currentId ] = true;
        $currentId             = $this->parentMap[ $currentId ];
        $depth++;
    }

      return $depth;
  }


}
