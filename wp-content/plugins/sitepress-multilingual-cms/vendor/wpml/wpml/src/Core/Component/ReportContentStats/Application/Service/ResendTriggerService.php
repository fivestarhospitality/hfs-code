<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

use WPML\Core\Component\ReportContentStats\Domain\Repository\EventReasonRepositoryInterface;
use WPML\Core\Component\ReportContentStats\Domain\Repository\LastSentRepositoryInterface;

class ResendTriggerService {

  const SECONDS_IN_DAY = 86400;
  const TRIGGER_DAYS_OFFSET = 29;

  /** @var LastSentRepositoryInterface */
  private $lastSentRepository;

  /** @var EventReasonRepositoryInterface */
  private $eventReasonRepository;


  public function __construct(
    LastSentRepositoryInterface $lastSentRepository,
    EventReasonRepositoryInterface $eventReasonRepository
  ) {
    $this->lastSentRepository    = $lastSentRepository;
    $this->eventReasonRepository = $eventReasonRepository;
  }


  /**
   * @return void
   */
  public function trigger( string $reason ) {
    $triggerTimestamp = time() - ( self::TRIGGER_DAYS_OFFSET * self::SECONDS_IN_DAY );

    $this->lastSentRepository->update( $triggerTimestamp );
    $this->eventReasonRepository->set( $reason );
  }


}
