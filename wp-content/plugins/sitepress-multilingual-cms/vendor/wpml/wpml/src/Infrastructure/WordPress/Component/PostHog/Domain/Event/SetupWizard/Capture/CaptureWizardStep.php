<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\Capture;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardLastStepSubmissionTimeRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Core\Port\Remote\RemoteInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture;

class CaptureWizardStep extends Capture {

  /** @var SetupWizardUUIDRepositoryInterface */
  private $wizardUUIDRepository;

  /** @var SetupWizardLastStepSubmissionTimeRepositoryInterface */
  private $wizardStepSubmissionTimeRepository;


  public function __construct(
    PostHogStateRepositoryInterface $postHogStateRepository,
    SetupWizardUUIDRepositoryInterface $wizardUUIDRepository,
    SetupWizardLastStepSubmissionTimeRepositoryInterface $wizardStepSubmissionTimeRepository,
    RemoteInterface $remote
  ) {
    $this->wizardUUIDRepository               = $wizardUUIDRepository;
    $this->wizardStepSubmissionTimeRepository = $wizardStepSubmissionTimeRepository;
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

    $wizardUUID = $this->wizardUUIDRepository->get();

    if ( ! TrackingMode::isEventAllowed( $this->postHogStateRepository->getTrackingMode(), false ) || ! $wizardUUID ) {
      return false;
    }

    // current time as step submission time
    $stepSubmissionTime = time();
    // get the last step submission time to be used for duration calculation
    $lastStepSubmissionTime = $this->wizardStepSubmissionTimeRepository->get();

    // calculate the difference between last and current steps submission times
    $stepDurationSeconds = $lastStepSubmissionTime ?
      $stepSubmissionTime - $lastStepSubmissionTime :
      null;

    // update the last step submission time with current step submission time
    $this->wizardStepSubmissionTimeRepository->save( $stepSubmissionTime );

    // add properties to the event
    $event->addProperties(
      [
      'wizard_uuid'           => $wizardUUID,
      'step_duration_seconds' => $stepDurationSeconds,
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
