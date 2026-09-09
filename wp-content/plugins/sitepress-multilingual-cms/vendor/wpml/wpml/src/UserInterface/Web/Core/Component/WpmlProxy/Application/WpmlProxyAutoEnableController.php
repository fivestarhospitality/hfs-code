<?php

namespace WPML\UserInterface\Web\Core\Component\WpmlProxy\Application;

use WPML\Core\Component\WpmlProxy\Application\Query\ProxyRoutingRulesInterface;
use WPML\Core\Component\WpmlProxy\Application\Service\WpmlProxyService;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;


/**
 * CSP Violation Listener
 * Adds a JavaScript listener to catch and log Content Security Policy violations.
 */
class WpmlProxyAutoEnableController implements ScriptDataProviderInterface, ScriptPrerequisitesInterface {

  /** @var string[] */
  private $allowedDomains;

  /** @var WpmlProxyService */
  private $proxyService;


  public function __construct(WpmlProxyService $proxyService, ProxyRoutingRulesInterface $allowedHosts
  ) {
    $this->proxyService = $proxyService;
    $this->allowedDomains = $allowedHosts->getDomains();
  }


  public function jsWindowKey(): string {
    return 'wpmlProxyAutoEnableConfig';
  }


  public function initialScriptData(): array {
    return [ 'allowedDomains' => $this->allowedDomains ];
  }


  public function scriptPrerequisitesMet(): bool {
    return ! $this->proxyService->isEnabled();
  }


}
