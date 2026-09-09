<?php

namespace WPML\TM\ATE\ClonedSites;

class AliasDomainProber {

	const TIMEOUT_SECONDS = 10;

	/**
	 * Sends a token to {$aliasUrl}/?wpml_alias_domain_check=TOKEN
	 * and confirms the HTTP round-trip returned 200 with the expected body.
	 *
	 * This only proves the URL is reachable and serves a WPML install — to also
	 * confirm it is the same physical install (shared DB), call isSameDatabase()
	 * with the same token immediately after.
	 *
	 * @param string $aliasUrl
	 * @param string $token
	 *
	 * @return bool
	 */
	public function probe( $aliasUrl, $token ) {
		$url = add_query_arg(
			AliasDomainCheckHandler::GET_PARAM,
			$token,
			trailingslashit( $aliasUrl )
		);

		$response = wp_remote_get( $url, [
			'timeout'   => self::TIMEOUT_SECONDS,
			'sslverify' => false,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return $code === 200
			&& strpos( $body, AliasDomainCheckHandler::RESPONSE_BODY ) !== false;
	}

	/**
	 * Reads and deletes the token stored locally by AliasDomainCheckHandler.
	 * Returns true iff the stored token matches the expected one — proving the
	 * probed URL hit the same WordPress installation (same DB).
	 *
	 * Only meaningful after a successful probe() call with the same token.
	 *
	 * @param string $expectedToken
	 *
	 * @return bool
	 */
	public function isSameDatabase( $expectedToken ) {
		return AliasDomainCheckHandler::getAndDeleteToken() === $expectedToken;
	}

	/**
	 * One-shot helper: generates a token, probes the URL, and verifies the
	 * response landed in the same database. Returns true only if both succeed.
	 *
	 * Use this from callers that don't need to manage retry state or token reuse.
	 *
	 * @param string $aliasUrl
	 *
	 * @return bool
	 */
	public function verify( $aliasUrl ) {
		$token = wp_generate_password( 32, false );

		if ( ! $this->probe( $aliasUrl, $token ) ) {
			return false;
		}

		return $this->isSameDatabase( $token );
	}
}
