<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Application\Service\Priority\ClassifiersFilterInterface;

/**
 * WordPress implementation of ClassifiersFilterInterface using apply_filters.
 */
class ClassifiersFilter implements ClassifiersFilterInterface {

    const FILTER_CLASSIFIERS = 'wpml_translation_priority_classifiers';


    /**
     * @param \WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface[] $classifiers
     *
     * @return \WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface[]
     */
  public function filter( array $classifiers ): array {
      /** @var \WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface[] $filtered */
      $filtered = apply_filters( self::FILTER_CLASSIFIERS, $classifiers );

      return $filtered;
  }


}
