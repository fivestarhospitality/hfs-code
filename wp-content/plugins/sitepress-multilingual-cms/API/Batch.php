<?php

namespace WPML\TM\API;


use WPML\FP\Curryable;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\TM\Jobs\Dispatch\Messages;
use WPML\Translation\CancelJobsServiceFactory;

/**
 * Class Batch
 * @package WPML\TM\API
 *
 * @method static callable|void rollback( ...$batchName ) - Curried :: string->void
 *
 * It rollbacks just sent batch.
 */
class Batch {

	use Curryable;

	public static function init() {

		self::curryN( 'rollback', 1, function ( $basketName ) {
			$batchId = \WPML_Translation_Basket::get_batch_id_from_name( $basketName );

			if ( $batchId ) {
				/**
				 * We have a known bug here, which we don't want to fix now. When the cancellation process is done,
				 * jobs are canceled in the WordPress database, but their counterparts in ATE are not canceled.
				 *
				 * The first problem is in JobActionsFactory. That factory is not executed in Ajax request, where the sending to translation
				 * actually happens.
				 *
				 * However, even if we fix the bug with JobActionsFactory, the process will not work well either.
				 * Even though we make a call to ATE with the proper request, jobs are not canceled on their side.
				 * I suspect this is a timing issue, stemming from the fact that we're trying to cancel a job which was created a second ago.
				 * So perhaps ATE doesn't manage to sync all those jobs yet.
				 *
				 * As we have had this issue for a very long time, we don't want to invest time in fixing it now.
				 */
				$cancelJobsService = CancelJobsServiceFactory::create();
				$cancelJobsService->cancelJobsInBatch( $batchId );
			}

			\TranslationProxy_Basket::set_batch_data( null );
			icl_cache_clear();
		} );


	}

	public static function sendStrings( Messages $messages, $batch ) {
		$dispatchActions = function ( $batch ) {
			do_action( 'wpml_tm_send_st-batch_jobs', $batch, 'st-batch' );
		};

		self::send( $dispatchActions, [ $messages, 'showForStrings' ], $batch );
	}

	private static function send( callable $dispatchAction, callable $displayErrors, $batch ) {
		$dispatchAction( $batch );

		$errors = wpml_load_core_tm()->messages_by_type( 'error' );

		if ( $errors ) {
			self::rollback( $batch->get_basket_name() );

			$displayErrors( Fns::map( Obj::prop( 'text' ), $errors ), 'error' );
		}
	}
}

Batch::init();
