<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

/**
 * Interface for providing custom classifiers to the priority sorter.
 */
interface ClassifierProviderInterface {


  /**
   * Get additional classifiers to be added to the default list.
   *
   * @return array<int, object>
   */
  public function getClassifiers(): array;


}
