<?php

namespace WPML\Core\Component\PostHog\Domain\Event;

interface EventInterface {


  public function getName(): string;


  /** @return array<string, mixed> */
  public function getProperties(): array;


  /**
   * @param array<string,mixed> $properties
   *
   * @return void
   */
  public function addProperties( array $properties );


}
