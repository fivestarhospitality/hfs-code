<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository;

use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SetupWizardUUIDRepository implements SetupWizardUUIDRepositoryInterface {

  const OPTION_NAME = 'wpml_ph_wizard_uuid';

  /** @var OptionsInterface */
  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /**
   * @param string $uuid
   *
   * @return void
   */
  public function save( string $uuid ) {
    $this->options->save( self::OPTION_NAME, $uuid );
  }


  /**
   * @return string|false
   */
  public function get() {
    /** @var string|false $uuid */
    $uuid = $this->options->get( self::OPTION_NAME );

    return $uuid;
  }


}
