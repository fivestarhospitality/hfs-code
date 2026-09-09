<?php

namespace WPML\Translation;

use WPML\Core\Component\Translation\Application\Service\TranslateJobErrorService;

class TranslateJobErrorServiceFactory {
	/** @var TranslateJobErrorService|null */
	private static $instance = null;

	/**
	 * @return TranslateJobErrorService
	 */
	public static function create(): TranslateJobErrorService {
		// Check if the instance is already created.
		if ( null === self::$instance ) {
			self::$instance = self::createNewInstance();
		}

		// Return the cached instance.
		return self::$instance;
	}

	/**
	 * Set a custom instance of TranslateJobErrorService. The main purpose it to mock it in the tests
	 *
	 * @param TranslateJobErrorService|null $instance
	 *
	 * @return void
	 */
	public static function setService( $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Create a new instance of TranslateJobErrorService
	 *
	 * @return TranslateJobErrorService
	 */
	private static function createNewInstance(): TranslateJobErrorService {
		global $wpml_dic;

		return $wpml_dic->make( TranslateJobErrorService::class );
	}
}
