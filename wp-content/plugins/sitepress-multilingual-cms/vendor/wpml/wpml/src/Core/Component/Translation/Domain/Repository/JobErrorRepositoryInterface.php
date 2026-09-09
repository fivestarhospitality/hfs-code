<?php

namespace WPML\Core\Component\Translation\Domain\Repository;

use WPML\Core\Component\Translation\Domain\Entity\JobError;

interface JobErrorRepositoryInterface {


  /**
   * Find a job error by job ID.
   *
   * @param int $jobId Job ID.
   *
   * @return JobError|null
   */
  public function findByJobId( int $jobId );


  /**
   * Insert a new job error.
   *
   * @param JobError $jobError
   * @return void
   */
  public function insert( JobError $jobError );


  /**
   * Increment the counter for a job error by its ID.
   *
   * @param int $jobId Job error ID.
   *
   * @return void
   */
  public function incrementCounter( int $jobId );


  /**
   * Delete a job error by job ID.
   *
   * @param int $jobId Job ID.
   *
   * @return void
   */
  public function delete( int $jobId );


  /**
   * Count all job errors.
   *
   * @return int
   */
  public function count(): int;


}
