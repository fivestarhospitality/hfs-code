<?php

namespace WPML\Core\Component\WpmlProxy\Domain\Repository;

/**
 * Repository interface for managing WPML Proxy state.
 *
 * Stores and retrieves the enabled/disabled state of the WPML Proxy feature.
 */
interface WpmlProxyRepositoryInterface {


  /**
   * Check if WPML Proxy is enabled.
   *
   * @return bool True if proxy is enabled, false otherwise (default: false).
   */
  public function isEnabled(): bool;


  /**
   * Set the WPML Proxy enabled state.
   *
   * @param bool $isEnabled True to enable proxy, false to disable.
   *
   * @return void
   */
  public function setIsEnabled( bool $isEnabled );


}
