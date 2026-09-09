<?php

namespace WPML\Core\Component\PostHog\Domain\Repository;

interface SetupWizardUUIDRepositoryInterface {


  /**
   * @param string $uuid
   *
   * @return void
   */
  public function save( string $uuid );


  /**
   * @return string|false
   */
  public function get();


}
