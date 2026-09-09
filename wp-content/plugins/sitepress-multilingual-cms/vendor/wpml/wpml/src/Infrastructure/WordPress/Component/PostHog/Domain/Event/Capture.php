<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Event\CaptureInterface;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Port\Remote\RemoteInterface;
use WPML\PHP\Exception\RemoteException;

class Capture implements CaptureInterface {

  const POSTHOG_CAPTURE_ENDPOINT = '/i/v0/e/';

  /** @var PostHogStateRepositoryInterface */
  protected $postHogStateRepository;

  /** @var RemoteInterface */
  private $remote;


  public function __construct(
    PostHogStateRepositoryInterface $postHogStateRepository,
    RemoteInterface $remote
  ) {
    $this->postHogStateRepository = $postHogStateRepository;
    $this->remote                 = $remote;
  }


  /**
   * Capturing single custom event to PostHog API
   * https://posthog.com/docs/api/capture
   *
   * @param string $apiKey
   * @param string $host
   * @param string $distinctId
   * @param string $sessionId
   * @param EventInterface $event
   * @param array<string, mixed> $personProperties
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
  ): bool {
    if ( ! $this->postHogStateRepository->isEnabled() ) {
      return false;
    }

    if ( ! $distinctId ) {
      return false;
    }

    $properties = $event->getProperties();

    // Adding session ID to properties
    $properties['$session_id'] = $sessionId;

    // "$current_url" is a reserved key for PostHog that's used to define current URL
    $properties['$current_url'] = $properties['$current_url'] ?? $this->getCurrentUrl();

    if ( ! empty( $personProperties ) ) {
      // "$set" is a reserved key for PostHog that's used to define person properties
      $properties['$set'] = $personProperties;
    }

    $payload = [
      'api_key'     => $apiKey,
      'event'       => $event->getName(),
      'distinct_id' => $distinctId,
      'properties'  => $properties,
      'timestamp'   => $this->getCurrentTimestamp(),
    ];

    $this->remote->post(
      rtrim( $host, '/' ) . self::POSTHOG_CAPTURE_ENDPOINT,
      $payload
    );

    return true;
  }


  /**
   * Get the current timestamp in ISO 8601 format
   *
   * @return string
   */
  protected function getCurrentTimestamp() {
    return date( 'c' );
  }


  /**
   * Get the current URL
   *
   * @return string
   */
  protected function getCurrentUrl() {
    // For AJAX requests, use the referer to get the actual page URL
    if ( wp_doing_ajax() && isset( $_SERVER['HTTP_REFERER'] ) ) {
      return $_SERVER['HTTP_REFERER'];
    }

    if ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
      $protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

      return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    return admin_url();
  }


}
