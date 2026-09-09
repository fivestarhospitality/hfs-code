<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Domain\Priority\JobPriority;
use WPML\Core\Component\Translation\Domain\Priority\OrderingPayload;

/**
 * Builds the ordering payload for ATE API.
 */
class OrderingPayloadBuilder {


    /**
     * Build ordering payload from sorted priorities.
     *
     * @param JobPriority[] $sortedPriorities
     * @param int|null      $homePostId
     *
     * @return OrderingPayload
     */
  public function build( array $sortedPriorities, $homePostId = null ): OrderingPayload {
      $positions = [];
      $meta      = [];

    foreach ( $sortedPriorities as $priority ) {
        $jobId              = $priority->getJobId();
        $positions[ $jobId ] = $priority->getPosition();
        $meta[ $jobId ]      = $priority->toArray();
    }

      return new OrderingPayload(
        OrderingPayload::MODE_WPML_PRIORITY_V1,
        $homePostId,
        $positions,
        $meta
      );
  }


    /**
     * Build ordering payload and return as array for API.
     *
     * @param JobPriority[] $sortedPriorities
     * @param int|null      $homePostId
     *
     * @return array{mode: string, home_post_id: int|null, positions: array<string, int>, meta: array<string, array{tier: int, rank: array<int|string>}>}
     */
  public function buildArray( array $sortedPriorities, $homePostId = null ): array {
      return $this->build( $sortedPriorities, $homePostId )->toArray();
  }


}
