<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\Server\Application\Endpoint\RestStatus;

use Throwable;
use WPML\Core\Port\Endpoint\EndpointInterface;

use function WPML\PHP\Logger\error;

class RestStatusController implements EndpointInterface {


  /**
   * @param array<string,mixed>|null $requestData
   *
   * @return array<mixed, mixed>
   */
  public function handle( $requestData = null ): array {
    try {
      // Validate that GET parameters are being read correctly
      $getParametersValid = $this->validateGetParameters( $requestData );

      return [
        'success' => true,
        'data'    => [
          'status'         => $getParametersValid ? 'valid' : 'invalid',
          'get_parameters' => $getParametersValid ? 'valid' : 'invalid',
        ]
      ];
    } catch ( Throwable $e ) {
      error(
        'Error checking REST API status: ' . $e->getMessage() . ' | File: '
        . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: '
        . $e->getTraceAsString()
      );

      return [
        'success' => false,
        'message' => $e->getMessage()
      ];
    }
  }


  /**
   * Validates that GET parameters are being read correctly
   *
   * @param array<string,mixed>|null $requestData
   *
   * @return bool
   */
  private function validateGetParameters( $requestData ): bool {
    return is_array( $requestData ) && count( $requestData ) > 0;
  }


}
