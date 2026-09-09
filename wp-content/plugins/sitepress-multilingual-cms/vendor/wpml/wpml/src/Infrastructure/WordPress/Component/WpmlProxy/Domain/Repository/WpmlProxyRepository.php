<?php

namespace WPML\Infrastructure\WordPress\Component\WpmlProxy\Domain\Repository;

use WPML\Core\Component\WpmlProxy\Domain\Repository\WpmlProxyRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

/**
 * WordPress implementation of the WPML Proxy state repository.
 *
 * Stores the proxy enabled/disabled state in wp_options table.
 */
class WpmlProxyRepository implements WpmlProxyRepositoryInterface {

  /**
   * Option name for storing proxy state in wp_options.
   */
  const OPTION_NAME = 'wpml_proxy_enabled';

  /**
   * @var OptionsInterface
   */
  private $options;


  /**
   * @param OptionsInterface $options WordPress options abstraction layer.
   */
  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /**
   * Check if WPML Proxy is enabled.
   *
   * @return bool True if proxy is enabled, false otherwise (default: false).
   */
  public function isEnabled(): bool {
    /** @var bool $isEnabled */
    $isEnabled = $this->options->get( self::OPTION_NAME, false );

    return $isEnabled;
  }


  /**
   * Set the WPML Proxy enabled state.
   *
   * @param bool $isEnabled True to enable proxy, false to disable.
   *
   * @return void
   */
  public function setIsEnabled( bool $isEnabled ) {
    $this->options->save( self::OPTION_NAME, $isEnabled ? 1 : 0 );
  }


}
