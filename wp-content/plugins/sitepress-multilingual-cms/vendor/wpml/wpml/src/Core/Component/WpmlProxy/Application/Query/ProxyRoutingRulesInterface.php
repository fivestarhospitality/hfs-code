<?php

namespace WPML\Core\Component\WpmlProxy\Application\Query;

interface ProxyRoutingRulesInterface {


    /**
     * Get all allowed domains for the proxy interceptor.
     *
     * @return array<string>
     */
  public function getDomains(): array;


}
