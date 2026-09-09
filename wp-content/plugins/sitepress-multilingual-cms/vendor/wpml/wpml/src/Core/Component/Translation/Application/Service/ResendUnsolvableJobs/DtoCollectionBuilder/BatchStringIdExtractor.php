<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder;

use WPML\Core\Component\Translation\Application\String\Query\StringsFromBatchQueryInterface;
use WPML\Core\Component\Translation\Domain\Translation;

class BatchStringIdExtractor {

  /** @var StringsFromBatchQueryInterface */
  private $stringsFromBatchQuery;


  /**
   * Constructor.
   *
   * @param StringsFromBatchQueryInterface $stringsFromBatchQuery Query for getting string IDs from batch ID.
   */
  public function __construct( StringsFromBatchQueryInterface $stringsFromBatchQuery ) {
    $this->stringsFromBatchQuery = $stringsFromBatchQuery;
  }


  /**
   * Extract string IDs from batch translations.
   *
   * @param Translation[] $translations Array of translations to process.
   *
   * @return array<int, int[]> Map of batch_id/string_id => string_ids[]
   */
  public function extract( array $translations ): array {
    $batchIdToStringIdsMap = [];

    foreach ( $translations as $translation ) {
      $type      = $translation->getType()->get();
      $elementId = $translation->getOriginalElementId();

      if ( $type === 'string-batch' || $type === 'string' ) {
        // Only fetch if not already fetched (avoid duplicate queries)
        if ( ! isset( $batchIdToStringIdsMap[ $elementId ] ) ) {
          $batchIdToStringIdsMap[ $elementId ] = $this->stringsFromBatchQuery->get( $elementId );
        }
      }
    }

    return $batchIdToStringIdsMap;
  }


}
