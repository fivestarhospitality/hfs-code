<?php

namespace WPML\Core\Component\ATE\Domain\Credits\Repository;

interface CreditsInProgressRepositoryInterface {


  /**
   * @param int[] $statusesInProgress
   *
   * @return int
   */
  public function getCreditsInProgressCount( $statusesInProgress );


}
