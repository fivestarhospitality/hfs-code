<?php

namespace WPML\UserInterface\Web\Core\Component\WpmlProxy\Application;

use WPML\Core\Component\WpmlProxy\Application\Service\WpmlProxyService;
use WPML\Core\Port\PluginInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;


/**
 * WPML Proxy Auto Disable Handler
 * Enqueues a scheduled script that checks if WPML domains are accessible
 * and automatically disables the proxy if no CSP violations occur.
 */
class WpmlProxyAutoDisableController implements ScriptDataProviderInterface,
  ScriptPrerequisitesInterface {

  /** @var string */
  private $checkUrl;

  /** @var WpmlProxyService */
  private $proxyService;


  public function __construct(
    PluginInterface $plugin,
    WpmlProxyService $proxyService
  ) {
    $this->checkUrl     = $plugin->getAMSHost() . '/api/wpml';
    $this->proxyService = $proxyService;
  }


  public function jsWindowKey(): string {
    return 'wpmlProxyAutoDisableConfig';
  }


  public function initialScriptData(): array {
    return [
      'checkUrl' => $this->checkUrl
    ];
  }


  public function scriptPrerequisitesMet(): bool {

    return $this->proxyService->isEnabled();
  }


}
