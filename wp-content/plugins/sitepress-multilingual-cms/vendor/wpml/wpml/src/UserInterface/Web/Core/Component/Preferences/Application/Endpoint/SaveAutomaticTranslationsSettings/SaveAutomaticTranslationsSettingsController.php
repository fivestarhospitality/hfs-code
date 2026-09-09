<?php

namespace WPML\UserInterface\Web\Core\Component\Preferences\Application\Endpoint\SaveAutomaticTranslationsSettings;

use WPML\Core\Component\ATE\Application\Service\EngineServiceException;
use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Component\Translation\Application\Service\SettingsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\Exception;

/**
 * @phpstan-import-type EngineDtoArray from \WPML\Core\Component\ATE\Application\Service\Dto\EngineDto
 */
class SaveAutomaticTranslationsSettingsController implements EndpointInterface {

  /** @var SettingsRepository */
  private $settingsRepository;

  /** @var SettingsService */
  private $settingsService;

  /** @var EnginesServiceInterface */
  private $engineService;

  /** @var EnginesBuilder */
  private $enginesBuilder;

  /** @var ConfigService */
  private $configService;

  /** @var CaptureEventService */
  private $captureEventService;

  /** @var EventInstanceService */
  private $eventInstanceService;


  public function __construct(
    SettingsRepository $settingsRepository,
    SettingsService $settingsService,
    EnginesServiceInterface $engineService,
    EnginesBuilder $enginesBuilder,
    ConfigService $configService,
    CaptureEventService $captureEventService,
    EventInstanceService $eventInstanceService
  ) {
    $this->settingsRepository   = $settingsRepository;
    $this->settingsService      = $settingsService;
    $this->engineService        = $engineService;
    $this->enginesBuilder       = $enginesBuilder;
    $this->configService        = $configService;
    $this->captureEventService  = $captureEventService;
    $this->eventInstanceService = $eventInstanceService;
  }


  /**
   * @param array<string,mixed>|null $requestData
   *
   * @return array<mixed, mixed>
   */
  public function handle( $requestData = null ): array {
    // Flush cache even if no $requestData['engines'] are provided.
    // This is required since the engines GUI is completely loaded from ATE as
    // WPML no longer calls the update endpoint when chaning the engines.
    $this->engineService->flushCache();

    // Proof if the following is triggered by any GUI action at all.
    try {
      if (
        isset( $requestData['engines'] ) &&
        is_array( $requestData['engines'] ) &&
        ! empty( $requestData['engines'] )
      ) {
        $engines = $this->enginesBuilder->build( $requestData['engines'] );
        $this->engineService->update( $engines );
      }
    } catch ( EngineServiceException $e ) {
      return [
        'status'  => false,
        'message' => $e->getMessage(),
      ];
    }

    /**
     * If the engines save fails, we should return an error message and not save the other settings.
     */

    if ( isset( $requestData['reviewMode'] ) && is_string( $requestData['reviewMode'] ) ) {
      $this->settingsService->saveReviewOption( $requestData['reviewMode'] );
    }

    if ( isset( $requestData['shouldTranslateAutomaticallyDrafts'] ) ) {
      $this->settingsRepository->saveShouldTranslateAutomaticallyDrafts(
        (bool) $requestData['shouldTranslateAutomaticallyDrafts']
      );
    }

    // Capture PostHog event after successful save
    $this->captureAutomaticTranslationSettingsSaved( $requestData );

    return [
      'status' => true,
    ];
  }


  /**
   * Capture PostHog event for automatic translation settings saved
   *
   * @param array<string,mixed>|null $requestData
   *
   * @return void
   */
  private function captureAutomaticTranslationSettingsSaved( $requestData ) {
    if ( ! $requestData ) {
      return;
    }

    try {

      $config = $this->configService->create();

      $isPtcEngineSelected = isset( $requestData['isPtcEngineSelected'] ) && $requestData['isPtcEngineSelected'];

      $eventProperties = $this->buildEventProperties( $requestData, $isPtcEngineSelected );

      $eventInstance = $this
          ->eventInstanceService
          ->getAutomaticTranslationSettingsSavedEvent( $eventProperties );

      $this->captureEventService->capture(
        $config,
        $eventInstance
      );
      // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Silently fail - don't break the save flow
    } catch ( Exception $e ) {
    }
  }


  /**
   * Build event properties for automatic translation settings
   *
   * @param array<string,mixed> $requestData
   * @param bool $isPtcEngineSelected
   *
   * @return array<string,mixed>
   */
  private function buildEventProperties( $requestData, $isPtcEngineSelected ) {
    // Get current settings to fill in missing values
    $currentSettings = $this->settingsRepository->getSettings();

    // Get review mode: use request value if present, otherwise get current value
    $reviewMode = null;
    if ( isset( $requestData['reviewMode'] ) ) {
      $reviewMode = $requestData['reviewMode'];
    } else {
      $currentReviewMode = $currentSettings->getReviewMode();
      $reviewMode        = $currentReviewMode ? $currentReviewMode->getValue() : null;
    }

    // Get translate drafts: use request value if present, otherwise get current value
    $translateDrafts = null;
    if ( isset( $requestData['shouldTranslateAutomaticallyDrafts'] ) ) {
      $translateDrafts = (bool) $requestData['shouldTranslateAutomaticallyDrafts'];
    } else {
      $translateDrafts = $this->settingsRepository->shouldTranslateAutomaticallyDrafts();
    }

    // Get auto translate when editor opens: use request value if present, otherwise use null
    $autoTranslateWhenEditorOpens = isset( $requestData['autoTranslateWhenEditorOpens'] )
      ? (bool) $requestData['autoTranslateWhenEditorOpens']
      : null;

    $eventProperties = [
      'translation_engine'               => $isPtcEngineSelected ? 'ptc' : 'other',
      'review_mode'                      => $reviewMode,
      'translate_drafts'                 => $translateDrafts,
      'auto_translate_when_editor_opens' => $autoTranslateWhenEditorOpens,
    ];

    // Add context only for PTC engine
    if (
      $isPtcEngineSelected &&
      isset( $requestData['websiteContext'] ) &&
      is_array( $requestData['websiteContext'] )
    ) {
      $websiteContext             = $requestData['websiteContext'];
      $eventProperties['context'] = [
        'site_topic'    => $websiteContext['site_topic'] ?? '',
        'site_purpose'  => $websiteContext['site_purpose'] ?? '',
        'site_audience' => $websiteContext['site_audience'] ?? '',
      ];
    }

    return $eventProperties;
  }


}
