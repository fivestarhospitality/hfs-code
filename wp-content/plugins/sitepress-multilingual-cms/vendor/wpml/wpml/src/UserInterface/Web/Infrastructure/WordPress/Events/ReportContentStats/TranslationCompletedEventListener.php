<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\TranslationActivityService;

class TranslationCompletedEventListener {

  /** @var TranslationActivityService */
  private $translationActivityService;


  public function __construct( TranslationActivityService $translationActivityService ) {
    $this->translationActivityService = $translationActivityService;
  }


  /** @return void */
  public function doActions() {
    $this->translationActivityService->onTranslationCompleted();
  }


}
