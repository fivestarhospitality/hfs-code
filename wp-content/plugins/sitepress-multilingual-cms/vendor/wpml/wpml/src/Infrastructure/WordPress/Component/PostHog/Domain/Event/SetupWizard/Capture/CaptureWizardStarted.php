<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\Capture;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardUUIDInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Core\Port\Remote\RemoteInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture;

class CaptureWizardStarted extends Capture {

  /** @var SetupWizardUUIDInterface */
  private $wizardUUID;

  /** @var SetupWizardStartTimeRepositoryInterface */
  private $wizardStartTimeRepository;


  public function __construct(
    PostHogStateRepositoryInterface $postHogStateRepository,
    SetupWizardUUIDInterface $wizardUUID,
    SetupWizardStartTimeRepositoryInterface $wizardStartTimeRepository,
    RemoteInterface $remote
  ) {
    $this->wizardUUID                = $wizardUUID;
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

    // create UUID for the wizard to be used for tracking
    $wizardUUID = $this->wizardUUID->create();
    // current time as wizard start time
    $wizardStartTime = time();
    // update the wizard start time repository to be used for later calculation
    $this->wizardStartTimeRepository->save( $wizardUUID, $wizardStartTime );

    // add properties to the event
    $event->addProperties(
      [
      'wizard_uuid'       => $wizardUUID,
      'wizard_start_time' => $wizardStartTime,
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
