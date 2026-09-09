<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\TM\ATE\ClonedSites\AutoMigration\Handler;
use WPML\TM\ATE\ClonedSites\AutoMigration\Notice;
use WPML\TM\ATE\ClonedSites\Lock;
use WPML\TM\ATE\ClonedSites\SecondaryDomains;

class EnableAliasDomainController implements EndpointInterface {

  /** @var Lock */
  private $lock;

  /** @var SecondaryDomains */
  private $secondaryDomains;


  public function __construct( Lock $lock, SecondaryDomains $secondaryDomains ) {
    $this->lock             = $lock;
    $this->secondaryDomains = $secondaryDomains;
  }


  public function handle( $requestData = null ): array {
    $lockData        = $this->lock->getLockData();
    $aliasUrl        = $lockData['urlUsedToMakeRequest'];
    $originalSiteUrl = $lockData['urlCurrentlyRegisteredInAMS'];

    $aliasDomains = $this->secondaryDomains->add( $aliasUrl, $originalSiteUrl );
    $this->lock->unlock();

    // Registering an alias resolves the cloned-site scenario semantically: the
    // user just told WPML "this is the same site, just under a different URL".
    // The auto-migration banner (set by wpmldev-6725 when AMS returned 426 and
    // possibly when the subsequent copy_with_attachment failed) should disappear
    // at the same time — leaving it visible would contradict the alias the user
    // just registered. Mirrors what the Dismiss endpoint does for the migration
    // notice.
    Handler::clearMigrationData();
    delete_option( Notice::NOTICE_URL_KEY );

    return [
      'success'     => true,
      'aliasDomain' => [
        'originalSiteUrl' => $originalSiteUrl,
        'aliasDomains'    => $aliasDomains,
      ],
    ];
  }


}
