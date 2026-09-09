<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

use WPML\Core\Component\ReportContentStats\Domain\Query\PublishedPostCountQueryInterface;
use WPML\Core\Component\ReportContentStats\Domain\Repository\ContentSnapshotRepositoryInterface;

class ContentChangeDetectionService {

  const PERCENTAGE_THRESHOLD         = 0.20;
  const ABSOLUTE_THRESHOLD           = 50;
  const MIN_BASELINE_FOR_PERCENTAGE  = 50;

  /** @var ResendTriggerService */
  private $resendTriggerService;

  /** @var ContentSnapshotRepositoryInterface */
  private $snapshotRepository;

  /** @var PublishedPostCountQueryInterface */
  private $postCountQuery;


  public function __construct(
    ResendTriggerService $resendTriggerService,
    ContentSnapshotRepositoryInterface $snapshotRepository,
    PublishedPostCountQueryInterface $postCountQuery
  ) {
    $this->resendTriggerService = $resendTriggerService;
    $this->snapshotRepository   = $snapshotRepository;
    $this->postCountQuery       = $postCountQuery;
  }


  public function saveCurrentSnapshot(): void {
    $this->snapshotRepository->save( $this->postCountQuery->get() );
  }


  public function check(): void {
    $baseline = $this->snapshotRepository->get();

    if ( $baseline === null ) {
      return;
    }

    $current = $this->postCountQuery->get();
    $diff    = abs( $current - $baseline );

    if ( $this->isSignificantChange( $baseline, $diff ) ) {
      $this->resendTriggerService->trigger( EventReasonService::REASON_CONTENT_CHANGE );
      $this->snapshotRepository->save( $current );
    }
  }


  /**
   * Two independent thresholds — either one is sufficient to trigger:
   *
   * 1. Absolute (50+ posts): catches large changes on any site.
   *    E.g. a 10,000-post site adding 50 posts (only 0.5%) still triggers.
   *
   * 2. Percentage (20%, only when baseline > 50): catches significant
   *    relative changes on medium/large sites that don't reach the
   *    absolute threshold. E.g. a 60-post site adding 13 posts (21.6%)
   *    triggers even though diff < 50. Disabled on small sites
   *    (baseline <= 50) to avoid noise and division-by-zero.
   */
  private function isSignificantChange( int $baseline, int $diff ): bool {
    if ( $diff >= self::ABSOLUTE_THRESHOLD ) {
      return true;
    }

    $isPercentageApplicable = $baseline > self::MIN_BASELINE_FOR_PERCENTAGE;

    return $isPercentageApplicable && ( $diff / $baseline ) >= self::PERCENTAGE_THRESHOLD;
  }


}
