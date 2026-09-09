<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder;

use WPML\Core\Component\Translation\Domain\Translation;


class TranslationGrouper {


  /**
   * Group translations by source language code.
   *
   * @param Translation[] $translations Array of translations to group.
   *
   * @return array<string, Translation[]> Map of source_language_code => Translation[]
   */
  public function groupBySourceLanguage( array $translations ): array {
    $grouped = [];

    foreach ( $translations as $translation ) {
      $sourceLanguage = $translation->getSourceLanguageCode();

      if ( ! isset( $grouped[ $sourceLanguage ] ) ) {
        $grouped[ $sourceLanguage ] = [];
      }

      $grouped[ $sourceLanguage ][] = $translation;
    }

    return $grouped;
  }


}
