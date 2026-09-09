<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\Capture;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Core\Port\Remote\RemoteInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture;

class CaptureWizardCompleted extends Capture {

  /** @var SetupWizardUUIDRepositoryInterface */
  private $wizardUUIDRepository;

  /** @var SetupWizardStartTimeRepositoryInterface */
  private $wizardStartTimeRepository;


  public function __construct(
    PostHogStateRepositoryInterface $postHogStateRepository,
    SetupWizardUUIDRepositoryInterface $wizardUUIDRepository,
    SetupWizardStartTimeRepositoryInterface $wizardStartTimeRepository,
    RemoteInterface $remote
  ) {
    $this->wizardUUIDRepository      = $wizardUUIDRepository;
    $this->wizardStartTimeRepository = $wizardStartTimeRepository;
    parent::__construct( $postHogStateRepository, $remote );
  }


  public function capture(
    string $apiKey,
    string $host,
    string $distinctId,
    string $sessionId,
    EventInterface $event,
    array $personProperties = []
  ): bool {

    if ( ! TrackingMode::isEventAllowed( $this->postHogStateRepository->getTrackingMode(), false ) ) {
      return false;
    }

    // get the wizard UUID
    $wizardUUID = $this->wizardUUIDRepository->get();
    // current time as wizard finished time
    $now = time();
    // get the wizard start time
    $wizardStartTime = $wizardUUID ?
      $this->wizardStartTimeRepository->get( $wizardUUID ) :
      false;
    // calculate the difference between wizard start and end time
    $wizardDurationSeconds = $wizardStartTime ? $now - $wizardStartTime : 0;

    // add properties to the event
    $event->addProperties(
      [
      'wizard_uuid'             => $wizardUUID,
      'wizard_duration_seconds' => $wizardDurationSeconds,
       ]
    );

    // capture the event
    return parent::capture(
      $apiKey,
      $host,
      $distinctId,
      $sessionId,
      $event,
      $personProperties
    );
  }


}
