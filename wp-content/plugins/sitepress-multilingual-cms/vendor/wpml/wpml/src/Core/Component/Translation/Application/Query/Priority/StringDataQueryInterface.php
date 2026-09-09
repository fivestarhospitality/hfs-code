<?php

namespace WPML\Core\Component\Translation\Application\Query\Priority;

/**
 * Interface for querying string data needed for priority sorting.
 */
interface StringDataQueryInterface {


    /**
     * Get string data for the given string IDs.
     *
     * @param int[] $stringIds
     *
     * @return array<array{
     *   id: int,
     *   domain?: string|null,
     *   context?: string|null
     * }>
     */
  public function getStringsData( array $stringIds ): array;


}
