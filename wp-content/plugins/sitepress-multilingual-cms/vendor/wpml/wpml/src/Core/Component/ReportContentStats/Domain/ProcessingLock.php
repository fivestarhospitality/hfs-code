<?php

namespace WPML\Core\Component\ReportContentStats\Domain;

class ProcessingLock {

  const LOCK_TIMEOUT_SECONDS = 30;

  /** @var int|null */
  private $acquiredAt;

  /** @var string|null */
  private $ownerId;


  /**
   * @param int|null $acquiredAt
   * @param string|null $ownerId
   */
  public function __construct( $acquiredAt = null, $ownerId = null ) {
    $this->acquiredAt = $acquiredAt;
    $this->ownerId    = $ownerId;
  }


  /**
   * Create a new lock with unique owner ID
   *
   * @return ProcessingLock
   */
  public static function create(): ProcessingLock {
    return new self( time(), self::generateOwnerId() );
  }


  /**
   * Generate unique owner ID
   *
   * @return string
   */
  private static function generateOwnerId(): string {
    if ( function_exists( 'wp_generate_uuid4' ) ) {
      return wp_generate_uuid4();
    }

    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0x0fff ) | 0x4000,
      mt_rand( 0, 0x3fff ) | 0x8000,
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0xffff )
    );
  }


  /**
   * Check if lock is currently active (not expired)
   *
   * @return bool
   */
  public function isActive(): bool {
    if ( $this->acquiredAt === null ) {
      return false;
    }

    return time() - $this->acquiredAt < self::LOCK_TIMEOUT_SECONDS;
  }


  /**
   * Check if lock has expired
   *
   * @return bool
   */
  public function hasExpired(): bool {
    if ( $this->acquiredAt === null ) {
      return true;
    }

    return time() - $this->acquiredAt >= self::LOCK_TIMEOUT_SECONDS;
  }


  /**
   * Extend lock timeout (refresh timestamp) keeping same owner
   *
   * @return ProcessingLock
   */
  public function extend(): ProcessingLock {
    return new self( time(), $this->ownerId );
  }


  /**
   * Get acquired timestamp
   *
   * @return int|null
   */
  public function getAcquiredAt() {
    return $this->acquiredAt;
  }


  /**
   * Get owner ID
   *
   * @return string|null
   */
  public function getOwnerId() {
    return $this->ownerId;
  }


  /**
   * Check if this lock is owned by given owner ID
   *
   * @param string $ownerId
   *
   * @return bool
   */
  public function isOwnedBy( string $ownerId ): bool {
    return $this->ownerId === $ownerId;
  }


}
