<?php

namespace WPML\TM\ATE\REST;

use WPML\FP\Relation;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\TM\API\ATE;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;
use function WPML\FP\curryN;
use function WPML\FP\pipe;

/**
 * @author OnTheGo Systems
 */
class PublicReceive extends \WPML_TM_ATE_Required_Rest_Base {

	const CODE_LOCKED = 423;
	const CODE_UNPROCESSABLE_ENTITY = 422;
	const CODE_OK = 200;

	const ENDPOINT_JOBS_RECEIVE = '/ate/jobs/receive/';

	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			self::ENDPOINT_JOBS_RECEIVE . '(?P<wpmlJobId>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'receive_ate_job' ),
				'args'                => array(
					'wpmlJobId' => array(
						'required'          => true,
						'type'              => 'int',
						'validate_callback' => array( 'WPML_REST_Arguments_Validation', 'integer' ),
						'sanitize_callback' => array( 'WPML_REST_Arguments_Sanitation', 'integer' ),
					),
				),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [];
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return true|\WP_Error
	 */
	public function receive_ate_job( \WP_REST_Request $request ) {
		$wpmlJobId = $request->get_param( 'wpmlJobId' );

		// Open the JobLog envelope before any work runs. Without this the
		// downstream `applyTranslation` → save_translation chain silently
		// no-ops on every JobLog::add() call, leaving zero trace of the
		// webhook in joblog files. Reusing GROUP_ID_DOWNLOAD_JOBS so polled
		// download and pushed webhook both surface under "translation
		// receive" in the UI.
		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'ATE webhook (PublicReceive)',
			[ 'rid' => $wpmlJobId ]
		);

		try {
			$lock    = make( SyncLock::class );
			$lockKey = $lock->create( 'publicReceive' );
			if ( ! $lockKey ) {
				JobLog::add( 'webhook_lock_busy_423', [ 'rid' => $wpmlJobId ] );
				return new \WP_Error( self::CODE_LOCKED, '', [ 'status' => self::CODE_LOCKED ] );
			}

			JobLog::add( 'webhook_lock_acquired', [ 'rid' => $wpmlJobId ] );

			$skipEditReviewJobs = Logic::complement( Relation::propEq( 'review_status', ReviewStatus::EDITING ) );

			$ateAPI = make( ATE::class );

			$getXLIFF = pipe(
				Obj::prop( 'job_id' ),
				Fns::safe( [ $ateAPI, 'checkJobStatus' ] ),
				Fns::map( Obj::prop( 'translated_xliff' ) )
			);

			$applyTranslations = Fns::converge(
				Fns::liftA3( curryN( 3, [ $ateAPI, 'applyTranslation' ] ) ),
				[
					Fns::safe( Obj::prop( 'job_id' ) ),
					Fns::safe( Obj::prop( 'original_doc_id' ) ),
					$getXLIFF
				]
			);

			$result = Maybe::of( $wpmlJobId )
			            ->map( Jobs::get() )
			            ->filter( $skipEditReviewJobs )
			            ->chain( $applyTranslations )
			            ->map( Fns::always( new \WP_REST_Response( null, self::CODE_OK ) ) )
			            ->getOrElse( new \WP_Error( self::CODE_UNPROCESSABLE_ENTITY, '', [ 'status' => self::CODE_UNPROCESSABLE_ENTITY ] ) );

			$isOk = $result instanceof \WP_REST_Response;
			JobLog::add(
				$isOk ? 'webhook_applied' : 'webhook_unprocessable_422',
				[ 'rid' => $wpmlJobId, 'http_status' => $isOk ? self::CODE_OK : self::CODE_UNPROCESSABLE_ENTITY ]
			);

			// Specifically flag the "superseded" failure mode — ATE pushed
			// back a translation for a wpml_job_id that no longer exists
			// in icl_translate_job because a newer job replaced it. This
			// is the credit-runaway signal: the work was paid for but
			// will never be delivered. Cross-reference with the original
			// ate_job_bound event (now carrying trid + target_lang) to
			// reconstruct what was lost.
			if ( ! $isOk ) {
				$wpmlJob = Jobs::get( $wpmlJobId );
				if ( ! $wpmlJob ) {
					JobLog::addError( 'webhook_wpml_job_missing', [
						'wpml_job_id' => (int) $wpmlJobId,
						'reason'      => 'wpml_job_no_longer_in_icl_translate_job',
					] );
				}
			}

			$lock->release();

			return $result;
		} finally {
			JobLog::finishCurrentGroup();
		}
	}

	/**
	 * @param int $wpml_job_id
	 *
	 * @return string
	 */
	public static function get_receive_ate_job_url( $wpml_job_id ) {
		return self::get_url( self::ENDPOINT_JOBS_RECEIVE . $wpml_job_id );
	}
}