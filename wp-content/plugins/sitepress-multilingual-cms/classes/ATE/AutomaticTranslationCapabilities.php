<?php

namespace WPML\TM\ATE;

use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use function WPML\FP\curryN;

/**
 * Encapsulates automatic translation capability checks.
 *
 * This class provides a safe abstraction over LanguageMappings methods
 * by always checking if ATE is enabled before making any API calls.
 * When ATE is disabled, methods return sensible defaults (false, empty arrays).
 *
 * Use this class instead of calling LanguageMappings/CachedLanguageMappings directly
 * to avoid unnecessary ATE API calls when Classic Translation Editor is enabled.
 */
class AutomaticTranslationCapabilities {

	/**
	 * @return bool
	 */
	public static function isAvailable() {
		return \WPML_TM_ATE_Status::is_enabled_and_activated();
	}

	/**
	 * @param string $languageCode
	 *
	 * @return bool
	 */
	public static function isLanguageEligible( $languageCode ) {
		if ( ! self::isAvailable() ) {
			return false;
		}

		return CachedLanguageMappings::isCodeEligibleForAutomaticTranslations( $languageCode );
	}

	/**
	 * @param array|null  $languages
	 * @param string|null $sourceLang
	 *
	 * @return array|callable
	 */
	public static function withCapabilityInfo( $languages = null, $sourceLang = null ) {
		$fn = curryN( 1, function ( $languages, $sourceLang = null ) {
			if ( ! self::isAvailable() ) {
				return Fns::map(
					Obj::addProp( 'can_be_translated_automatically', Fns::always( false ) ),
					$languages
				);
			}

			return CachedLanguageMappings::withCanBeTranslatedAutomatically( $languages, $sourceLang );
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	/**
	 * @return bool
	 */
	public static function shouldTranslateEverything() {
		return self::isAvailable() && Option::shouldTranslateEverything();
	}

	/**
	 * @return bool
	 */
	public static function doesDefaultLanguageSupport() {
		if ( ! self::isAvailable() ) {
			return false;
		}

		return CachedLanguageMappings::doesDefaultLanguageSupportAutomaticTranslations();
	}
}
