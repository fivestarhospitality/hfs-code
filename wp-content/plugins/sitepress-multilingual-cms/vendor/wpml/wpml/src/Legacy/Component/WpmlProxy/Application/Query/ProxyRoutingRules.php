<?php

namespace WPML\Legacy\Component\WpmlProxy\Application\Query;

use WPML\Core\Component\WpmlProxy\Application\Query\ProxyRoutingRulesInterface;

class ProxyRoutingRules implements ProxyRoutingRulesInterface {

    /**
     * @var \WPML\ATE\Proxies\ProxyRoutingRules|null
     * @phpstan-ignore-next-line
     * @psalm-suppress UndefinedDocblockClass
     */
    private $loader;


  public function __construct() {
    if ( class_exists( 'WPML\ATE\Proxies\ProxyRoutingRules' ) ) {
        $this->loader = new \WPML\ATE\Proxies\ProxyRoutingRules();
    }
  }


    /**
     * Get all allowed domains for the proxy interceptor.
     *
     * @return array<string>
     */
  public function getDomains(): array {
    if ( $this->loader === null ) {
        return [ 'https://ams.wpml.org', 'https://ate.wpml.org' ];
    }

      /**
       * @phpstan-ignore-next-line
       * @psalm-suppress UndefinedDocblockClass
       */
      return $this->loader->getAllowedDomains();
  }


}
