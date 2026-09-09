<?php

namespace WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository;

use WPML\Core\Component\ReportContentStats\Domain\Repository\LastTranslationCompletedRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class LastTranslationCompletedRepository implements LastTranslationCompletedRepositoryInterface {

  const OPTION_KEY = 'wpml-stats-last-translation-completed';

  /** @var Options */
  private $options;


  public function __construct( Options $options ) {
    $this->options = $options;
  }


  /**
   * @return int|null
   */
  public function get() {
    /** @var int|null $timestamp */
    $timestamp = $this->options->get( self::OPTION_KEY, null );

    return $timestamp;
  }


  /** @return void */
  public function update( int $timestamp ) {
    $this->options->save( self::OPTION_KEY, $timestamp );
  }


}
