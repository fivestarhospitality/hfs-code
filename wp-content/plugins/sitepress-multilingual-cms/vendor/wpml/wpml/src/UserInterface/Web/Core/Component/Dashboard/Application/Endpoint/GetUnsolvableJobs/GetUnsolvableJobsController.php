<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetUnsolvableJobs;

use WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;

/**
 * @phpstan-import-type UnsolvableJobRow from UnsolvableJobsQueryInterface
 */
class GetUnsolvableJobsController implements EndpointInterface {

    /** @var UnsolvableJobsQueryInterface */
    private $query;


  public function __construct( UnsolvableJobsQueryInterface $query ) {
      $this->query = $query;
  }


    /**
     * Handle the request to get unsolvable jobs.
     *
     * @param mixed $requestData
     * @phpstan-return array{jobs: UnsolvableJobRow[]}
     * @return array
     */
  public function handle( $requestData = null ): array {
      return [
          'jobs' => $this->query->getUnsolvableJobs()
      ];
  }


}
