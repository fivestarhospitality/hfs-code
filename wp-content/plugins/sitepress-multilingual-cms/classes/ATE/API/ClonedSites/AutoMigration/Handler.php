<?php

namespace WPML\TM\ATE\ClonedSites\AutoMigration;

use WPML\API\Settings;
use WPML\TM\ATE\API\AmsCredentialsStorage;
use WPML\TM\ATE\API\AmsRequestSigner;
use WPML\TM\ATE\ClonedSites\InProgressJobsCanceller;
use WPML\TM\ATE\ClonedSites\MigrationLogger;
use WPML\TM\ATE\ClonedSites\AliasDomainResetFlag;
use WPML\TM\ATE\ClonedSites\SetupMigration\Resetter\SiteKeyCleaner;
use WPML\TM\ATE\ClonedSites\SetupMigration\Resetter\SiteKeyRegistrar;

use function WPML\Container\make;

class Handler {

	const TRANSIENT_KEY = 'wpml_ate_auto_migration_succeeded';
	const OPTION_MIGRATION_DATA = 'wpml_ate_auto_migration_data';
	const OPTION_MIGRATION_FAILED = 'wpml_ate_auto_migration_failed';

	/** @var AmsRequestSigner */
	private $signer;

	/** @var \WPML_TM_ATE_AMS_Endpoints */
	private $endpoints;

	/** @var AmsCredentialsStorage */
	private $credentialsStorage;

	/** @var \WPML_TM_ATE_Authentication */
	private $auth;

	/** @var SiteKeyCleaner */
	private $siteKeyCleaner;

	/** @var SiteKeyRegistrar */
	private $siteKeyRegistrar;

	/** @var bool */
	private static $processing = false;

	public function __construct(
		AmsRequestSigner $signer,
		\WPML_TM_ATE_AMS_Endpoints $endpoints,
		\WPML_TM_ATE_Authentication $auth,
		AmsCredentialsStorage $credentialsStorage,
		SiteKeyCleaner $siteKeyCleaner,
		SiteKeyRegistrar $siteKeyRegistrar
	) {
		$this->signer             = $signer;
		$this->endpoints          = $endpoints;
		$this->auth               = $auth;
		$this->credentialsStorage = $credentialsStorage;
		$this->siteKeyCleaner     = $siteKeyCleaner;
		$this->siteKeyRegistrar   = $siteKeyRegistrar;
	}

	/**
	 * @param string $oldUrl The domain previously registered in AMS.
	 * @param string $newUrl The current domain that triggered the 426.
	 *
	 * @return bool True if migration succeeded.
	 */
	public function tryMigrate( string $oldUrl = '', string $newUrl = '' ): bool {
		// Callers without URL context (e.g. the Retry endpoint) fall back to whatever we
		// captured on a prior attempt. This keeps the failure UI's "original URL" rendering
		// stable across retries even if the second tryMigrate() invocation is parameter-less.
		if ( ! $oldUrl || ! $newUrl ) {
			$existing = self::getMigrationData();
			if ( is_array( $existing ) ) {
				$oldUrl = $oldUrl ?: ( $existing['old_url'] ?? '' );
				$newUrl = $newUrl ?: ( $existing['new_url'] ?? '' );
			}
		}

		if ( ! $this->ensureInstallerAvailable() ) {
			return $this->fail( $oldUrl, $newUrl );
		}

		// When setup wizard is incomplete, the SetupMigration\ClonedSiteResetter path handles migration instead.
		if ( ! \SitePress_Setup::setup_complete() ) {
			return false;
		}

		if ( self::$processing ) {
			return false;
		}

		if ( $this->alreadyMigratedForCurrentUrls( $oldUrl, $newUrl ) ) {
			return true;
		}

		self::$processing = true;

		MigrationLogger::begin();

		try {
			$result = $this->doMigrate( $oldUrl, $newUrl );

			if ( $result ) {
				delete_option( self::OPTION_MIGRATION_FAILED );
				MigrationLogger::siteUnlocked();
			} else {
				MigrationLogger::migrationFailed();
				$this->fail( $oldUrl, $newUrl );
			}

			return $result;
		} finally {
			MigrationLogger::end();
			self::$processing = false;
		}
	}

	/**
	 * Persist the failure flag, plus the URLs we attempted, so the React error UI can
	 * always render "couldn't connect this site to <oldUrl>" without a defensive fallback.
	 */
	private function fail( string $oldUrl = '', string $newUrl = '' ): bool {
		update_option( self::OPTION_MIGRATION_FAILED, true, false );

		if ( $oldUrl || $newUrl ) {
			update_option( self::OPTION_MIGRATION_DATA, [
				'old_url' => $oldUrl,
				'new_url' => $newUrl,
			], false );
		}

		return false;
	}

	private function doMigrate( string $oldUrl, string $newUrl ): bool {
		$body = $this->callCopyWithAttachment();

		if ( ! $body ) {
			return false;
		}

		if ( ! isset( $body['new_shared_key'], $body['new_secret_key'], $body['new_website_uuid'] ) ) {
			MigrationLogger::copyResponseInvalid( $body );
			return false;
		}

		$stored = $this->credentialsStorage->store( $body );
		MigrationLogger::credentialsStored( $stored );

		if ( ! $stored ) {
			return false;
		}

		if ( ! $this->sendConfirmation() ) {
			MigrationLogger::confirmFailed();
			return false;
		}

		// Lazy resolution via sitepress's container. Constructor injection fails here because wpml/wpml's
		// Dic (separate Auryn instance) cannot auto-wire sitepress classes across the container boundary
		// (regression wpmldev-6711). Resolving on-demand via make() breaks that chain.
		$cancelledCount = make( InProgressJobsCanceller::class )->cancel();
		MigrationLogger::jobsCancelled( (int) $cancelledCount );

		$this->handleSiteKey( $body );

		$organizationName      = $body['billing_group_name'] ?? $oldUrl;
		$organizationConnected = (bool) ( $body['organization_connected'] ?? true );

		// Snapshot the wpmldev-6477 alias-reset flag into the migration data
		// itself — the caller will invoke Lock::unlock() right after this, which
		// clears the flag before the notice can render. Carrying it inside the
		// migration data keeps it available to the React notice for the lifetime
		// of that notice (wpmldev-6885).
		$aliasDomainReset = AliasDomainResetFlag::isSet();

		update_option( self::OPTION_MIGRATION_DATA, [
			'old_url'                => $oldUrl,
			'new_url'                => $newUrl,
			'organization_name'      => $organizationName,
			'organization_connected' => $organizationConnected,
			'alias_domain_reset'     => $aliasDomainReset,
		], false );

		if ( $oldUrl && $newUrl ) {
			Settings::setAndSave( 'migrated_site', [
				'old_url' => $oldUrl,
				'new_url' => $newUrl,
			] );
		}

		set_transient( self::TRANSIENT_KEY, [
			'old_url' => $oldUrl,
			'new_url' => $newUrl,
		], HOUR_IN_SECONDS );

		do_action( 'wpml_tm_ate_synchronize_translators' );

		return true;
	}

	/**
	 * @return array|null Decoded response body on success, null on failure.
	 */
	private function callCopyWithAttachment() {
		$registration_data = get_option( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, [] );

		$url = $this->endpoints->get_ams_copy_attached();

		$params = [
			'shared_key'                  => isset( $registration_data['shared'] ) ? $registration_data['shared'] : '',
			'website_uuid'                => $this->auth->get_site_id(),
			'respect_previous_disconnect' => 'true',
		];

		MigrationLogger::copyRequestSent( $url );

		$response = $this->signer->send( $url, 'POST', $params );

		MigrationLogger::copyResponse( $response );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return null;
		}

		if (
			! isset( $response['response']['code'] )
			|| $response['response']['code'] !== 200
			|| ! isset( $response['body'] )
		) {
			return null;
		}

		$body = json_decode( $response['body'], true );

		if ( ! is_array( $body ) ) {
			return null;
		}

		return $body;
	}

	private function sendConfirmation(): bool {
		$registration_data = get_option( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, [] );

		MigrationLogger::confirmSent( $this->auth->get_site_id() );

		$response = $this->signer->send(
			$this->endpoints->get_ams_site_confirm(),
			'POST',
			[
				'new_shared_key'   => isset( $registration_data['shared'] ) ? $registration_data['shared'] : '',
				'new_website_uuid' => $this->auth->get_site_id(),
			]
		);

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			MigrationLogger::confirmRequestFailed( $response );
			return false;
		}

		if ( ! isset( $response['body'] ) ) {
			MigrationLogger::confirmRequestFailed( $response );
			return false;
		}

		$body = json_decode( $response['body'], true );
		$confirmed = is_array( $body ) && ! empty( $body['confirmed'] );

		MigrationLogger::confirmResponse( $confirmed );

		return $confirmed;
	}

	private function handleSiteKey( array $body ) {
		$siteKey = isset( $body['site_key'] ) ? $body['site_key'] : null;

		if ( ! $siteKey || ! $this->siteKeyRegistrar->register( $siteKey ) ) {
			$this->siteKeyCleaner->unregister();
		}
	}

	private function ensureInstallerAvailable(): bool {
		if ( function_exists( 'OTGS_Installer' ) ) {
			return true;
		}

		if ( function_exists( 'wpml_installer_force_load' ) ) {
			wpml_installer_force_load();
		}

		return function_exists( 'OTGS_Installer' );
	}

	public static function hasMigrated(): bool {
		return (bool) get_transient( self::TRANSIENT_KEY );
	}

	/**
	 * @return array|null Array with 'old_url', 'new_url', 'organization_name', or null.
	 */
	public static function getMigrationData() {
		$data = get_option( self::OPTION_MIGRATION_DATA, null );

		return is_array( $data ) ? $data : null;
	}

	public static function clearMigrationFlag() {
		delete_transient( self::TRANSIENT_KEY );
	}

	public static function clearMigrationData() {
		delete_option( self::OPTION_MIGRATION_DATA );
	}

	public static function setOrganizationConnected( bool $connected ) {
		$data = self::getMigrationData();
		if ( ! is_array( $data ) ) {
			return;
		}
		$data['organization_connected'] = $connected;
		update_option( self::OPTION_MIGRATION_DATA, $data, false );
	}

	public static function hasSitekey(): bool {
		if ( ! function_exists( 'OTGS_Installer' ) ) {
			return false;
		}
		$installer = \OTGS_Installer();
		if ( ! $installer ) {
			return false;
		}
		return (bool) $installer->get_site_key( 'wpml' );
	}

	public static function hasFailed(): bool {
		return (bool) get_option( self::OPTION_MIGRATION_FAILED, false );
	}

	/**
	 * @param array|null $migrationData Optional override; defaults to current persisted data.
	 *
	 * @return string One of 'error', 'success', 'success-independent'.
	 */
	public static function resolveInitialState( $migrationData = null ): string {
		// Failure flag wins: a failed attempt may have stored URLs in MIGRATION_DATA so the
		// React error UI can show them, but the dispatcher must still pick the error flow.
		if ( self::hasFailed() ) {
			return 'error';
		}

		if ( $migrationData === null ) {
			$migrationData = self::getMigrationData();
		}

		if ( ! is_array( $migrationData ) || empty( $migrationData ) ) {
			return 'error';
		}

		$connected = isset( $migrationData['organization_connected'] )
			? (bool) $migrationData['organization_connected']
			: true;

		return $connected ? 'success' : 'success-independent';
	}

	public static function clearFailureFlag() {
		delete_option( self::OPTION_MIGRATION_FAILED );
	}

	private function alreadyMigratedForCurrentUrls( string $oldUrl, string $newUrl ): bool {
		$cached = get_transient( self::TRANSIENT_KEY );

		return is_array( $cached )
			&& isset( $cached['old_url'], $cached['new_url'] )
			&& $cached['old_url'] === $oldUrl
			&& $cached['new_url'] === $newUrl;
	}
}
