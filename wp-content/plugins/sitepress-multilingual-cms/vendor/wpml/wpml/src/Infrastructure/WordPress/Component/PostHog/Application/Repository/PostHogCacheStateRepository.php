<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\PostHogCacheStateRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class PostHogCacheStateRepository implements PostHogCacheStateRepositoryInterface {

    const LAST_CHECKED_KEY    = 'wpml_posthog_cache_last_checked';
    const PROCESSING_LOCK_KEY = 'wpml_posthog_check_in_progress';

    /** @var OptionsInterface */
    private $options;

    /** @var \wpdb Type defined here to allow injecting the global. */
    private $wpdb;

    /** @var QueryPrepareInterface */
    private $queryPreparer;


    /**
     * @param \wpdb $wpdb
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


  public function getLastChecked(): ?int {
      /** @var string|null $value */
      $value = $this->options->get( self::LAST_CHECKED_KEY, null );
      return $value !== null ? (int) $value : null;
  }


    /** @return void */
  public function setLastChecked( int $timestamp ) {
      $this->options->save( self::LAST_CHECKED_KEY, $timestamp );
  }


  public function isStale( int $ttlSeconds ): bool {
      $lastChecked = $this->getLastChecked();

    if ( $lastChecked === null ) {
        return true;
    }

      return time() - $lastChecked > $ttlSeconds;
  }


    /**
     * Uses the same atomic INSERT … ON DUPLICATE KEY UPDATE pattern as
     * PostHogDefaultRequestSentRepository. Returns true if the lock row was inserted
     * (this caller should proceed), false if the row already existed (another request
     * holds the lock).
     */
  public function acquireProcessingLock(): bool {
      $query = "INSERT INTO {$this->wpdb->options} (option_name, option_value, autoload)
		     VALUES (%s, %s, 'off')
		     ON DUPLICATE KEY UPDATE option_value = option_value";

      $sqlPrepared = $this->queryPreparer->prepare(
        $query,
        self::PROCESSING_LOCK_KEY,
        '1'
      );

      $result = $this->wpdb->query( $sqlPrepared );

      return $result === 1;
  }


    /** @return void */
  public function releaseProcessingLock() {
      $this->options->delete( self::PROCESSING_LOCK_KEY );
  }


}
