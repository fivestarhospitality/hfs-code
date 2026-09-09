<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

use WPML\Core\Component\ReportContentStats\Domain\Repository\EventReasonRepositoryInterface;

class EventReasonService {

  const REASON_INITIAL = 'initial';
  const REASON_SCHEDULED = 'scheduled';
  const REASON_EDITOR_SWITCH = 'editor_switch';
  const REASON_LANGUAGE_CHANGE = 'language_change';
  const REASON_TRANSLATION_AFTER_BREAK = 'translation_after_break';
  const REASON_TRANSLATION_LARGE_BATCH = 'translation_large_batch';
  const REASON_CONTENT_CHANGE = 'content_change';

  /** @var EventReasonRepositoryInterface */
  private $eventReasonRepository;

  /** @var LastSentService */
  private $lastSentService;


  public function __construct(
    EventReasonRepositoryInterface $eventReasonRepository,
    LastSentService $lastSentService
  ) {
    $this->eventReasonRepository = $eventReasonRepository;
    $this->lastSentService       = $lastSentService;
  }


  public function getOrDetermine(): string {
    // Check if already set (e.g., from editor switch)
    $existing = $this->eventReasonRepository->get();
    if ( $existing !== null ) {
      return $existing;
    }

    // Determine based on last_sent state
    $lastSent = $this->lastSentService->get();
    $reason   = $lastSent === null ? self::REASON_INITIAL : self::REASON_SCHEDULED;

    // Store for consistency during reporting cycle
    $this->eventReasonRepository->set( $reason );

    return $reason;
  }


  /**
   * @return void
   */
  public function clear() {
    $this->eventReasonRepository->clear();
  }


}
