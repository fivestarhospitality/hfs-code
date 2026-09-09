<?php

namespace WPML\Core\Component\ReportContentStats\Domain\Repository;

use WPML\Core\Component\ReportContentStats\Domain\ProcessingLock;

interface ProcessingLockRepositoryInterface {


    /**
     * Attempt to acquire a lock atomically
     *
     * @return bool True if lock was acquired, false if already locked
     */
  public function acquire(): bool;


    /**
     * Get current lock if exists
     *
     * @return ProcessingLock|null
     */
  public function get();


    /**
     * Release the lock
     *
     * @return void
     */
  public function release();


    /**
     * Update existing lock (extend timeout)
     *
     * @param ProcessingLock $lock
     *
     * @return void
     */
  public function update( ProcessingLock $lock );


}
