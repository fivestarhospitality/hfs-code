<?php

namespace WPML\TM\ATE\Sitekey;

use function WPML\Container\make;

/**
 * Handles communication with AMS for site key synchronization.
 * Provides a single point of responsibility for sending site keys to AMS.
 */
class SitekeyApiClient {

	/** @var SitekeyProvider */
	private $sitekeyProvider;

	/** @var SitekeyLogger */
	private $logger;

	/**
	 * @param SitekeyProvider $sitekeyProvider
	 * @param SitekeyLogger $logger
	 */
	public function __construct( SitekeyProvider $sitekeyProvider, SitekeyLogger $logger ) {
		$this->sitekeyProvider = $sitekeyProvider;
		$this->logger = $logger;
	}

	/**
	 * Send site key to AMS.
	 *
	 * @return bool True if successful, false otherwise
	 */
	public function sendSitekey() {
		$sitekey = $this->sitekeyProvider->getSitekey();

		if ( ! $sitekey ) {
			$this->logger->logError( 'Site key is empty' );
			return false;
		}

		return $this->sendToAMS( $sitekey );
	}

	/**
	 * Send site key to AMS API.
	 *
	 * @param string $sitekey
	 * @return bool
	 */
	private function sendToAMS( $sitekey ) {
		try {
			$result = make( \WPML_TM_AMS_API::class )->send_sitekey( $sitekey );

			if ( ! $result ) {
				$this->logger->logError( 'AMS API returned false' );
			}

			return (bool) $result;
		} catch ( \Exception $e ) {
			$this->logger->logError( $e->getMessage() );
			return false;
		}
	}

}
