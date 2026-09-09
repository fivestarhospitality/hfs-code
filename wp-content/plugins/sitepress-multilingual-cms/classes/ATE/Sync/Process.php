<?php

namespace WPML\TM\ATE\Sync;

use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Download\Job;
use WPML\TM\ATE\Log\EventsTypes;
use WPML_TM_ATE_API;
use WPML_TM_ATE_Job_Repository;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\Entry;
use WPML\Translation\TranslateJobErrorServiceFactory;
use function WPML\FP\pipe;
use WPML\TM\Jobs\JobLog;

class Process {

	const LOCK_RELEASE_TIMEOUT = 1 * MINUTE_IN_SECONDS;

	/** @var WPML_TM_ATE_API $api */
	private $api;

	/** @var WPML_TM_ATE_Job_Repository $ateRepository */
	private $ateRepository;

	public function __construct( WPML_TM_ATE_API $api, WPML_TM_ATE_Job_Repository $ateRepository ) {
		$this->api           = $api;
		$this->ateRepository = $ateRepository;
	}

	/**
	 * @param Arguments $args
	 *
	 * @return Result
	 */
	public function run( Arguments $args ) {
		$result          = new Result();

		if ( $args->page ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup(
				JobLog::GROUP_ID_SYNC_JOBS,
				'Sending jobs to sync - run sync on pages',
				[
					'args' => get_object_vars( $args ),
				]
			);
			$result = $this->runSyncOnPages( $result, $args );
			JobLog::add(
				'Sync result',
				[
					'result' => get_object_vars( $result ),
				]
			);
			JobLog::finishCurrentGroup();
		} else {
			$includeManualAndLongstandingJobs  = (bool) Obj::propOr( true , 'includeManualAndLongstandingJobs', $args);
			$result = $this->runSyncInit( $result, $includeManualAndLongstandingJobs );
		}

		return $result;
	}

	/**
	 * This will run the sync on extra pages.
	 *
	 * @param Result $result
	 * @param Arguments $args
	 *
	 * @return Result
	 */
	private function runSyncOnPages( Result $result, Arguments $args ) {
		$apiPage = $args->page - 1; // ATE API pagination starts at 0.
		$data    = $this->api->sync_page( $args->ateToken, $apiPage );

		$jobs         = Obj::propOr( [], 'items', $data );
		$result->jobs = $this->handleJobs( $jobs );

		if ( !$result->jobs ){
			$log = Entry::createForType(
				EventsTypes::JOBS_SYNC,
				[
					'numberOfPages'     => $args->numberOfPages,
					'page'              => $args->page,
					'downloadQueueSize' => $result->downloadQueueSize,
					'nextPage'          => $result->nextPage,
				]
			);

			JobLog::add(
				'No jobs in sync results',
				$log
			);
			Storage::add( $log );
		}

		if ( isset( $data->eta ) && ( ! isset( $data->eta->available ) || false !== $data->eta->available ) ) {
			$result->eta            = $data->eta;
			$result->eta->available = true;
		}

		if ( $args->numberOfPages > $args->page ) {
			$result->nextPage      = $args->page + 1;
			$result->numberOfPages = $args->numberOfPages;
			$result->ateToken      = $args->ateToken;
		}

		return $result;
	}

	/**
	 * This will run the first sync iteration.
	 * We send all the job IDs we want to sync.
	 *
	 * @param Result $result
	 * @param boolean $includeManualAndLongstandingJobs
	 *
	 * @return Result
	 */
	private function runSyncInit( Result $result, $includeManualAndLongstandingJobs = true ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property name
		$jobsData   = $this->ateRepository->get_jobs_to_sync_with_element_ids( $includeManualAndLongstandingJobs );
		$ateJobIds  = $jobsData['ateJobIds'];
		$postIds    = $jobsData['postIds'];
		$stringIds  = $jobsData['stringIds'];
		$packageIds = $jobsData['packageIds'];

		if ( $ateJobIds ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup(
				JobLog::GROUP_ID_SYNC_JOBS,
				'Sending jobs to sync - sync init',
				[
					'ateJobIds'                        => $ateJobIds,
					'includeManualAndLongstandingJobs' => $includeManualAndLongstandingJobs,
				]
			);
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property name
			$this->ateRepository->increment_ate_sync_count( $ateJobIds );
			$data = $this->api->sync_all( $ateJobIds, $postIds, $stringIds, $packageIds );

			$jobs         = Obj::propOr( [], 'items', $data );
			$result->jobs = $this->handleJobs( $jobs );
			if ( isset( $data->eta ) && ( ! isset( $data->eta->available ) || false !== $data->eta->available ) ) {
				$result->eta            = $data->eta;
				$result->eta->available = true;
			}

			if ( isset( $data->next->pagination_token, $data->next->pages_number ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property names
				$result->ateToken = $data->next->pagination_token;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property name
				$result->numberOfPages = $data->next->pages_number;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property name
				$result->nextPage = 1; // We start pagination at 1 to avoid carrying a falsy value.
			}

			JobLog::add(
				'Sync results',
				[
					'result' => get_object_vars( $result ),
				]
			);
			JobLog::finishCurrentGroup();
		}

		return $result;
	}

	/**
	 * @param boolean $includeManualAndLongstandingJobs
	 *
	 * @return array
	 */
	private function getAteJobIdsToSync( $includeManualAndLongstandingJobs = true ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- existing property name
		return $this->ateRepository
			->get_jobs_to_sync( $includeManualAndLongstandingJobs )
			->map_to_property( 'editor_job_id' );
	}

	/**
	 * @param array $items
	 *
	 * @return Job[] $items
	 */
	private function handleJobs( array $items ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return wpml_collect( $items )
			->map( [ Job::class, 'fromAteResponse' ] )
			->map( Obj::over( Obj::lensProp( 'jobId' ), Map::fromRid() ) ) // wpmlJobId returned by ATE endpoint represents RID column in wp_icl_translation_status.
			->map(
				function ( $job ) {
					if ( $job->isUnsolvable ) {
						$jobData                = Jobs::get( $job->jobId );
						$job->elementId         = $jobData->element_id ?? null;
						$job->originalElementId = $jobData->original_doc_id ?? null;
						unset( $jobData->elements );
						$job->errorData = $jobData;
					}
					return $job;
				}
			)
			->each(
				function ( $job ) {
					if ( $job->isUnsolvable ) {
						$this->logUnsolvableJob( $job );
					}
				}
			)
			->toArray();
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Log unsolvable job error to the database.
	 *
	 * @param Job $job
	 *
	 * @return void
	 */
	private function logUnsolvableJob( $job ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$service = TranslateJobErrorServiceFactory::create();

		$service->logError(
			$job->jobId,
			$job->ateJobId,
			'SyncError',
			$job->message ?: 'Job marked as unsolvable by ATE',
			[
				'ateStatus' => $job->ateStatus,
				'jobData'   => $job->errorData,
			]
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
