<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository;

use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardLastStepSubmissionTimeRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SetupWizardLastStepSubmissionTimeRepository implements SetupWizardLastStepSubmissionTimeRepositoryInterface {

  const OPTION_NAME = 'wpml_ph_wizard_last_step_submission_time';

  /** @var OptionsInterface */
  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /** @return int|false */
  public function get() {
    /** @var int|false $lastSubmissionTime */
    $lastSubmissionTime = $this->options->get( self::OPTION_NAME );

    return $lastSubmissionTime;
  }


  /**
   * @param int $timestamp
   *
   * @return void
   */
  public function save( int $timestamp ) {
    $this->options->save( self::OPTION_NAME, $timestamp );
  }


}
