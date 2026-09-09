<?php

namespace WPML\ATE\Proxies;

use WPML_TM_ATE_AMS_Endpoints;

/**
 * Class ProxyRoutingRules
 *
 * Defines routing rules for WPML's proxy interceptor, including which domains
 * should be proxied and which specific HTTP requests should bypass the proxy.
 *
 * @package WPML\ATE\Proxies
 */
class ProxyRoutingRules {

	/**
	 * Get the list of domains that should be routed through the proxy.
	 *
	 * @return array List of allowed host patterns (e.g., domain names or wildcard patterns).
	 */
	public static function getAllowedDomains() {
		$ateEndpoints = new WPML_TM_ATE_AMS_Endpoints();

		return [
			$ateEndpoints->get_ATE_host(),
			$ateEndpoints->get_AMS_host(),
		];
	}

	/**
	 * Get the list of HTTP requests that should bypass the proxy.
	 *
	 * These requests will be sent directly to their destination instead of being
	 * routed through the proxy, even if their domain is in the allowed list.
	 *
	 * @return string[]
	 */
	public function getBypassedHttpRequests() {
		$ateEndpoints = new WPML_TM_ATE_AMS_Endpoints();
		return [
			// we need to bypass this request from the proxy because we call it directly to see if browser client is able to connect to AMS.
			$ateEndpoints->get_AMS_base_url() . '/api/wpml',
		];
	}
}
