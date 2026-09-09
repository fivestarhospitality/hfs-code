<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\ResendUnsolvableJobs;

use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;

/**
 * Endpoint controller for resending unsolvable translation jobs.
 *
 * Delegates business logic to ResendUnsolvableJobsService.
 *
 * @phpstan-import-type ResultDtoArray from \WPML\Core\Component\Translation\Application\Service\TranslationService\Dto\ResultDto
 */
class ResendUnsolvableJobsController implements EndpointInterface {

  /** @var ResendUnsolvableJobsService */
  private $resendService;


  /**
   * Constructor.
   *
   * @param ResendUnsolvableJobsService $resendService Service for resending jobs.
   */
  public function __construct( ResendUnsolvableJobsService $resendService ) {
    $this->resendService = $resendService;
  }


  /**
   * Handle the request to resend unsolvable jobs.
   *
   * @param array<mixed> $requestData Expected format: ['jobIds' => [int, int, ...], 'batchName' => string]
   *
   * @phpstan-return array{success: bool, data: array{batchName: string, results: array<string, ResultDtoArray>}|string}
   *
   * @throws InvalidArgumentException If the request data is invalid.
   * @throws \WPML\PHP\Exception\InvalidItemIdException If cancelling jobs fails.
   */
  public function handle( $requestData = null ): array {
    $requestData = $requestData ?: [];

    $jobIds    = $requestData['jobIds'] ?? null;
    $batchName = $requestData['batchName'] ?? null;

    if ( $jobIds === null ) {
      throw new InvalidArgumentException( 'jobIds array is required.' );
    }

    if ( ! is_array( $jobIds ) ) {
      throw new InvalidArgumentException( 'jobIds must be an array.' );
    }

    if ( empty( $jobIds ) ) {
      return [
        'success' => false,
        'data'    => 'No job IDs provided.',
      ];
    }

    try {
      $result = $this->resendService->resend( $jobIds, $batchName );

      return [
        'success' => true,
        'data'    => $result,
      ];
    } catch ( Exception $e ) {
      return [
        'success' => false,
        'data'    => 'Error resending jobs: ' . $e->getMessage(),
      ];
    }
  }


}
