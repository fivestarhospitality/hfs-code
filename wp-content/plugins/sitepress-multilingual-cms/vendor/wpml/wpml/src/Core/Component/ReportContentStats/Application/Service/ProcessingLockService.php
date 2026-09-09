<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

use WPML\Core\Component\ReportContentStats\Domain\Repository\ProcessingLockRepositoryInterface;

class ProcessingLockService {

  /** @var ProcessingLockRepositoryInterface */
  private $lockRepository;


  public function __construct( ProcessingLockRepositoryInterface $lockRepository ) {
    $this->lockRepository = $lockRepository;
  }


  /**
   * Check if processing is currently locked by any user
   *
   * @return bool
   */
  public function isLocked(): bool {
    $lock = $this->lockRepository->get();

    return $lock && $lock->isActive();
  }


  /**
   * Check if processing is locked by a different owner
   *
   * @param string|null $ownerId
   *
   * @return bool
   */
  public function isLockedByOthers( $ownerId ): bool {
    $lock = $this->lockRepository->get();
    if ( ! $lock || ! $lock->isActive() ) {
      return false;
    }

    if ( ! $ownerId ) {
      return true;
    }

    return ! $lock->isOwnedBy( $ownerId );
  }


  /**
   * Attempt to acquire lock
   *
   * @return string|null Owner ID on success, null on failure
   */
  public function acquire() {
    $acquired = $this->lockRepository->acquire();
    if ( ! $acquired ) {
      return null;
    }

    $lock = $this->lockRepository->get();

    return $lock ? $lock->getOwnerId() : null;
  }


  /**
   * Release lock
   *
   * @return void
   */
  public function release() {
    $this->lockRepository->release();
  }


  /**
   * Refresh lock to extend timeout by another 30 seconds
   *
   * @param string|null $ownerId
   *
   * @return bool True if refreshed successfully, false if not owned or doesn't exist
   */
  public function refresh( $ownerId ): bool {
    if ( ! $ownerId ) {
      return false;
    }

    $lock = $this->lockRepository->get();
    if ( ! $lock || ! $lock->isOwnedBy( $ownerId ) ) {
      return false;
    }

    $extendedLock = $lock->extend();
    $this->lockRepository->update( $extendedLock );

    return true;
  }


}
