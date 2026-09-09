<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\ContentChangeDetectionService;

class ContentChangeEventListener {

  /** @var ContentChangeDetectionService */
  private $contentChangeDetectionService;


  public function __construct( ContentChangeDetectionService $contentChangeDetectionService ) {
    $this->contentChangeDetectionService = $contentChangeDetectionService;
  }


  /** @param \WP_Post $post */
  public function onTransitionPostStatus( string $newStatus, string $oldStatus, $post ): void {
    // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
    if ( $post->post_type === 'revision' || $post->post_type === 'attachment' ) {
      return;
    }

    if ( $this->publishedPostCountChanged( $newStatus, $oldStatus ) ) {
      $this->contentChangeDetectionService->check();
    }
  }


  /**
   * The published post count changes when:
   * - A new or draft post gets published (newStatus = 'publish')
   * - A previously published post is trashed, deleted, or reverted to draft (oldStatus = 'publish')
   *
   * If both statuses are 'publish' (post updated without status change), the count
   * hasn't changed so we skip.
   */
  private function publishedPostCountChanged( string $newStatus, string $oldStatus ): bool {
    return $newStatus !== $oldStatus
      && ( $newStatus === 'publish' || $oldStatus === 'publish' );
  }


}
