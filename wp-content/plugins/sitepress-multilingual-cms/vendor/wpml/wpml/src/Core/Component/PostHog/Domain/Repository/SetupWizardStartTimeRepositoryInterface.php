<?php

namespace WPML\Core\Component\PostHog\Domain\Repository;

interface SetupWizardStartTimeRepositoryInterface {


  /** @return void */
  public function save( string $wizardUUID, int $timestamp );


  /** @return int|false */
  public function get( string $wizardUUID );


}
