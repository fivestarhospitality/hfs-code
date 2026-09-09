<?php

namespace WPML\TM\ATE\Sitekey;

use WPML\Core\BackgroundTask\Repository\BackgroundTaskRepository;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\LIB\WP\Hooks;
use WPML\WP\OptionManager;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

/**
 * Handles direct site key synchronization with AMS.
 * Attempts immediate API call when site key is updated, with background task fallback on failure.
 */
class DirectSync implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	/** @var BackgroundTaskService */
	private $backgroundTaskService;

	/** @var BackgroundTaskRepository */
	private $backgroundTaskRepository;

	/** @var SitekeyConfirmationService */
	private $confirmationService;

	/** @var SitekeyProvider */
	private $sitekeyProvider;


	public function __construct(
		BackgroundTaskService $backgroundTaskService,
		BackgroundTaskRepository $backgroundTaskRepository,
		SitekeyConfirmationService $confirmationService,
		SitekeyProvider $sitekeyProvider
	) {
		$this->backgroundTaskService    = $backgroundTaskService;
		$this->backgroundTaskRepository = $backgroundTaskRepository;
		$this->confirmationService      = $confirmationService;
		$this->sitekeyProvider  = $sitekeyProvider;
	}

	public function add_hooks() {
		// Hook directly to site key update for immediate sync attempt
		Hooks::onAction( 'otgs_installer_site_key_update' )
			->then( spreadArgs( [ $this, 'handleSiteKeyUpdate' ] ) );
	}

	/**
	 * Handle site key update with direct API call and fallback to background task.
	 *
	 * @param string $repo Repository identifier (should be 'wpml')
	 */
	public function handleSiteKeyUpdate( $repo ) {
		if ( $repo !== 'wpml' ) {
			return;
		}

		if ( ! \WPML_TM_ATE_Status::is_enabled_and_activated() ) {
			return;
		}

		if ( ! $this->sitekeyProvider->hasSitekey() ) {
			return false;
		}

		SitekeyConfirmationFlag::markAsPending();
		$success = $this->confirmationService->confirm();

		if ( $success ) {
			$this->cleanupExistingTasks();
		} else {
			$this->scheduleBackgroundTask();
		}
	}

	/**
	 * Schedule background task as fallback.
	 */
	private function scheduleBackgroundTask() {
		$this->backgroundTaskService->addOnce(
			make( Endpoint::class ),
			wpml_collect( [] )
		);
	}

	/**
	 * Clean up any existing background tasks for this operation.
	 */
	private function cleanupExistingTasks() {
		$task = $this->backgroundTaskRepository->getLastIncompletedByType( Endpoint::class );

		if ( $task ) {
			$this->backgroundTaskService->delete( $task->getTaskId() );
		}
	}
}
