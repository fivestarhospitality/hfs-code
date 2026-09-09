<?php

namespace WPML\Core\Component\PostHog\Domain\Repository;

interface SetupWizardLastStepSubmissionTimeRepositoryInterface {


  /** @return int|false */
  public function get();


  /**
   * @param int $timestamp
   *
   * @return void
   */
  public function save( int $timestamp );


}
