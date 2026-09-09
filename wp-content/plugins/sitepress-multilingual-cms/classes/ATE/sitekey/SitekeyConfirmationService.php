<?php

namespace WPML\TM\ATE\Sitekey;

/**
 * Service for confirming site key with AMS.
 * Handles the site key confirmation logic and flag management.
 */
class SitekeyConfirmationService {

	/** @var SitekeyProvider */
	private $sitekeyProvider;

	/** @var SitekeyApiClient */
	private $sitekeyApiClient;

	/**
	 * @param SitekeyProvider  $sitekeyProvider
	 * @param SitekeyApiClient $sitekeyApiClient
	 */
	public function __construct(
		SitekeyProvider $sitekeyProvider,
		SitekeyApiClient $sitekeyApiClient
	) {
		$this->sitekeyProvider  = $sitekeyProvider;
		$this->sitekeyApiClient = $sitekeyApiClient;
	}

	public function confirm(): bool {
		if ( ! $this->sitekeyProvider->hasSitekey() ) {
			return false;
		}

		$success = $this->sitekeyApiClient->sendSitekey();

		if ( $success ) {
			SitekeyConfirmationFlag::markAsCompleted();
		}

		return $success;
	}
}
