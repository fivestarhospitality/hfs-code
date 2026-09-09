<?php

namespace WPML\PostHog\Event;

use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\Capture\CaptureWizardStep;
use WPML\PHP\Exception\RemoteException;

class CaptureWizardStepEvent {

	public static function capture( EventInterface $event, $personProps = [] ) {
		$postHogConfig = ( new ConfigService() )->create();

		global $wpml_dic;

		/** @var CaptureWizardStep $postHogCaptureEvent */
		$postHogCaptureEvent = $wpml_dic->make( CaptureWizardStep::class );

		/** @var CaptureEventService $postHogCaptureEventService */
		$postHogCaptureEventService = $wpml_dic->make( CaptureEventService::class, [
			':captureEvent' => $postHogCaptureEvent,
		] );

		try {
			return $postHogCaptureEventService->capture(
				$postHogConfig,
				$event,
				$personProps
			);
		} catch ( RemoteException $e ) {
			return false;
		}
	}
}
