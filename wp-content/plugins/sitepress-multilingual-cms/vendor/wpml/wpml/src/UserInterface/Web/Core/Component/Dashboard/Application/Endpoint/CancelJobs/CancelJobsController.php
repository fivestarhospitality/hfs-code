<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\CancelJobs;

use WPML\Core\Component\Translation\Application\Service\CancelJobsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;

/**
 * Endpoint controller for canceling translation jobs.
 *
 * Handles HTTP requests to cancel jobs that couldn't be completed due to
 * unexpected errors.
 */
class CancelJobsController implements EndpointInterface {

    /** @var CancelJobsService */
    private $cancelJobsService;


    /**
     * Constructor.
     *
     * @param CancelJobsService $cancelJobsService Service for canceling jobs.
     */
  public function __construct( CancelJobsService $cancelJobsService ) {
      $this->cancelJobsService = $cancelJobsService;
  }


    /**
     * Handle the request to cancel unsolvable jobs.
     *
     * @param array<mixed> $requestData Expected format: ['jobIds' => [int, ...]]
     *
     * @return array{success: bool, data: array{cancelledJobIds: array<int>, restoredStatuses: array<int, int>}} Response format: ['success' => bool, 'data' => ['cancelledJobIds' => [...], 'restoredStatuses' => [jobId => status, ...]]]
     *
     * @throws InvalidArgumentException If the request data is invalid.
     * @throws \WPML\PHP\Exception\InvalidItemIdException If cancelling jobs fails.
     */
  public function handle( $requestData = null ): array {
      $requestData = $requestData ?: [];

      $jobIds = $requestData['jobIds'] ?? null;

    if ( $jobIds === null ) {
        throw new InvalidArgumentException( 'jobIds array is required.' );
    }

    if ( ! is_array( $jobIds ) ) {
        throw new InvalidArgumentException( 'jobIds must be an array.' );
    }

      $result = $this->cancelJobsService->cancelJobs( $jobIds );

      return [
          'success' => true,
          'data'    => $result,
      ];
  }


}
