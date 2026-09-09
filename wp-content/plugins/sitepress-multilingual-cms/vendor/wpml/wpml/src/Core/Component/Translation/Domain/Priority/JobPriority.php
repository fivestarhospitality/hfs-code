<?php

namespace WPML\Core\Component\Translation\Domain\Priority;

/**
 * Represents the priority of a translation job.
 * Combines tier (coarse priority) with rank (fine priority within tier).
 */
class JobPriority {

    /** @var int */
    private $jobId;

    /** @var Tier */
    private $tier;

    /** @var Rank */
    private $rank;

    /** @var int */
    private $position;


  public function __construct( int $jobId, Tier $tier, Rank $rank, int $position = 0 ) {
      $this->jobId    = $jobId;
      $this->tier     = $tier;
      $this->rank     = $rank;
      $this->position = $position;
  }


  public function getJobId(): int {
      return $this->jobId;
  }


  public function getTier(): Tier {
      return $this->tier;
  }


  public function getRank(): Rank {
      return $this->rank;
  }


  public function getPosition(): int {
      return $this->position;
  }


  public function withPosition( int $position ): self {
      return new self( $this->jobId, $this->tier, $this->rank, $position );
  }


    /**
     * Compare this priority with another priority.
     *
     * @param JobPriority $other
     *
     * @return int -1 if this < other, 0 if equal, 1 if this > other
     */
  public function compareTo( JobPriority $other ): int {
      $tierComparison = $this->tier->getValue() <=> $other->getTier()->getValue();
    if ( $tierComparison !== 0 ) {
        return $tierComparison;
    }

      return $this->rank->compareTo( $other->getRank() );
  }


    /**
     * Convert to array format for ATE API.
     *
     * @return array{tier: int, rank: array<int|string>}
     */
  public function toArray(): array {
      return [
          'tier' => $this->tier->getValue(),
          'rank' => $this->rank->getValues(),
      ];
  }


}
