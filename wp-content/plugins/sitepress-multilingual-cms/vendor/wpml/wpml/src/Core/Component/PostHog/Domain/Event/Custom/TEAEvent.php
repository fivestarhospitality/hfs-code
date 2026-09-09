<?php

namespace WPML\Core\Component\PostHog\Domain\Event\Custom;

use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Component\PostHog\Domain\Event\TEAEventInterface;

/**
 * A custom (JS-proxy-originated) event that carries TEA classification.
 *
 * Use this instead of Event when the JS caller set isTEAEvent: true, so that
 * CaptureEventService allows the event through in tea_only tracking mode.
 */
class TEAEvent implements EventInterface, TEAEventInterface {

  /** @var string */
  private $name;

  /** @var array<string,mixed> */
  private $properties;


  /**
   * @param string              $name
   * @param array<string,mixed> $properties
   */
  public function __construct( string $name, array $properties ) {
    $this->name       = $name;
    $this->properties = $properties;
  }


  public function getName(): string {
    return $this->name;
  }


  /** @return array<string,mixed> */
  public function getProperties(): array {
    return $this->properties;
  }


  /**
   * @param array<string,mixed> $properties
   *
   * @return void
   */
  public function addProperties( array $properties ) {
    $this->properties = array_merge( $this->properties, $properties );
  }


}
