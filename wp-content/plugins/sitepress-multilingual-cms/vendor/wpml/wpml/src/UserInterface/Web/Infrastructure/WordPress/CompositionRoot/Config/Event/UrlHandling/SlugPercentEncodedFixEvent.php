<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\UrlHandling;

use WPML\DicInterface;
use WPML\Infrastructure\WordPress\Component\UrlHandling\Application\Hook\SlugPercentEncodedFixAction;

/**
 * Class SlugPercentEncodedFixEvent
 *
 * Registers the sanitize_title filter for fixing percent-encoded slugs in translations.
 */
class SlugPercentEncodedFixEvent {

  /** @var DicInterface */
  private $dic;

  /** @var SlugPercentEncodedFixAction|null */
  private $slugPercentEncodedFixAction;


  public function __construct( DicInterface $dic ) {
    $this->dic = $dic;
    $this->register();
  }


  /** @return void */
  public function register() {
    /** @psalm-suppress InvalidArgument */
    add_filter(
      'sanitize_title',
      function ( $title, $rawTitle = '', $context = '' ) {
        return $this->getSlugPercentEncodedFixAction()
            ->fixPercentInTranslationSlug( $title, $rawTitle, $context );
      },
      9,
      3
    );
  }


  private function getSlugPercentEncodedFixAction(): SlugPercentEncodedFixAction {
    if ( $this->slugPercentEncodedFixAction === null ) {
      $this->slugPercentEncodedFixAction = $this->dic->make(
        SlugPercentEncodedFixAction::class
      );
    }

    return $this->slugPercentEncodedFixAction;
  }


}
