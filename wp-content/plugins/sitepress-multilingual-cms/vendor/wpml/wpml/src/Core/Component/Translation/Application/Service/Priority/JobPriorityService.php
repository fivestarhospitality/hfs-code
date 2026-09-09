<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\StringDataQueryInterface;
use WPML\Core\Component\Translation\Domain\Priority\OrderingPayload;

/**
 * Service that orchestrates job priority sorting for ATE API.
 */
class JobPriorityService {

    /** @var JobPrioritySorter */
    private $sorter;

    /** @var ClassificationContextBuilder */
    private $contextBuilder;

    /** @var PrioritizableItemBuilder */
    private $itemBuilder;

    /** @var OrderingPayloadBuilder */
    private $payloadBuilder;

    /** @var PostDataQueryInterface|null */
    private $postDataQuery;

    /** @var StringDataQueryInterface|null */
    private $stringDataQuery;


  public function __construct(
        JobPrioritySorter $sorter,
        ClassificationContextBuilder $contextBuilder,
        PrioritizableItemBuilder $itemBuilder,
        OrderingPayloadBuilder $payloadBuilder,
        ?PostDataQueryInterface $postDataQuery = null,
        ?StringDataQueryInterface $stringDataQuery = null
    ) {
      $this->sorter          = $sorter;
      $this->contextBuilder  = $contextBuilder;
      $this->itemBuilder     = $itemBuilder;
      $this->payloadBuilder  = $payloadBuilder;
      $this->postDataQuery   = $postDataQuery;
      $this->stringDataQuery = $stringDataQuery;
  }


    /**
     * Sort post IDs by priority and return sorted IDs.
     *
     * @param int[] $postIds
     *
     * @return int[]
     */
  public function sortPostIds( array $postIds ): array {
    if ( empty( $postIds ) ) {
        return [];
    }

      $items      = $this->buildPostItems( $postIds );
      $parentMap  = $this->buildParentMap( $postIds );
      $context    = $this->contextBuilder->build( $parentMap );

      return $this->sorter->sortIds( $items, $context );
  }


    /**
     * Sort string IDs by priority and return sorted IDs.
     *
     * @param int[] $stringIds
     *
     * @return int[]
     */
  public function sortStringIds( array $stringIds ): array {
    if ( empty( $stringIds ) ) {
        return [];
    }

      $items   = $this->buildStringItems( $stringIds );
      $context = $this->contextBuilder->build();

      return $this->sorter->sortIds( $items, $context );
  }


    /**
     * Sort mixed items (posts, strings, packages) and return ordering payload.
     *
     * @param int[] $postIds
     * @param int[] $stringIds
     * @param int[] $packageIds
     *
     * @return OrderingPayload
     */
  public function buildOrderingPayload(
        array $postIds,
        array $stringIds = [],
        array $packageIds = []
    ): OrderingPayload {
    $items = [];

    $items = array_merge( $items, $this->buildPostItems( $postIds ) );
    $items = array_merge( $items, $this->buildStringItems( $stringIds ) );
    $items = array_merge( $items, $this->buildPackageItems( $packageIds ) );

    $parentMap = $this->buildParentMap( $postIds );
    $context   = $this->contextBuilder->build( $parentMap );

    $sortedPriorities = $this->sorter->sort( $items, $context );

    return $this->payloadBuilder->build( $sortedPriorities, $context->getHomePageId() );
  }


    /**
     * Sort mixed items and return ordering payload as array for API.
     *
     * @param int[] $postIds
     * @param int[] $stringIds
     * @param int[] $packageIds
     *
     * @return array{mode: string, home_post_id: int|null, positions: array<string, int>, meta: array<string, array{tier: int, rank: array<int|string>}>}
     */
  public function buildOrderingPayloadArray( array $postIds, array $stringIds = [], array $packageIds = [] ): array {
      return $this->buildOrderingPayload( $postIds, $stringIds, $packageIds )->toArray();
  }


    /**
     * Get sorted IDs for all item types combined.
     *
     * @param int[] $postIds
     * @param int[] $stringIds
     * @param int[] $packageIds
     *
     * @return int[]
     */
  public function getSortedIds( array $postIds, array $stringIds = [], array $packageIds = [] ): array {
      $items = [];

      $items = array_merge( $items, $this->buildPostItems( $postIds ) );
      $items = array_merge( $items, $this->buildStringItems( $stringIds ) );
      $items = array_merge( $items, $this->buildPackageItems( $packageIds ) );

      $parentMap = $this->buildParentMap( $postIds );
      $context   = $this->contextBuilder->build( $parentMap );

      return $this->sorter->sortIds( $items, $context );
  }


    /**
     * Build PrioritizableItem objects from post IDs.
     *
     * @param int[] $postIds
     *
     * @return \WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem[]
     */
  private function buildPostItems( array $postIds ): array {
    if ( empty( $postIds ) || $this->postDataQuery === null ) {
        return [];
    }

      $items    = [];
      $postData = $this->postDataQuery->getPostsData( $postIds );

    foreach ( $postData as $post ) {
        $items[] = $this->itemBuilder->buildFromPost(
          $post,
          $post['is_featured'] ?? false,
          $post['stock_status'] ?? null
        );
    }

      return $items;
  }


    /**
     * Build PrioritizableItem objects from string IDs.
     *
     * @param int[] $stringIds
     *
     * @return \WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem[]
     */
  private function buildStringItems( array $stringIds ): array {
    if ( empty( $stringIds ) ) {
        return [];
    }

      $items = [];

    if ( $this->stringDataQuery !== null ) {
        $stringData = $this->stringDataQuery->getStringsData( $stringIds );
      foreach ( $stringData as $string ) {
          $items[] = $this->itemBuilder->buildFromString(
            $string['id'],
            $string['domain'] ?? null,
            $string['context'] ?? null
          );
      }
    } else {
      foreach ( $stringIds as $stringId ) {
          $items[] = $this->itemBuilder->buildFromString( $stringId );
      }
    }

      return $items;
  }


    /**
     * Build PrioritizableItem objects from package IDs.
     *
     * @param int[] $packageIds
     *
     * @return \WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem[]
     */
  private function buildPackageItems( array $packageIds ): array {
      $items = [];

    foreach ( $packageIds as $packageId ) {
        $items[] = $this->itemBuilder->buildFromPackage( $packageId );
    }

      return $items;
  }


    /**
     * Build parent map from post IDs.
     *
     * @param int[] $postIds
     *
     * @return array<int, int>
     */
  private function buildParentMap( array $postIds ): array {
    if ( empty( $postIds ) || $this->postDataQuery === null ) {
        return [];
    }

      return $this->postDataQuery->getParentMap( $postIds );
  }


}
