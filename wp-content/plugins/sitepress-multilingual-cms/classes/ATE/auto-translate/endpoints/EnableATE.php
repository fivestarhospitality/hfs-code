<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\API\Settings;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use function WPML\Container\make;
use function WPML\FP\pipe;

class EnableATE implements IHandler {


	public function run( Collection $data ) {
		Settings::assoc( 'translation-management', 'doc_translation_method', ICL_TM_TMETHOD_ATE );

		$cache = wpml_get_cache( \WPML_Translation_Roles_Records::CACHE_GROUP );
		$cache->flush_group_cache();

		/** @var \WPML_TM_AMS_API $ateApi */
		$ateApi = make( \WPML_TM_AMS_API::class );
		$status = $ateApi->get_status();
		if ( Obj::propOr( false, 'activated', $status ) ) {
			$result = Either::right( true );
		} else {
			/** @var \WPML_TM_AMS_Users $amsUsers */
			$amsUsers = make( \WPML_TM_AMS_Users::class );

			/** @var \WPML_TM_AMS_API $amsApi */
			$amsApi = make( \WPML_TM_AMS_API::class );

			$saveLanguageMapping = Fns::tap( pipe(
				[ Option::class, 'getLanguageMappings' ],
				Logic::ifElse( Logic::isEmpty(), Fns::always( true ), [ LanguageMappings::class, 'saveMapping'] )
			) );

			$result = $amsApi->register_manager(
				User::getCurrent(),
				$amsUsers->get_translators(),
				$amsUsers->get_managers()
			)->map( $saveLanguageMapping );

			$ateApi->get_status(); // Required to get the active status and store it.
		}

		return $result->map( Fns::tap( [ make( \WPML_TM_AMS_Synchronize_Actions::class ), 'synchronize_translators' ] ) )
									->map( $this->confirmSiteKey() )
		              ->bimap(
		              	$this->formatError(),
		              	Fns::identity()
		              );
	}

	/**
	 * Confirm site key with AMS immediately after enabling ATE.
	 * If confirmation fails, the Sync class will schedule a background task as fallback.
	 */
	private function confirmSiteKey() {
		return Fns::tap(function() {
			$confirmationService = make( \WPML\TM\ATE\Sitekey\SitekeyConfirmationService::class );
			$confirmationService->confirm();
			// Don't care about return value - Sync will handle fallback if confirmation failed
		});
	}

	/**
	 * Format error data to include both user-friendly message and raw response
	 *
	 * @return callable Function that transforms error structure
	 */
	private function formatError() {
		return function( $errorData ) {
			return [ 'error' => $errorData ];
		};
	}
}
