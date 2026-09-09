<?php

namespace WPML\Core\Component\WpmlProxy\Application\Service;

use WPML\Core\Component\WpmlProxy\Application\Exception\WpmlProxyException;
use WPML\Core\Component\WpmlProxy\Domain\Repository\WpmlProxyRepositoryInterface;

/**
 * Service for managing WPML Proxy state.
 *
 * Provides business logic for enabling and disabling the WPML Proxy feature.
 */
class WpmlProxyService {

  /**
   * @var WpmlProxyRepositoryInterface
   */
  private $proxyRepository;


  /**
   * @param WpmlProxyRepositoryInterface $proxyRepository Repository for proxy state persistence.
   */
  public function __construct( WpmlProxyRepositoryInterface $proxyRepository ) {
    $this->proxyRepository = $proxyRepository;
  }


  /**
   * Enable WPML Proxy.
   *
   * Idempotent operation - safe to call multiple times.
   *
   * @return void
   * @throws WpmlProxyException If WPML_DISABLE_PROXY constant is defined.
   */
  public function enable() {
    $this->throwExceptionIfProxyIsControlledViaWPConstVariable();

    if ( $this->proxyRepository->isEnabled() ) {
      return;
    }

    $this->proxyRepository->setIsEnabled( true );
  }


  /**
   * Disable WPML Proxy.
   *
   * Idempotent operation - safe to call multiple times.
   *
   * @return void
   * @throws WpmlProxyException If WPML_DISABLE_PROXY constant is defined.
   */
  public function disable() {
    $this->throwExceptionIfProxyIsControlledViaWPConstVariable();

    if ( ! $this->proxyRepository->isEnabled() ) {
      return;
    }

    $this->proxyRepository->setIsEnabled( false );
  }


  /**
   * Check if WPML Proxy is currently enabled.
   *
   * @return bool True if proxy is enabled, false otherwise.
   */
  public function isEnabled(): bool {
    if ( defined( 'WPML_DISABLE_PROXY' ) ) {
      return ! WPML_DISABLE_PROXY;
    }

    return $this->proxyRepository->isEnabled();
  }


  /**
   * Toggle WPML Proxy state.
   *
   * If enabled, it will be disabled. If disabled, it will be enabled.
   *
   * @return bool The new state after toggling (true = enabled, false = disabled).
   * @throws WpmlProxyException If WPML_DISABLE_PROXY constant is defined.
   */
  public function toggle(): bool {
    if ( $this->isEnabled() ) {
      $this->disable();

      return false;
    } else {
      $this->enable();

      return true;
    }
  }


  /**
   * @return void
   * @throws WpmlProxyException
   */
  private function throwExceptionIfProxyIsControlledViaWPConstVariable() {
    if ( defined( 'WPML_DISABLE_PROXY' ) ) {
      throw new WpmlProxyException(
        'WPML_DISABLE_PROXY is defined in your wp-config.php,' .
        ' so WPML PROXY cannot be disabled or enabled automatically. Please remove it. '
      );
    }
  }


}
