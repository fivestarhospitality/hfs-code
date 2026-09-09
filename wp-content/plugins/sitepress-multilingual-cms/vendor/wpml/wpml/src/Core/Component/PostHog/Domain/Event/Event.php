<?php

namespace WPML\Core\Component\PostHog\Domain\Event;

abstract class Event implements EventInterface {

  /** @var array<string, mixed> */
  protected $properties;


  /**
   * @param array<string,mixed> $properties
   *
   * @return void
   */
  public function __construct( array $properties ) {
    $this->properties = $properties;
  }


  abstract public function getName(): string;


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
