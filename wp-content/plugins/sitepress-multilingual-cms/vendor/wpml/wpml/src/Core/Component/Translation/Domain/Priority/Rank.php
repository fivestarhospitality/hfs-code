<?php

namespace WPML\Core\Component\Translation\Domain\Priority;

/**
 * Represents a rank within a tier for fine-grained sorting.
 * The rank is an array of comparable values used for multi-level sorting.
 */
class Rank {

    /** @var array<int|string> */
    private $values;


    /**
     * @param array<int|string> $values
     */
  public function __construct( array $values ) {
      $this->values = $values;
  }


    /**
     * @return array<int|string>
     */
  public function getValues(): array {
      return $this->values;
  }


    /**
     * Compare this rank with another rank.
     *
     * @param Rank $other
     *
     * @return int -1 if this < other, 0 if equal, 1 if this > other
     */
  public function compareTo( Rank $other ): int {
      $thisValues  = $this->values;
      $otherValues = $other->getValues();
      $maxLength   = max( count( $thisValues ), count( $otherValues ) );

    for ( $i = 0; $i < $maxLength; $i++ ) {
        $thisValue  = $thisValues[ $i ] ?? 0;
        $otherValue = $otherValues[ $i ] ?? 0;

      if ( $thisValue < $otherValue ) {
        return -1;
      }
      if ( $thisValue > $otherValue ) {
          return 1;
      }
    }

      return 0;
  }


}
