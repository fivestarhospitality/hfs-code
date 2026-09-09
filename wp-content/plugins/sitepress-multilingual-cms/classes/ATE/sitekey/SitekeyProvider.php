<?php

namespace WPML\TM\ATE\Sitekey;

/**
 * Provides access to the WPML site key.
 * Encapsulates the logic for retrieving the site key from OTGS Installer.
 */
class SitekeyProvider {

	public function hasSitekey(): bool {
		return ! empty( $this->getSitekey() );
	}

	/**
	 * Get WPML site key from installer.
	 *
	 * @return string|null
	 */
	public function getSitekey() {
		if ( ! $this->isInstallerAvailable() ) {
			return null;
		}

		return \OTGS_Installer()->get_site_key( 'wpml' );
	}

	/**
	 * Check if OTGS Installer is available.
	 *
	 * @return bool
	 */
	private function isInstallerAvailable() {
		return function_exists( 'OTGS_Installer' );
	}
}
