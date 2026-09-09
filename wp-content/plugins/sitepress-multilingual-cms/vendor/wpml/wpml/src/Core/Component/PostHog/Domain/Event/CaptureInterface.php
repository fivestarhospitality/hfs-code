<?php

namespace WPML\Core\Component\PostHog\Domain\Event;

use WPML\PHP\Exception\RemoteException;

interface CaptureInterface {


  /**
   * @param string $apiKey
   * @param string $host
   * @param string $distinctId
   * @param string $sessionId
   * @param EventInterface $event
   * @param array<string,mixed> $personProperties
   *
   * @return bool
   * @throws RemoteException
   */
  public function capture(
    string $apiKey,
    string $host,
    string $distinctId,
    string $sessionId,
    EventInterface $event,
    array $personProperties = []
  ): bool;


}
