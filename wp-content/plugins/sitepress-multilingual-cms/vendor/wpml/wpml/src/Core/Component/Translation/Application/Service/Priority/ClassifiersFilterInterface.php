<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

/**
 * Interface for filtering/extending tier classifiers.
 */
interface ClassifiersFilterInterface {


  /**
   * Filter the list of classifiers, allowing external plugins to modify it.
   *
   * @param \WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface[] $classifiers
   *
   * @return \WPML\Core\Component\Translation\Domain\Priority\Classifier\TierClassifierInterface[]
   */
  public function filter( array $classifiers ): array;


}
