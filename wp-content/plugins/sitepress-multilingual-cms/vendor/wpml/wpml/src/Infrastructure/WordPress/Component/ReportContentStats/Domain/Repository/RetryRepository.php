<?php

namespace WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository;

use WPML\Core\Component\ReportContentStats\Domain\Repository\RetryRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class RetryRepository implements RetryRepositoryInterface {

    const OPTION_KEY = 'wpml-stats-retry-data';

    /** @var Options */
    private $options;


  public function __construct( Options $options ) {
      $this->options = $options;
  }


    /**
     * @return array{attempt_count: int, last_attempt_timestamp: int|null}|null
     */
  public function get() {
      /** @var array{attempt_count: int, last_attempt_timestamp: int|null}|null $retryData */
      $retryData = $this->options->get( self::OPTION_KEY, null );

      return $retryData;
  }


    /**
     * @param array{attempt_count: int, last_attempt_timestamp: int|null} $retryData
     *
     * @return void
     */
  public function update( array $retryData ) {
      $this->options->save( self::OPTION_KEY, $retryData );
  }


    /**
     * @return void
     */
  public function delete() {
      $this->options->delete( self::OPTION_KEY );
  }


}
