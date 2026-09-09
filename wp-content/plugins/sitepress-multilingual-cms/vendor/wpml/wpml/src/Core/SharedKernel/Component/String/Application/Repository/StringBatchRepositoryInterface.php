<?php

// phpcs:ignore PHPCompatibility.Keywords.ForbiddenNamesAsDeclared.stringFound
namespace WPML\Core\SharedKernel\Component\String\Application\Repository;

use WPML\Core\SharedKernel\Component\String\Domain\StringTranslation;

interface StringBatchRepositoryInterface {


  /**
   * @param int    $batchId
   * @param string $targetLanguage
   *
   * @return StringTranslation[]
   */
  public function getStringTranslationsByBatch( int $batchId, string $targetLanguage ): array;


  /**
   * @param int[] $translationIds
   *
   * @return int
   */
  public function deleteStringTranslationsByIds( array $translationIds ): int;


  /**
   * @param int[] $translationIds
   * @param int   $status
   *
   * @return int
   */
  public function updateStringTranslationsStatus( array $translationIds, int $status ): int;


  public function deleteStringBatch( int $batchId ): int;


}
