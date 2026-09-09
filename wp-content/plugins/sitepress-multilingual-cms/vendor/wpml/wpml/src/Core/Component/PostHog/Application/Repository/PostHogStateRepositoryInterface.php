<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface PostHogStateRepositoryInterface {


  public function isEnabled(): bool;


  /**
   * @return void
   */
  public function setIsEnabled( bool $isEnabled );


  public function getTrackingMode(): string;


  /**
   * @return void
   */
  public function setTrackingMode( string $mode );


}
