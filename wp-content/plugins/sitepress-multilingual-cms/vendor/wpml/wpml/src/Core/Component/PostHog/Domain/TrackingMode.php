<?php

namespace WPML\Core\Component\PostHog\Domain;

class TrackingMode {

  const DISABLED = 'disabled';
  const TEA_ONLY = 'tea_only';
  const ALL      = 'all';


  /**
   * @return string[]
   */
  public static function getAll(): array {
    return [
      self::DISABLED,
      self::TEA_ONLY,
      self::ALL,
    ];
  }


  public static function isValid( string $mode ): bool {
    return in_array( $mode, self::getAll(), true );
  }


  public static function fromBool( bool $shouldRecord ): string {
    return $shouldRecord ? self::ALL : self::DISABLED;
  }


  public static function toBool( string $mode ): bool {
    return $mode === self::TEA_ONLY || $mode === self::ALL;
  }


  /**
   * Single allow/deny decision for event capture.
   *
   * - ALL      → always allow
   * - TEA_ONLY → allow only TEA events
   * - DISABLED (or unknown) → deny
   *
   * @param string $mode       One of the TrackingMode constants.
   * @param bool   $isTeaEvent Whether the event is a TEA event.
   *
   * @return bool
   */
  public static function isEventAllowed( string $mode, bool $isTeaEvent ): bool {
    if ( $mode === self::ALL ) {
      return true;
    }

    if ( $mode === self::TEA_ONLY ) {
      return $isTeaEvent;
    }

    return false;
  }


}
