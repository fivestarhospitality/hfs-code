<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Site\Application\Query\SiteUrlQueryInterface;
use WPML\TM\ATE\ClonedSites\AliasDomainProber;
use WPML\TM\ATE\ClonedSites\SecondaryDomains;

class RegisterAliasDomainController implements EndpointInterface {

  /** @var SecondaryDomains */
  private $secondaryDomains;

  /** @var AliasDomainProber */
  private $prober;

  /** @var SiteUrlQueryInterface */
  private $siteUrlQuery;


  public function __construct(
    SecondaryDomains $secondaryDomains,
    AliasDomainProber $prober,
    SiteUrlQueryInterface $siteUrlQuery
  ) {
    $this->secondaryDomains = $secondaryDomains;
    $this->prober           = $prober;
    $this->siteUrlQuery     = $siteUrlQuery;
  }


  public function handle( $requestData = null ): array {
    $rawDomain = $requestData['domain'] ?? '';
    $alias     = is_string( $rawDomain ) ? trim( $rawDomain ) : '';

    if ( $alias === '' || filter_var( $alias, FILTER_VALIDATE_URL ) === false ) {
      return [
        'success' => false,
        'message' => __( 'Please enter a valid URL.', 'wpml' ),
      ];
    }

    // Strip the trailing slash so the stored value matches what site_url() returns
    // at request time (WordPress's _config_wp_siteurl filter rtrim()s WP_SITEURL).
    // Without this normalisation, the strict in_array() check in
    // SecondaryDomains::isRegistered() would never match, and the alias swap would
    // not fire — letting AMS see a 426 and triggering an auto-migration banner
    // instead of the expected silent fallback.
    $alias          = rtrim( $alias, '/' );
    $currentSiteUrl = rtrim( $this->siteUrlQuery->get(), '/' );

    if ( $alias === $currentSiteUrl ) {
      return [
        'success' => false,
        'message' => __( 'This is already the current site URL.', 'wpml' ),
      ];
    }

    if ( ! $this->prober->verify( $alias ) ) {
      return [
        'success' => false,
        'message' => __( 'We could not verify that the URL points to the same WordPress installation.', 'wpml' ),
      ];
    }

    $aliasDomains = $this->secondaryDomains->add( $alias, $currentSiteUrl );

    return [
      'success'     => true,
      'aliasDomain' => [
        'originalSiteUrl' => $currentSiteUrl,
        'aliasDomains'    => $aliasDomains,
      ],
    ];
  }


}
