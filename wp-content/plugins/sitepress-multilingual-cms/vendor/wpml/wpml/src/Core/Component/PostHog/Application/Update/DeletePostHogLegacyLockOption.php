<?php

namespace WPML\Core\Component\PostHog\Application\Update;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Update\UpdateInterface;

/**
 * One-time migration: removes the permanent one-shot lock introduced by the original
 * PostHog enablement flow. The new flow uses a TTL-based cache (wpml_posthog_cache_last_checked)
 * instead, so this stale row must be cleared on upgrade so existing sites enter the
 * refreshable cache flow correctly on their next page load.
 */
class DeletePostHogLegacyLockOption implements UpdateInterface {

    const LEGACY_OPTION_KEY = 'wpml_posthog_default_request_sent';

    /** @var OptionsInterface */
    private $options;


  public function __construct( OptionsInterface $options ) {
      $this->options = $options;
  }


  public function update() {
      $this->options->delete( self::LEGACY_OPTION_KEY );
      return true;
  }


}
