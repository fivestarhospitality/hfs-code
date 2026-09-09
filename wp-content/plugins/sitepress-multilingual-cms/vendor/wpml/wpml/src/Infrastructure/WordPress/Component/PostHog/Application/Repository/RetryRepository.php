<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\RetryRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class RetryRepository implements RetryRepositoryInterface {

  const OPTION_KEY = 'wpml_posthog_default_request_retry';

  /** @var OptionsInterface */
  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /** @inheritDoc */
  public function get() {
    /** @var array{attempt_count: int, last_attempt_timestamp: int|null}|null $retryData */
    $retryData = $this->options->get( self::OPTION_KEY, null );

    return $retryData;
  }


  /** @inheritDoc */
  public function update( array $retryData ) {
    $this->options->save( self::OPTION_KEY, $retryData );
  }


  /** @inheritDoc */
  public function delete() {
    $this->options->delete( self::OPTION_KEY );
  }


}
