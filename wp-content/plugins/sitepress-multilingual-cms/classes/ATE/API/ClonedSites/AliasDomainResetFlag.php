<?php

namespace WPML\TM\ATE\ClonedSites;

/**
 * Persistent boolean flag set by `ValidateAliasDomain` when it force-resets a
 * misconfigured WPML alias domain, and read by the auto-migration notice to
 * decide whether to surface the contextual "alias domain was reset" prefix.
 *
 * Centralises the option name + magic value so callers (the upgrade command, the
 * cloned-site Lock, the AutoMigration Handler) don't repeat them.
 */
class AliasDomainResetFlag {

	const OPTION = 'wpml_cloned_site_banner_context';
	const VALUE  = 'alias_domain_reset';

	public static function set() {
		update_option( self::OPTION, self::VALUE, 'no' );
	}

	public static function isSet(): bool {
		return get_option( self::OPTION, '' ) === self::VALUE;
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
