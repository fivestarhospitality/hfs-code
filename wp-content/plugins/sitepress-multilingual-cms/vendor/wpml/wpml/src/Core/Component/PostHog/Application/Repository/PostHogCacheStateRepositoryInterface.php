<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface PostHogCacheStateRepositoryInterface {


    /**
     * Returns the Unix timestamp of the last successful API call, or null if never checked.
     */
  public function getLastChecked(): ?int;


    /** @return void */
  public function setLastChecked( int $timestamp );


    /**
     * Returns true when last_checked is null or older than $ttlSeconds.
     */
  public function isStale( int $ttlSeconds ): bool;


    /**
     * Atomically acquires a short-lived concurrency lock to prevent duplicate simultaneous
     * API calls. Returns true if the lock was acquired (caller should proceed), false if
     * another request already holds it (caller should skip).
     *
     * This lock is NOT cache state. It must always be released via releaseProcessingLock()
     * when the API call completes, whether it succeeds or fails.
     */
  public function acquireProcessingLock(): bool;


    /** @return void */
  public function releaseProcessingLock();


}
