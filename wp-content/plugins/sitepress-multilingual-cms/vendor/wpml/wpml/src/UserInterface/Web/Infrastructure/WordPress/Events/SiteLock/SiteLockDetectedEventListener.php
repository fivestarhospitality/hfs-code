<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\SiteLock;

use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsService;

class SiteLockDetectedEventListener {

  /** @var ContentStatsService */
  private $contentStatsService;


  public function __construct( ContentStatsService $contentStatsService ) {
    $this->contentStatsService = $contentStatsService;
  }


  /** @return void */
  public function doActions() {
    $this->resetContentStatsData();
  }


  /** @return void */
  private function resetContentStatsData() {
    $this->contentStatsService->resetPostTypesStatsData();
  }


}
