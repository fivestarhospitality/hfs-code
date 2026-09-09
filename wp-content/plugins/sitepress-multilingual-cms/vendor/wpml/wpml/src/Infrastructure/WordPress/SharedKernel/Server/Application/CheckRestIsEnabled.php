<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\Server\Application;

use WPML\Core\SharedKernel\Component\Server\Domain\CacheInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\CheckRestIsEnabledInterface;

/**
 * WordPress implementation of the REST API service interface.
 * This class contains all WordPress-specific code for checking REST API
 * functionality.
 */
class CheckRestIsEnabled implements CheckRestIsEnabledInterface {

  /**
   * @var CacheInterface $cache
   */
  private $cache;

  const CACHE_KEY = 'wpml_rest_api_status';
  const CACHE_TTL = 300;


  public function __construct( CacheInterface $cache ) {
    $this->cache = $cache;
  }


  public function isEnabled( bool $useCache = false ): bool {
    if ( $useCache ) {
      $cached = $this->cache->get( self::CACHE_KEY );
      if ( is_bool( $cached ) ) {
        return $cached;
      }
    }

    $result = $this->checkRestApiAvailability();
    $this->cache->set( self::CACHE_KEY, $result, self::CACHE_TTL );

    return $result;
  }


  /**
   * Tests if a REST API endpoint is accessible and returns valid data
   *
   * @param string $endpoint The endpoint URL to test
   *
   * @return bool True if the endpoint is accessible and returns valid data
   */
  private function testEndpoint( string $endpoint ): bool {
    $args = [
      'timeout'     => 20,
      'redirection' => 5,
      'sslverify'   => false,
      'headers'     => [
        'Accept'     => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
                        '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      ],
      'cookies'     => $this->getCookiesWithoutSessionId(),
    ];

    // Add basic authentication if present
    if ( isset( $_SERVER['PHP_AUTH_USER'] )
         && isset( $_SERVER['PHP_AUTH_PW'] )
    ) {
      $args['headers']['Authorization'] = 'Basic '
                                          . base64_encode(
                                            $_SERVER['PHP_AUTH_USER']
                                            . ':'
                                            . $_SERVER['PHP_AUTH_PW']
                                          );
    }

    // Add a cache buster to avoid any caching issues
    $endpoint = add_query_arg( 'cachebuster', time(), $endpoint );
    $response = wp_remote_get( $endpoint, $args );

    // Check for WP error
    if ( is_wp_error( $response ) ) {
      return false;
    }

    // Check status code
    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code >= 400 ) {
      // If we get a 403, try to bypass WAF by calling REST API internally
      if ( $status_code === 403 ) {
        return $this->testEndpointInternally( $this->getRestRoute(), $this->getRestQueryParams() );
      }
      return false;
    }

    return $this->validateResponse( wp_remote_retrieve_body( $response ) );
  }


  /**
   * Validates the REST API response body
   *
   * @param string $body The response body to validate
   *
   * @return bool True if the response is valid
   */
  private function validateResponse( string $body ): bool {
    // Check response body is valid JSON
    if ( empty( $body ) ) {
      return false;
    }
    $response = json_decode( $body, true );

    if ( empty( $response ) || ! is_array( $response ) ) {
      return false;
    }

    if ( ! isset( $response['success'] ) || ! $response['success'] ) {
      return false;
    }

    $payload = $response['data'] ?? null;

    return is_array( $payload )
           && isset( $payload['status'], $payload['get_parameters'] )
           && $payload['status'] === 'valid';
  }


  /**
   * Tests the endpoint internally without making an HTTP request
   * This bypasses any WAF rules that might block external requests
   *
   * @param string $route The REST API route (e.g., '/wpml/v1/rest/status')
   * @param array<string,mixed> $query_params The query parameters to pass to the endpoint
   *
   * @return bool True if successful, false if failed
   */
  private function testEndpointInternally( string $route, array $query_params ) {
    // Create an internal REST request using the provided route and params
    $request = new \WP_REST_Request( 'GET', $route );
    $request->set_query_params( $query_params );

    // Dispatch the request internally
    $server = rest_get_server();
    /** @var \WP_REST_Response|\WP_Error $response */
    $response = $server->dispatch( $request );

    if ( is_wp_error( $response ) ) {
      return false;
    }

    $status_code = $response->get_status();
    if ( $status_code >= 400 ) {
      return false;
    }

    $data = $response->get_data();

    // Validate the response structure
    if ( empty( $data ) || ! is_array( $data ) ) {
      return false;
    }

    if ( ! isset( $data['success'] ) || ! $data['success'] ) {
      return false;
    }

    $payload = $data['data'] ?? false;

    return is_array( $payload )
           && isset( $payload['status'], $payload['get_parameters'] )
           && $payload['status'] === 'valid';
  }


  /**
   * Get cookies without session ID for REST API requests
   *
   * @return array<string,string>
   */
  private function getCookiesWithoutSessionId() {
    return array_diff_key( $_COOKIE, [ 'PHPSESSID' => '' ] );
  }


  /**
   * Get the REST API route (without the full URL)
   *
   * @return string
   */
  private function getRestRoute(): string {
    return '/wpml/v1/rest/status';
  }


  /**
   * Get the query parameters for the REST API request
   *
   * @return array<string,mixed>
   */
  private function getRestQueryParams(): array {
    return [
      'test_get_parameter' => true,
      'cachebuster'        => time(),
    ];
  }


  public function getEndpoint(): string {
    $endpoint = get_rest_url( null, ltrim( $this->getRestRoute(), '/' ) );

    // Add query parameters
    foreach ( $this->getRestQueryParams() as $key => $value ) {
      $endpoint = add_query_arg( $key, $value, $endpoint );
    }

    return $endpoint;
  }


  /**
   * Checks if the REST API is available and functional
   *
   * @return bool True if REST API is available and working
   */
  private function checkRestApiAvailability(): bool {
    // 1) Is the REST server class even available?
    if ( ! class_exists( 'WP_REST_Server' ) ) {
      return false;
    }

    // 2) Has someone explicitly disabled the REST API?
    /** @var bool $rest_enabled */
    $rest_enabled = apply_filters( 'rest_enabled', true );
    if ( ! $rest_enabled ) {
      return false;
    }

    // 3) Are there any routes registered?
    $server = rest_get_server();
    $routes = $server->get_routes();

    if ( empty( $routes ) ) {
      return false;
    }

    $result = $this->testEndpoint( $this->getEndpoint() );
    if ( ! $result ) {
      //try again to avoid false positives warning
      $result = $this->testEndpoint( $this->getEndpoint() );
    }

    return $result;
  }


}
