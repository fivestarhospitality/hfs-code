<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder;

use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationExtraInformationDto;
use WPML\Core\Component\Translation\Application\Service\Dto\TargetLanguageMethodDto;
use WPML\Core\Component\Translation\Domain\HowToHandleExistingTranslationType;
use WPML\Core\Component\Translation\Domain\Translation;

/**
 * Builds SendToTranslationDto from grouped translations.
 */
class SendToTranslationDtoBuilder {


  /**
   * Build SendToTranslationDto from translations.
   *
   * @param string            $batchName The batch name for the translation.
   * @param string            $sourceLanguage The source language code.
   * @param Translation[]     $translations Array of translations to process.
   * @param array<int, int[]> $batchIdToStringIdsMap Pre-fetched map of batch_id/string_id => string_ids[]
   *
   * @return SendToTranslationDto
   * @throws \WPML\PHP\Exception\Exception
   */
  public function build(
    string $batchName,
    string $sourceLanguage,
    array $translations,
    array $batchIdToStringIdsMap
  ): SendToTranslationDto {
    $elementsByType  = $this->categorizeElementsByType( $translations, $batchIdToStringIdsMap );
    $targetLanguages = $this->extractTargetLanguages( $translations );

    $targetLanguageMethods = $this->buildTargetLanguageMethods( $targetLanguages );
    $extraInformation      = $this->buildExtraInformation();

    return new SendToTranslationDto(
      $batchName,
      $sourceLanguage,
      $targetLanguageMethods,
      $elementsByType['posts'],
      $elementsByType['packages'],
      $elementsByType['strings'],
      $extraInformation
    );
  }


  /**
   * Categorize elements by type (posts, strings, packages).
   *
   * @param Translation[]     $translations Array of translations to categorize.
   * @param array<int, int[]> $batchIdToStringIdsMap Pre-fetched map of batch_id/string_id => string_ids[]
   *
   * @return array{posts: int[], strings: int[], packages: int[]}
   */
  private function categorizeElementsByType( array $translations, array $batchIdToStringIdsMap ): array {
    $posts    = [];
    $strings  = [];
    $packages = [];

    foreach ( $translations as $translation ) {
      $type      = $translation->getType()->get();
      $elementId = $translation->getOriginalElementId();

      switch ( $type ) {
        case 'string-batch':
        case 'string':
          // Use pre-fetched string IDs from map (fetched before batch deletion)
          if ( isset( $batchIdToStringIdsMap[ $elementId ] ) ) {
            $strings = array_merge( $strings, $batchIdToStringIdsMap[ $elementId ] );
          }
          break;
        case 'package':
          $packages[] = $elementId;
          break;
        default:
          $posts[] = $elementId;
          break;
      }
    }

    // Deduplicate element IDs (same element may appear in multiple batches)
    return [
      'posts'    => array_values( array_unique( $posts ) ),
      'strings'  => array_values( array_unique( $strings ) ),
      'packages' => array_values( array_unique( $packages ) ),
    ];
  }


  /**
   * Extract unique target language codes from translations.
   *
   * @param Translation[] $translations Array of translations to process.
   *
   * @return string[] Array of unique target language codes.
   */
  private function extractTargetLanguages( array $translations ): array {
    $targetLanguages = [];

    foreach ( $translations as $translation ) {
      $targetLanguages[ $translation->getTargetLanguageCode() ] = true;
    }

    return array_keys( $targetLanguages );
  }


  /**
   * Build target language method DTOs.
   *
   * @param string[] $targetLanguages Array of target language codes.
   *
   * @return TargetLanguageMethodDto[]
   * @throws \WPML\PHP\Exception\Exception
   */
  private function buildTargetLanguageMethods( array $targetLanguages ): array {
    $targetLanguageMethods = [];

    foreach ( $targetLanguages as $targetLanguage ) {
      $targetLanguageMethods[] = TargetLanguageMethodDto::fromArray(
        [
          'targetLanguageCode' => $targetLanguage,
          'translationMethod'  => 'automatic',
          'translatorId'       => null,
        ]
      );
    }

    return $targetLanguageMethods;
  }


  /**
   * Build extra information DTO with default values.
   *
   * @return SendToTranslationExtraInformationDto
   * @throws \WPML\PHP\Exception\Exception
   */
  private function buildExtraInformation(): SendToTranslationExtraInformationDto {
    return SendToTranslationExtraInformationDto::fromArray(
      [
        'deadline'                        => '',
        'howToHandleExistingTranslations' => HowToHandleExistingTranslationType::HANDLE_EXISTING_OVERRIDE,
      ]
    );
  }


}
