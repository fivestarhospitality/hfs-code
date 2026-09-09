<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class PostHogDefaultRequestSentRepository implements PostHogDefaultRequestSentRepositoryInterface {

  const OPTION_KEY = 'wpml_posthog_default_request_sent';

  /** @var OptionsInterface */
  private $options;

  /** @var \wpdb */
  private $wpdb;

  /** @var QueryPrepareInterface */
  private $queryPreparer;


  /**
   * @param \wpdb $wpdb Type defined here to allow injecting the global.
   * @param OptionsInterface $options
   */
  public function __construct(
    $wpdb,
    QueryPrepareInterface $queryPreparer,
    OptionsInterface $options
  ) {
    $this->wpdb          = $wpdb;
    $this->queryPreparer = $queryPreparer;
    $this->options       = $options;
  }


  public function isSent(): bool {
    return boolval(
      $this->options->get( self::OPTION_KEY, false )
    );
  }


  /**
   * Try to acquire the lock atomically.
   *
   * @return bool True if lock was acquired (you should make the API call),
   *              False if already acquired (skip the API call)
   */
  public function tryAcquireLock(): bool {
    $query = "INSERT INTO {$this->wpdb->options} (option_name, option_value, autoload) 
         VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = option_value";

    $sqlPrepared = $this->queryPreparer->prepare(
      $query,
      self::OPTION_KEY,
      '1' // represents true
    );

    // Use atomic INSERT with ON DUPLICATE KEY UPDATE
    $result = $this->wpdb->query( $sqlPrepared );

    // $result = 1 means row was inserted (current request won the race)
    // $result = 0 means row already existed (other request else won)
    return $result === 1;
  }


  /** @inheritDoc */
  public function setIsSent( bool $isSent ) {
    $this->options->save( self::OPTION_KEY, $isSent );
  }


  /** @inheritDoc */
  public function delete() {
    $this->options->delete( self::OPTION_KEY );
  }


}
