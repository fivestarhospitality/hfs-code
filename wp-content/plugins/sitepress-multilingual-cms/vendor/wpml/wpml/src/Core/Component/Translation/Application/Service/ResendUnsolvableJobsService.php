<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\SendToTranslationDtoCollectionBuilder;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\TimestampBatchNameGenerator;
use WPML\Core\Component\Translation\Application\Service\TranslationService\TranslationServiceException;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

/**
 * Service for resending unsolvable translation jobs.
 *
 * Orchestrates the complete flow:
 * 1. Retrieves translations by job IDs
 * 2. Prepares DTOs (before cancellation - requires DB access)
 * 3. Cancels existing jobs
 * 4. Sends prepared DTOs to translation service
 *
 * @phpstan-import-type ResultDtoArray from \WPML\Core\Component\Translation\Application\Service\TranslationService\Dto\ResultDto
 */
class ResendUnsolvableJobsService {

  /** @var TranslationQueryInterface */
  private $translationQuery;

  /** @var CancelJobsService */
  private $cancelJobsService;

  /** @var TranslationService */
  private $translationService;

  /** @var SendToTranslationDtoCollectionBuilder */
  private $dtoCollectionBuilder;

  /** @var TimestampBatchNameGenerator */
  private $batchNameGenerator;


  public function __construct(
    TranslationQueryInterface $translationQuery,
    CancelJobsService $cancelJobsService,
    TranslationService $translationService,
    SendToTranslationDtoCollectionBuilder $dtoCollectionBuilder,
    TimestampBatchNameGenerator $batchNameGenerator
  ) {
    $this->translationQuery     = $translationQuery;
    $this->cancelJobsService    = $cancelJobsService;
    $this->translationService   = $translationService;
    $this->dtoCollectionBuilder = $dtoCollectionBuilder;
    $this->batchNameGenerator   = $batchNameGenerator;
  }


  /**
   * Resend unsolvable jobs.
   *
   * @param int[] $jobIds Array of job IDs to resend.
   * @param string|null $batchName Optional batch name to use for all jobs.
   *
   * @phpstan-return array{batchName: string, results: array<string, ResultDtoArray>}
   *
   * @throws Exception If building DTO fails.
   * @throws TranslationServiceException If sending to translation fails.
   * @throws InvalidArgumentException If DTO validation fails.
   * @throws InvalidItemIdException If cancelling jobs fails.
   */
  public function resend( array $jobIds, $batchName = null ): array {
    $translations = $this->translationQuery->getManyByJobIds( $jobIds );
    if ( empty( $translations ) ) {
      throw new InvalidArgumentException( 'No translations found for the provided job IDs.' );
    }

    // Prepare all DTOs BEFORE cancellation (requires DB access to batch data)
    $batchName = $batchName ?: $this->batchNameGenerator->generate();
    $dtos      = $this->dtoCollectionBuilder->buildCollection( $batchName, $translations );

    // Cancel jobs (deletes batch data from DB)
    $this->cancelJobsService->cancelJobs( $jobIds );

    // Send translations AFTER cancellation (uses prepared DTOs)
    $results = [];
    foreach ( $dtos as $sourceLanguage => $dto ) {
      $result                     = $this->translationService->send( $dto );
      $results[ $sourceLanguage ] = $result->toArray();
    }

    return [
      'batchName' => $batchName,
      'results'   => $results,
    ];
  }


}
