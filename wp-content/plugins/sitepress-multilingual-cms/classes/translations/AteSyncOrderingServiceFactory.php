<?php

namespace WPML\Translation;

use WPML\Core\Component\Translation\Application\Service\Priority\AteSyncOrderingService;

class AteSyncOrderingServiceFactory {
	/** @var AteSyncOrderingService|null */
	private static $instance = null;

	/**
	 * @return AteSyncOrderingService
	 */
	public static function create(): AteSyncOrderingService {
		if ( null === self::$instance ) {
			self::$instance = self::createNewInstance();
		}

		return self::$instance;
	}

	/**
	 * Set a custom instance of AteSyncOrderingService. The main purpose is to mock it in the tests.
	 *
	 * @param AteSyncOrderingService|null $instance
	 *
	 * @return void
	 */
	public static function setService( $instance ) {
		self::$instance = $instance;
	}

	/**
	 * @return AteSyncOrderingService
	 */
	private static function createNewInstance(): AteSyncOrderingService {
		global $wpml_dic;

		return $wpml_dic->make( AteSyncOrderingService::class );
	}
}
