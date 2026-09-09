<?php

namespace WPML\Core\Component\Translation\Domain\Priority\Classifier;

use WPML\Core\Component\Translation\Domain\Priority\JobPriority;
use WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem;

/**
 * Interface for tier classifiers that assign priority to items.
 */
interface TierClassifierInterface {


    /**
     * Check if this classifier can handle the given item.
     *
     * @param PrioritizableItem $item
     * @param ClassificationContext $context
     *
     * @return bool
     */
  public function canClassify( PrioritizableItem $item, ClassificationContext $context ): bool;


    /**
     * Classify the item and return its priority.
     *
     * @param PrioritizableItem $item
     * @param ClassificationContext $context
     *
     * @return JobPriority
     */
  public function classify( PrioritizableItem $item, ClassificationContext $context ): JobPriority;


}
