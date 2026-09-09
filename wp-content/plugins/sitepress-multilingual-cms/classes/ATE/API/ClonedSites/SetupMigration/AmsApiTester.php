<?php

namespace WPML\TM\ATE\ClonedSites\SetupMigration;

class AmsApiTester {

	/** @var \WPML_TM_ATE_API */
	private $api;

	/**
	 * @param \WPML_TM_ATE_API $api
	 */
	public function __construct( \WPML_TM_ATE_API $api ) {
		$this->api = $api;
	}

	/**
	 * Checks if the site has been migrated to a new domain by syncing with ATE API.
	 *
	 * @return bool True if 426 error code is detected, false otherwise.
	 */
	public function hasSiteBeenMigratedToNewDomain(): bool {
		$response = $this->api->sync_all( [ 123 ] );

		if ( ! is_wp_error( $response ) ) {
			return false;
		}

		return $response->get_error_code() === 426;
	}
}
