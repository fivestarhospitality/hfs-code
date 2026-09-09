<?php

namespace WPML\Core\Component\Translation\Application\Query;

/**
 * @phpstan-type UnsolvableJobRow array{
 *    jobId: int,
 *    ateJobId: int,
 *    ateStatus: int,
 *    status: int,
 *    isUnsolvable: bool,
 *    message: string,
 *    errorType: string,
 *    errorData: string|null
 * }
 */
interface UnsolvableJobsQueryInterface {


    /**
     * Get jobs marked as unsolvable from the error table.
     * Returns jobs in the same format as the sync endpoint.
     *
     * @phpstan-return UnsolvableJobRow[]
     * @return array Array of jobs in sync endpoint format
     */
  public function getUnsolvableJobs(): array;


}
