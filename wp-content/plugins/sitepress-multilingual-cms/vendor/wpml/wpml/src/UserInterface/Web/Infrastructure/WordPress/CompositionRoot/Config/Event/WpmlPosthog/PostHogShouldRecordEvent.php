<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\WpmlPosthog;

use WPML\DicInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\WpmlPosthog\PostHogRecording\PostHogShouldRecordListener;

class PostHogShouldRecordEvent {

  const EVENT_NAME = 'check_posthog_should_record';

  /** @var DicInterface */
  private $dic;

  /** @var PostHogShouldRecordListener|null */
  private $posthogShouldRecordListener;


  public function __construct( DicInterface $dic ) {
    $this->dic = $dic;
    $this->register();
  }


  /** @return void */
  public function register() {
    // This action hook can be used manually or inside a scheduled event.
    add_action(
      self::EVENT_NAME,
      function () {
        $this->getPosthogShouldRecordListener()->check();
      }
    );

    // Clean up legacy scheduled events only if they exist
    // this is because we don't need any scheduled actions for this event anymore.
    // @see wpmldev-5938
    if ( wp_next_scheduled( self::EVENT_NAME ) ) {
      wp_clear_scheduled_hook( self::EVENT_NAME );
    }
  }


  private function getPosthogShouldRecordListener(): PostHogShouldRecordListener {
    if ( $this->posthogShouldRecordListener === null ) {
      $this->posthogShouldRecordListener = $this->dic->make( PostHogShouldRecordListener::class );
    }

    return $this->posthogShouldRecordListener;
  }


}
