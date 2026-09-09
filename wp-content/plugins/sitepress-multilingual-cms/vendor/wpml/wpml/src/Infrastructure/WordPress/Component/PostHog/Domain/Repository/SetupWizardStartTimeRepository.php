<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository;

use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SetupWizardStartTimeRepository implements SetupWizardStartTimeRepositoryInterface {

  const OPTION_NAME = 'wpml_ph_wizard_start_time';

  /** @var OptionsInterface */
  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /** @return void */
  public function save( string $wizardUUID, int $timestamp ) {
    if ( ! $wizardUUID ) {
      return;
    }

    $optionName = $this->getOptionName( $wizardUUID );
    $this->options->save( $optionName, $timestamp );
  }


  /** @return int|false */
  public function get( string $wizardUUID ) {
    if ( ! $wizardUUID ) {
      return false;
    }

    $optionName = $this->getOptionName( $wizardUUID );

    /** @var int|false $stratTime */
    $stratTime = $this->options->get( $optionName );

    return $stratTime;
  }


  private function getOptionName( string $wizardUUID ): string {
    return self::OPTION_NAME . '_' . $wizardUUID;
  }


}
