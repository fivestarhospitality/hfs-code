<?php

namespace WPML\UserInterface\Web\Core\Component\ContentStats\Application\Endpoint\CalculateContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\ContentChangeDetectionService;
use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsService;
use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsServiceException;
use WPML\Core\Component\ReportContentStats\Application\Service\EventReasonService;
use WPML\Core\Component\ReportContentStats\Application\Service\LastSentService;
use WPML\Core\Component\ReportContentStats\Application\Service\ProcessingLockService;
use WPML\Core\Component\ReportContentStats\Application\Service\ReportPreparer\ReportPreparerService;
use WPML\Core\Component\ReportContentStats\Application\Service\ReportSender\ReportSenderService;
use WPML\Core\Component\ReportContentStats\Application\Service\RetryService;
use WPML\Core\Port\Endpoint\EndpointInterface;

class ProcessContentStatsController implements EndpointInterface {

  /** @var LastSentService */
  private $lastSentService;

  /** @var ContentStatsService */
  private $contentStatsService;

  /** @var ReportPreparerService */
  private $reportPreparerService;

  /** @var ReportSenderService */
  private $reportSenderService;

  /** @var RetryService */
  private $retryService;

  /** @var ProcessingLockService */
  private $processingLockService;

  /** @var EventReasonService */
  private $eventReasonService;

  /** @var ContentChangeDetectionService */
  private $contentChangeDetectionService;


  public function __construct(
    ContentStatsService $contentStatsService,
    LastSentService $lastSentService,
    ReportPreparerService $reportPreparerService,
    ReportSenderService $reportSenderService,
    RetryService $retryService,
    ProcessingLockService $processingLockService,
    EventReasonService $eventReasonService,
    ContentChangeDetectionService $contentChangeDetectionService
  ) {
    $this->contentStatsService            = $contentStatsService;
    $this->lastSentService                = $lastSentService;
    $this->reportPreparerService          = $reportPreparerService;
    $this->reportSenderService            = $reportSenderService;
    $this->retryService                   = $retryService;
    $this->processingLockService          = $processingLockService;
    $this->eventReasonService             = $eventReasonService;
    $this->contentChangeDetectionService  = $contentChangeDetectionService;
  }


  public function handle( $requestData = null ): array {
    /** @var string|null $ownerId */
    $ownerId = $requestData['ownerId'] ?? null;

    $lockError = $this->acquireOrRefreshLock( $ownerId );
    if ( $lockError ) {
      return $lockError;
    }

    try {
      $processedPostTypes = $this->contentStatsService->processPostTypes();

      /**
       * In case $processedPostTypes is not FALSE, we return response and in next
       * iteration we decide if we should process more post types or send report.
       */

      if ( $processedPostTypes ) {
        return [
          'success' => true,
          'ownerId' => $ownerId,
          'message' => 'Calculation done for post types: ' .
                       implode( ',', $processedPostTypes ),
        ];
      }

      $sendError = $this->sendReportOrRetry( (string) $ownerId );
      if ( $sendError ) {
        return $sendError;
      }

      $this->finalizeReportingCycle();

      return [
        'success' => true,
        'message' => 'Report sent successfully!',
      ];
    } catch ( ContentStatsServiceException $e ) {
      return [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }


  /**
   * @param string $ownerId
   *
   * @return array<string, mixed>|null Error response on failure, null on success.
   */
  private function sendReportOrRetry( string $ownerId ) {
    $preparedReport         = $this->reportPreparerService->prepare();
    $reportSentSuccessfully = $this->reportSenderService->send( $preparedReport );

    if ( $reportSentSuccessfully ) {
      return null;
    }

    $this->retryService->incrementAttempt();

    if ( $this->retryService->hasExceededMaxAttempts() ) {
      $this->finalizeReportingCycle();

      return [
        'success' => false,
        'message' => 'Error when sending report - max retry attempts exceeded, will try again in 30 days',
      ];
    }

    return [
      'success' => false,
      'ownerId' => $ownerId,
      'message' => 'Error when sending report - will retry in ' .
                   $this->retryService->getRetryIntervalMinutes() . ' minutes',
    ];
  }


  /**
   * @param string|null $ownerId
   *
   * @return array<string, mixed>|null Error response if locked, null on success.
   */
  private function acquireOrRefreshLock( &$ownerId ) {
    if ( $this->processingLockService->isLockedByOthers( $ownerId ) ) {
      return [
        'success' => false,
        'locked'  => true,
        'message' => 'Content stats processing is already running in another tab/session',
      ];
    }

    if ( ! $ownerId ) {
      $ownerId = $this->processingLockService->acquire();
      if ( ! $ownerId ) {
        return [
          'success' => false,
          'locked'  => true,
          'message' => 'Content stats processing is already running in another tab/session',
        ];
      }
    }

    $this->processingLockService->refresh( $ownerId );

    return null;
  }


  private function finalizeReportingCycle(): void {
    $this->retryService->reset();
    $this->lastSentService->update( time() );
    $this->contentStatsService->resetPostTypesStatsData();
    $this->processingLockService->release();
    $this->eventReasonService->clear();
    $this->contentChangeDetectionService->saveCurrentSnapshot();
  }


}
