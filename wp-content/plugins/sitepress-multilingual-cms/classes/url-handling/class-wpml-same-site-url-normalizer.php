<?php

/**
 * Helpers for treating www and non-www variants of the same site as identical.
 *
 * Provides single-URL normalization (normalize_url), same-site detection (is_same_site),
 * and home-URL detection (is_home_url) used by AbsoluteLinks and Pro Translation.
 *
 * @see wpmlvip-21, wpmldev-5726, wpmldev-6201
 */
class WPML_Same_Site_Url_Normalizer {

	/** @var string|null */
	private static $cached_home_url = null;

	/**
	 * Return canonical home URL, cached per request. Auto-invalidates when the 'home' option changes.
	 *
	 * Reads from the `home` option directly to bypass WPML's `home_url` filter, which can rewrite
	 * the host per-language and break www/non-www same-site comparisons.
	 *
	 * @return string
	 */
	private static function get_home_url_cached() {
		if ( self::$cached_home_url === null ) {
			$home                  = get_option( 'home' );
			self::$cached_home_url = ( is_string( $home ) && $home !== '' ) ? $home : get_home_url();
			add_action( 'update_option_home', [ __CLASS__, 'invalidate_home_url_cache' ] );
		}

		return self::$cached_home_url;
	}

	/**
	 * Reset the cached home URL. Used in tests when the 'home' option changes.
	 */
	public static function reset_cached_home_url() {
		self::$cached_home_url = null;
	}

	/**
	 * Called automatically when the 'home' option is updated.
	 */
	public static function invalidate_home_url_cache() {
		self::$cached_home_url = null;
	}

	/**
	 * Normalize domain for comparison: strip scheme and optional leading www.
	 *
	 * @param string $url Full URL or host (e.g. "https://www.example.com/" or "www.example.com")
	 * @return string Domain without scheme and without www (e.g. "example.com")
	 */
	public static function get_normalized_domain( $url ) {
		$without_scheme = preg_replace( '/^https?:\/\//', '', $url );
		$without_scheme = rtrim( $without_scheme, '/' );
		$host           = wp_parse_url( 'http://' . $without_scheme, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			$host = $without_scheme;
		}
		return preg_replace( '/^www\./i', '', $host );
	}

	/**
	 * Whether the URL belongs to the same site as the canonical `home` URL (by normalized domain).
	 *
	 * @param string $url Full URL.
	 * @return bool
	 */
	public static function is_same_site( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return false;
		}
		if ( preg_match( '/^https?:\/\//', $url ) !== 1 ) {
			return false;
		}
		return self::get_normalized_domain( self::get_home_url_cached() ) === self::get_normalized_domain( $url );
	}

	/**
	 * Whether the URL is the home URL (same site and path equals home path; supports subdirectory installs).
	 *
	 * @param string $url Full URL.
	 * @return bool
	 */
	public static function is_home_url( $url ) {
		if ( ! self::is_same_site( $url ) ) {
			return false;
		}
		$home_url  = self::get_home_url_cached();
		$home_path = trim( (string) wp_parse_url( $home_url, PHP_URL_PATH ), '/' );
		$parsed    = wp_parse_url( $url );
		$url_path  = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
		return $url_path === $home_path;
	}

	/**
	 * Build a regex fragment that matches a domain with or without a leading www. prefix.
	 *
	 * Used by AbsoluteLinks when building link-matching patterns, so the regex natively
	 * handles both www and non-www variants without modifying the source content.
	 *
	 * @param string $domain    Domain without scheme, e.g. "www.example.com" or "example.com/fr".
	 * @param string $delimiter Regex delimiter passed to preg_quote (default '@').
	 *
	 * @return string Regex fragment, e.g. "(?:www\.)?example\.com".
	 */
	public static function get_domain_regex_pattern( $domain, $delimiter = '@' ) {
		$bare_domain = preg_replace( '/^www\./i', '', $domain );

		return '(?:www\.)?' . preg_quote( $bare_domain, $delimiter );
	}

	/**
	 * If URL is same-site, return it with the canonical host (from the `home` option). Otherwise return unchanged.
	 *
	 * @param string $url Full URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return $url;
		}
		if ( ! self::is_same_site( $url ) ) {
			return $url;
		}
		$home_parsed   = wp_parse_url( self::get_home_url_cached() );
		$canonical_host = isset( $home_parsed['host'] ) ? $home_parsed['host'] : '';
		if ( $canonical_host === '' ) {
			return $url;
		}
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			return $url;
		}
		$current_host = $parsed['host'];
		if ( strtolower( $current_host ) === strtolower( $canonical_host ) ) {
			return $url;
		}
		// Replace the host in the URL with the canonical host (case-sensitive as in the `home` option).
		return preg_replace(
			'/^([^:]+:\/\/)([^\/?#]+)/i',
			'$1' . $canonical_host,
			$url,
			1
		);
	}

}