<?php

namespace WPML\Core\Component\PostHog\Domain\Event\Custom;

use WPML\Core\Component\PostHog\Domain\Event\EventInterface;

class Event implements EventInterface {

  /** @var string */
  private $name;

  /** @var array<string,mixed> */
  private $properties;


  /**
   * @param string $name
   * @param array<string,mixed> $properties
   *
   * @return void
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
