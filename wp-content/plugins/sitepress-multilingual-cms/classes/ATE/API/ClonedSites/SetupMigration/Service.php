<?php

namespace WPML\TM\ATE\ClonedSites\SetupMigration;

use WPML\TM\ATE\ClonedSites\Lock;
use WPML_TM_ATE_Status;

class Service {

	/** @var AmsApiTester */
	private $amsApiTester;

	/** @var ClonedSiteResetter */
	private $clonedSiteResetter;

	/**
	 * @param AmsApiTester       $amsApiTester
	 * @param ClonedSiteResetter $clonedSiteResetter
	 */
	public function __construct(
		AmsApiTester $amsApiTester,
		ClonedSiteResetter $clonedSiteResetter
	) {
		$this->amsApiTester       = $amsApiTester;
		$this->clonedSiteResetter = $clonedSiteResetter;
	}


	/**
	 * Attempts to migrate credentials by syncing with ATE API.
	 * If a 426 error (cloned site) is detected, clears ATE-related options.
	 *
	 * @return string
	 */
	public function maybeMigrateCredentials( string $currentStep ): string {
		if (
			get_option( Lock::CLONED_SITE_OPTION ) ||
			( WPML_TM_ATE_Status::is_enabled_and_activated() && $this->amsApiTester->hasSiteBeenMigratedToNewDomain() )
		) {
			return $this->clonedSiteResetter->reset( $currentStep );
		}

		return $currentStep;
	}
}
