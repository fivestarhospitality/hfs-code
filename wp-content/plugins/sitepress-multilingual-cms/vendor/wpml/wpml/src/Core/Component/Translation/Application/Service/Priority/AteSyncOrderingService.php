<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Domain\Priority\OrderingPayload;

/**
 * Service that provides ordering data for ATE sync API calls.
 * This service can be used to add ordering information to the sync payload.
 */
class AteSyncOrderingService {

    /** @var JobPriorityService */
    private $priorityService;


  public function __construct( JobPriorityService $priorityService ) {
      $this->priorityService = $priorityService;
  }


    /**
     * Build sync payload with ordering information.
     *
     * @param int[]    $ateJobIds    ATE job IDs to sync
     * @param int[]    $postIds      Post IDs associated with the jobs
     * @param int[]    $stringIds    String IDs associated with the jobs
     * @param int[]    $packageIds   Package IDs associated with the jobs
     * @param string   $wpmlVersion  WPML version
     *
     * @return array{ids: array<int>, wpml_version: string, ordering?: array{mode: string, home_post_id: int|null, positions: array<string, int>, meta: array<string, array{tier: int, rank: array<int|string>}>}}
     */
  public function buildSyncPayload(
        array $ateJobIds,
        array $postIds = [],
        array $stringIds = [],
        array $packageIds = [],
        string $wpmlVersion = '4.9.0'
    ): array {
      $payload = [
          'ids'          => $ateJobIds,
          'wpml_version' => $wpmlVersion,
      ];

      if ( ! empty( $postIds ) || ! empty( $stringIds ) || ! empty( $packageIds ) ) {
          $ordering = $this->priorityService->buildOrderingPayloadArray(
            $postIds,
            $stringIds,
            $packageIds
          );

        if ( ! empty( $ordering['positions'] ) ) {
          $payload['ordering'] = $ordering;
        }
      }

      return $payload;
  }


    /**
     * Sort ATE job IDs based on their associated content priority.
     *
     * @param array<int, int> $ateJobIdToPostIdMap Map of ATE job ID => post ID
     *
     * @return int[] Sorted ATE job IDs
     */
  public function sortAteJobIdsByPostPriority( array $ateJobIdToPostIdMap ): array {
    if ( empty( $ateJobIdToPostIdMap ) ) {
        return [];
    }

      $postIds       = array_values( $ateJobIdToPostIdMap );
      $sortedPostIds = $this->priorityService->sortPostIds( $postIds );

      $postIdToAteJobIds = [];
    foreach ( $ateJobIdToPostIdMap as $ateJobId => $postId ) {
      if ( ! isset( $postIdToAteJobIds[ $postId ] ) ) {
          $postIdToAteJobIds[ $postId ] = [];
      }
        $postIdToAteJobIds[ $postId ][] = $ateJobId;
    }

      $sortedAteJobIds = [];
    foreach ( $sortedPostIds as $postId ) {
      if ( isset( $postIdToAteJobIds[ $postId ] ) ) {
        foreach ( $postIdToAteJobIds[ $postId ] as $ateJobId ) {
          $sortedAteJobIds[] = $ateJobId;
        }
      }
    }

      return $sortedAteJobIds;
  }


    /**
     * Get ordering payload for a set of post IDs.
     *
     * @param int[] $postIds
     *
     * @return OrderingPayload
     */
  public function getOrderingPayloadForPosts( array $postIds ): OrderingPayload {
      return $this->priorityService->buildOrderingPayload( $postIds );
  }


    /**
     * Get ordering payload array for posts, strings, and packages.
     *
     * @param int[] $postIds
     * @param int[] $stringIds
     * @param int[] $packageIds
     *
     * @return array{mode: string, home_post_id: int|null, positions: array<string, int>, meta: array<string, array{tier: int, rank: array<int|string>}>}
     */
  public function getOrderingPayloadArrayForPosts(
        array $postIds,
        array $stringIds = [],
        array $packageIds = []
    ): array {
      return $this->priorityService->buildOrderingPayloadArray( $postIds, $stringIds, $packageIds );
  }


    /**
     * Get tier and rank for a single post.
     *
     * @param int $postId
     *
     * @return array{tier: int, rank: array<int|string>}|null
     */
  public function getTierAndRankForPost( int $postId ) {
      $payload = $this->priorityService->buildOrderingPayloadArray( [ $postId ] );
      /** @var string $key */
      $key = (string) $postId;

    if ( ! array_key_exists( $key, $payload['meta'] ) ) {
        return null;
    }

      return [
          'tier' => $payload['meta'][ $key ]['tier'],
          'rank' => $payload['meta'][ $key ]['rank'],
      ];
  }


}
