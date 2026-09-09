<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Event\JobsCancelledEvent;
use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\TranslationRepositoryInterface;
use WPML\Core\Component\Translation\Application\Service\PreviousState\PreviousStateService;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationEditor\AteEditor;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Port\Event\DispatcherInterface;
use WPML\Core\SharedKernel\Component\String\Application\Service\StringBatchCleanupService;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

/**
 * Service for canceling translation jobs.
 *
 * Handles three scenarios:
 * 1. Orphan translations (element_id IS NULL): Set status to cancelled (0)
 * 2. Existing translations (element_id exists): Restore previous state
 * 3. String batch translations: Clean up string-specific records (string_translations, string_status, string_batches)
 */
class CancelJobsService {

  /** @var TranslationQueryInterface */
  private $translationQuery;

  /** @var TranslationRepositoryInterface */
  private $translationRepository;

  /** @var PreviousStateService */
  private $previousStateService;

  /** @var DispatcherInterface */
  private $eventDispatcher;

  /** @var StringBatchCleanupService */
  private $stringBatchCleanupService;


  /**
   * Constructor.
   *
   * @param TranslationQueryInterface      $translationQuery
   * @param TranslationRepositoryInterface $translationRepository
   * @param PreviousStateService           $previousStateService
   * @param DispatcherInterface            $eventDispatcher
   * @param StringBatchCleanupService      $stringBatchCleanupService
   */
  public function __construct(
    TranslationQueryInterface $translationQuery,
    TranslationRepositoryInterface $translationRepository,
    PreviousStateService $previousStateService,
    DispatcherInterface $eventDispatcher,
    StringBatchCleanupService $stringBatchCleanupService
  ) {
    $this->translationQuery            = $translationQuery;
    $this->translationRepository       = $translationRepository;
    $this->previousStateService        = $previousStateService;
    $this->eventDispatcher             = $eventDispatcher;
    $this->stringBatchCleanupService   = $stringBatchCleanupService;
  }


  /**
   * Cancel jobs.
   *
   * Cancellation flow:
   * 1. Retrieve Translation entity by job ID
   * 2. Get previous state to determine if orphan or existing translation
   * 3a. If no previous state (orphan): Set status to cancelled (0)
   * 3b. If previous state exists: Restore previous state using PreviousStateService
   * 3c. If translation type is STRING_BATCH: Clean up string-specific records
   * 4. Trigger JobsCancelledEvent for ATE cancellation
   *
   * @param int[] $jobIds Array of job IDs (job_id from icl_translate_job)
   *
   * @return array{cancelledJobIds: int[], restoredStatuses: array<int, int>} Array with cancelled job IDs and their restored statuses
   * @throws \WPML\PHP\Exception\InvalidItemIdException
   */
  public function cancelJobs( array $jobIds ): array {
    if ( empty( $jobIds ) ) {
      return [
        'cancelledJobIds'  => [],
        'restoredStatuses' => [],
      ];
    }

    $cancelledJobIds  = [];
    $restoredStatuses = [];
    $jobDataForEvent  = [];

    foreach ( $jobIds as $jobId ) {
      $translation = $this->translationQuery->getOneByJobId( $jobId );
      if ( ! $translation ) {
        continue;
      }

      $translationType = $translation->getType()->get();

      // Get the previous state first to determine if this is a first or existing translation.
      // We cannot rely on translatedElementId being null for first because string packages
      // have null translatedElementId even when they are completed.
      $previousState = $this->previousStateService->get( $translation->getId() );

      if ( $previousState === null ) {
        $this->translationRepository->setCancelledStatus( $translation->getId() );
        $restoredStatuses[ $jobId ] = TranslationStatus::NOT_TRANSLATED;
      } else {
        // SCENARIO B: Existing translation - RESTORE previous state
        $restoredStatuses[ $jobId ] = $this->determineRestoredStatus( $previousState );

        $this->previousStateService->revertToPreviousState( $translation->getId() );
      }

      // SCENARIO C: String batch translation - CLEANUP string-specific records
      if ( $translationType === TranslationType::STRING_BATCH ) {
        $this->stringBatchCleanupService->cleanupBatch(
          $translation->getOriginalElementId(),
          $translation->getTargetLanguageCode()
        );
      }

      // 3. Collect data for event
      $cancelledJobIds[] = $jobId;
      $jobDataForEvent[] = $this->buildJobDataForEvent( $translation );
    }

    // 4. Dispatch event for ATE cancellation
    if ( ! empty( $jobDataForEvent ) ) {
      $this->eventDispatcher->dispatch(
        new JobsCancelledEvent( $jobDataForEvent )
      );
    }

    return [
      'cancelledJobIds'  => $cancelledJobIds,
      'restoredStatuses' => $restoredStatuses,
    ];
  }


  /**
   * Cancel all jobs in a batch.
   *
   * @param int $batchId Batch ID from icl_translation_status.batch_id
   *
   * @return array{cancelledJobIds: int[], restoredStatuses: array<int, int>}
   * @throws \WPML\PHP\Exception\InvalidItemIdException
   */
  public function cancelJobsInBatch( int $batchId ): array {
    $jobIds = $this->translationQuery->getJobIdsByBatchId( $batchId );

    if ( empty( $jobIds ) ) {
      return [
        'cancelledJobIds'  => [],
        'restoredStatuses' => [],
      ];
    }

    return $this->cancelJobs( $jobIds );
  }


  /**
   * Build job data for event.
   *
   * Creates object with fields needed by ATE cancellation.
   * Extracts editor_job_id from AteEditor when available.
   *
   * @param Translation $translation Translation entity
   *
   * @return object Job data for event
   */
  private function buildJobDataForEvent( Translation $translation ) {
    $job = $translation->getJob();

    $jobData = (object) [];
    // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Required for backward compatibility with wpml_tm_jobs_cancelled hook
    $jobData->job_id        = $job ? $job->getId() : null;
    $jobData->editor        = null;
    // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Required for backward compatibility with wpml_tm_jobs_cancelled hook
    $jobData->editor_job_id = null;

    if ( $job ) {
      $editor          = $job->getEditor();
      $jobData->editor = $editor->get();

      // Get editor_job_id for ATE jobs
      if ( $editor instanceof AteEditor ) {
        // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Required for backward compatibility with wpml_tm_jobs_cancelled hook
        $jobData->editor_job_id = $editor->getEditorJobId();
      }
    }

    return $jobData;
  }


  /**
   * Determine the restored status from previous state data.
   *
   * Handles the special case where needs_update is stored as a separate property,
   * not as part of the status field.
   *
   * @param array{status: int, needs_update: bool}|null $previousState Previous state data
   *
   * @return int The TranslationStatus constant value
   */
  private function determineRestoredStatus( ?array $previousState = null ): int {
    if ( ! $previousState ) {
      return TranslationStatus::NOT_TRANSLATED;
    }

    // needs_update is stored as separate property, not in status field
    if ( ! empty( $previousState['needs_update'] ) ) {
      return TranslationStatus::NEEDS_UPDATE;
    }

    return $previousState['status'];
  }


}
