<?php

// phpcs:ignore PHPCompatibility.Keywords.ForbiddenNamesAsDeclared.stringFound
namespace WPML\Core\SharedKernel\Component\String\Application\Service;

use WPML\Core\SharedKernel\Component\String\Application\Repository\StringBatchRepositoryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class StringBatchCleanupService {

  /** @var StringBatchRepositoryInterface */
  private $stringBatchRepository;


  public function __construct( StringBatchRepositoryInterface $stringBatchRepository ) {
    $this->stringBatchRepository = $stringBatchRepository;
  }


  /**
   * Cleanup logic:
   * - Untranslated strings (no value, no mo_string) → DELETE
   * - Translated strings (has value or mo_string) → UPDATE status to COMPLETE
   *
   * @return void
   */
  public function cleanupBatch( int $batchId, string $targetLanguage ) {
    $stringTranslations = $this->stringBatchRepository->getStringTranslationsByBatch( $batchId, $targetLanguage );

    $idsToDelete = [];
    $idsToComplete = [];

    foreach ( $stringTranslations as $translation ) {
      if ( $translation->isUntranslated() ) {
        $idsToDelete[] = $translation->getId();
      } else {
        $idsToComplete[] = $translation->getId();
      }
    }

    if ( ! empty( $idsToDelete ) ) {
      $this->stringBatchRepository->deleteStringTranslationsByIds( $idsToDelete );
    }

    if ( ! empty( $idsToComplete ) ) {
      $this->stringBatchRepository->updateStringTranslationsStatus( $idsToComplete, TranslationStatus::COMPLETE );
    }

    $this->stringBatchRepository->deleteStringBatch( $batchId );
  }


}
