<?php

namespace WPML\Translation;

use WPML\Core\Component\Translation\Application\Service\CancelJobsService;

class CancelJobsServiceFactory {
	/** @var CancelJobsService|null */
	private static $instance = null;

	public static function create(): CancelJobsService {
		if ( self::$instance === null ) {
			self::$instance = self::createNewInstance();
		}

		return self::$instance;
	}

	/**
	 * @param CancelJobsService $instance
	 *
	 * @return void
	 */
	public static function setService( CancelJobsService $instance ) {
		self::$instance = $instance;
	}

	private static function createNewInstance(): CancelJobsService {
		global $wpml_dic;

		return $wpml_dic->make( CancelJobsService::class );
	}
}
