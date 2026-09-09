<?php

namespace WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\Endpoint\GetWpmlProxyStatus;

use WPML\Core\Component\WpmlProxy\Application\Service\WpmlProxyService;
use WPML\Core\Port\Endpoint\EndpointInterface;

/**
 * GET endpoint for checking WPML Proxy enabled status.
 *
 * Endpoint: GET /wpml/v1/wpml-proxy/status
 */
class GetWpmlProxyStatusController implements EndpointInterface {

  /**
   * @var WpmlProxyService
   */
  private $wpmlProxyService;


  /**
   * @param WpmlProxyService $wpmlProxyService Service for managing WPML Proxy state.
   */
  public function __construct( WpmlProxyService $wpmlProxyService ) {
    $this->wpmlProxyService = $wpmlProxyService;
  }


  /**
   * Handle GET request to check WPML Proxy status.
   *
   * @param array<string,mixed>|null $requestData Request parameters (unused for GET).
   *
   * @return array<string,mixed> Response with WPML Proxy status.
   */
  public function handle( $requestData = null ): array {
    return [
      'success' => true,
      'data'    => [
        'enabled' => $this->wpmlProxyService->isEnabled(),
      ],
    ];
  }


}
