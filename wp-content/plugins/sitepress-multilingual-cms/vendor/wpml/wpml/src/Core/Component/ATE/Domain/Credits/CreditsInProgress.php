<?php

namespace WPML\Core\Component\ATE\Domain\Credits;

use WPML\Core\Component\ATE\Domain\Credits\Repository\CreditsInProgressRepositoryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class CreditsInProgress {

  /** @var CreditsInProgressRepositoryInterface */
  private $creditsInProgressRepository;


  public function __construct( CreditsInProgressRepositoryInterface $creditsInProgressRepository ) {
    $this->creditsInProgressRepository = $creditsInProgressRepository;
  }


  /**
   * @return CreditsInProgressDTO
   */
  public function getCount() {
    return new CreditsInProgressDTO(
      $this->creditsInProgressRepository->getCreditsInProgressCount(
        $this->statusesToConsider()
      )
    );
  }


  /**
   * @return int[]
   */
  private function statusesToConsider() {
    return [
      TranslationStatus::IN_PROGRESS,
      TranslationStatus::ATE_NEEDS_RETRY
    ];
  }


}
