<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Domain\Priority\Classifier\ClassificationContext;
use WPML\Core\Component\Translation\Domain\Priority\Classifier\PostClassifier;
use WPML\Core\Component\Translation\Domain\Priority\Classifier\StringClassifier;
use WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface;
use WPML\Core\Component\Translation\Domain\Priority\JobPriority;
use WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem;
use WPML\Core\Component\Translation\Domain\Priority\Rank;
use WPML\Core\Component\Translation\Domain\Priority\Tier;

/**
 * Service that sorts translation jobs by priority tiers.
 */
class JobPrioritySorter {

    /** @var TierClassifierInterface[] */
    private $classifiers;

    /** @var ClassifiersFilterInterface|null */
    private $classifiersFilter;


  public function __construct( ?ClassifiersFilterInterface $classifiersFilter = null ) {
      $this->classifiersFilter = $classifiersFilter;
      $this->classifiers       = $this->buildClassifiers();
  }


    /**
     * Build classifiers list, allowing external plugins to modify it via filter.
     *
     * @return TierClassifierInterface[]
     */
  private function buildClassifiers(): array {
      $classifiers = $this->createDefaultClassifiers();

    if ( $this->classifiersFilter !== null ) {
        $classifiers = $this->classifiersFilter->filter( $classifiers );
    }

      return $classifiers;
  }


    /**
     * @return TierClassifierInterface[]
     */
  private function createDefaultClassifiers(): array {
      return [
          new StringClassifier(),
          new PostClassifier(),
      ];
  }


    /**
     * Sort items by priority and return JobPriority objects with positions.
     *
     * @param PrioritizableItem[]   $items
     * @param ClassificationContext $context
     *
     * @return JobPriority[]
     */
  public function sort( array $items, ClassificationContext $context ): array {
      $priorities = [];

    foreach ( $items as $item ) {
        $priorities[] = $this->classifyItem( $item, $context );
    }

      usort(
        $priorities,
        function ( JobPriority $a, JobPriority $b ): int {
          return $a->compareTo( $b );
        }
      );

      $result = [];
    foreach ( $priorities as $position => $priority ) {
        $result[] = $priority->withPosition( $position );
    }

      return $result;
  }


    /**
     * Sort item IDs by priority and return sorted IDs.
     *
     * @param PrioritizableItem[]   $items
     * @param ClassificationContext $context
     *
     * @return int[]
     */
  public function sortIds( array $items, ClassificationContext $context ): array {
      $sortedPriorities = $this->sort( $items, $context );

      return array_map(
        function ( JobPriority $priority ): int {
            return $priority->getJobId();
        },
        $sortedPriorities
      );
  }


    /**
     * Classify a single item using the first matching classifier.
     *
     * @param PrioritizableItem     $item
     * @param ClassificationContext $context
     *
     * @return JobPriority
     */
  private function classifyItem( PrioritizableItem $item, ClassificationContext $context ): JobPriority {
    foreach ( $this->classifiers as $classifier ) {
      if ( $classifier->canClassify( $item, $context ) ) {
        return $classifier->classify( $item, $context );
      }
    }

      return $this->createFallbackPriority( $item );
  }


    /**
     * Create a fallback priority for items that don't match any classifier.
     *
     * @param PrioritizableItem $item
     *
     * @return JobPriority
     */
  private function createFallbackPriority( PrioritizableItem $item ): JobPriority {
      return new JobPriority(
        $item->getId(),
        new Tier( PHP_INT_MAX ),
        new Rank( [ $item->getId() ] )
      );
  }


}
