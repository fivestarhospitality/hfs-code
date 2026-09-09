<?php

namespace WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\Endpoint\SetWpmlProxyStatus;

use Throwable;
use WPML\Core\Component\WpmlProxy\Application\Exception\WpmlProxyException;
use WPML\Core\Component\WpmlProxy\Application\Service\WpmlProxyService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;

/**
 * POST endpoint for enabling or disabling WPML Proxy.
 *
 * Endpoint: POST /wpml/v1/wpml-proxy/status
 *
 */
class SetWpmlProxyStatusController implements EndpointInterface {

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
   * Handle POST request to enable or disable WPML Proxy.
   *
   * Request body:
   * {
   *   "enabled": true|false
   * }
   *
   * @param array<string,mixed>|null $requestData
   *
   * @return array<string,mixed> Response with operation status.
   */
  public function handle( $requestData = null ): array {
    try {
      $enabled = $this->validateRequest( $requestData );
      $this->updateProxyStatus( $enabled );

      return [
        'success' => true,
        'data'    => [
          'enabled' => $this->wpmlProxyService->isEnabled(),
        ],
      ];
    } catch ( InvalidArgumentException $e ) {
      return [
        'success' => false,
        'message' => $e->getMessage(),
        'status'  => 400,
      ];
    } catch ( Throwable $e ) {
      return [
        'success' => false,
        'message' => $e->getMessage(),
        'status'  => 403,
      ];
    }
  }


  /**
   * Validate request data and extract enabled parameter.
   *
   * @param mixed $requestData
   *
   * @return bool
   * @throws InvalidArgumentException
   */
  private function validateRequest( $requestData ): bool {
    if ( ! is_array( $requestData )
         || ! array_key_exists( 'enabled', $requestData )
    ) {
      throw new InvalidArgumentException( 'Missing required parameter: enabled' );
    }

    $enabled = $requestData['enabled'];

    if ( ! is_bool( $enabled ) ) {
      throw new InvalidArgumentException( 'Parameter "enabled" must be a boolean value' );
    }

    return $enabled;
  }


  /**
   * Update proxy status based on enabled flag.
   *
   * @param bool $enabled
   *
   * @return void
   * @throws WpmlProxyException
   */
  private function updateProxyStatus( bool $enabled ) {
    if ( $enabled ) {
      $this->wpmlProxyService->enable();
    } else {
      $this->wpmlProxyService->disable();
    }
  }


}
