<?php

namespace WPML\Core\Component\ReportContentStats\Domain\Repository;

interface RetryRepositoryInterface {


  /**
   * @return array{attempt_count: int, last_attempt_timestamp: int|null}|null
   */
  public function get();


  /**
   * @param array{attempt_count: int, last_attempt_timestamp: int|null} $retryData
   *
   * @return void
   */
  public function update( array $retryData );


  /**
   * @return void
   */
  public function delete();


}
