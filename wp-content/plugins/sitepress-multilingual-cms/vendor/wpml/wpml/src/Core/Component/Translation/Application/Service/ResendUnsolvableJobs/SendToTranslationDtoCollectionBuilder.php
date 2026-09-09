<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs;

use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\BatchStringIdExtractor;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\SendToTranslationDtoBuilder;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\TranslationGrouper;
use WPML\Core\Component\Translation\Domain\Translation;

/**
 * Builds a collection of SendToTranslationDto objects from translations.
 *
 * Encapsulates the complete DTO preparation logic that must happen BEFORE job cancellation:
 * 1. Extracts string IDs from batches (requires DB access)
 * 2. Groups translations by source language
 * 3. Builds DTOs for each language group
 */
class SendToTranslationDtoCollectionBuilder {

  /** @var BatchStringIdExtractor */
  private $batchStringIdExtractor;

  /** @var TranslationGrouper */
  private $translationGrouper;

  /** @var SendToTranslationDtoBuilder */
  private $dtoBuilder;


  public function __construct(
    BatchStringIdExtractor $batchStringIdExtractor,
    TranslationGrouper $translationGrouper,
    SendToTranslationDtoBuilder $dtoBuilder
  ) {
    $this->batchStringIdExtractor = $batchStringIdExtractor;
    $this->translationGrouper     = $translationGrouper;
    $this->dtoBuilder             = $dtoBuilder;
  }


  /**
   * Build a collection of DTOs grouped by source language.
   *
   * This method must be called BEFORE job cancellation because it needs to access
   * the icl_string_batches records that will be deleted during cancellation.
   *
   * @param string        $batchName The batch name for all translations.
   * @param Translation[] $translations Array of translations to process.
   *
   * @return array<string, SendToTranslationDto> Map of source_language_code => SendToTranslationDto
   * @throws \WPML\PHP\Exception\Exception If DTO building fails.
   */
  public function buildCollection( string $batchName, array $translations ): array {
    $batchIdToStringIdsMap = $this->batchStringIdExtractor->extract( $translations );

    $groupedBySourceLanguage = $this->translationGrouper->groupBySourceLanguage( $translations );

    $dtos = [];
    foreach ( $groupedBySourceLanguage as $sourceLanguage => $translationsGroup ) {
      $dtos[ $sourceLanguage ] = $this->dtoBuilder->build(
        $batchName,
        $sourceLanguage,
        $translationsGroup,
        $batchIdToStringIdsMap
      );
    }

    return $dtos;
  }


}
