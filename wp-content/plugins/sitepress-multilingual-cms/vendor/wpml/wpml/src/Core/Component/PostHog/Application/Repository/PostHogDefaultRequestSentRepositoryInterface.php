<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface PostHogDefaultRequestSentRepositoryInterface {


  public function isSent(): bool;


  /**
   * Try to acquire the lock atomically.
   *
   * @return bool True if lock was acquired (you should make the API call),
   *              False if already acquired (skip the API call)
   */
  public function tryAcquireLock(): bool;


  /**
   * @param bool $isSent
   *
   * @return void
   */
  public function setIsSent( bool $isSent );


  /**
   * Delete the flag to allow retry
   *
   * @return void
   */
  public function delete();


}
