<?php

namespace WPML\Core\SharedKernel\Component\Item\Application\Service;

use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorService;

class UntranslatedService {

  /** @var UntranslatedTypesCountQueryInterface[] */
  private $queries;

  /** @var TranslationEditorService */
  private $settingTranslationEditorService;


  /**
   * @param UntranslatedTypesCountQueryInterface[] $queries
   */
  public function __construct(
    array $queries,
    TranslationEditorService $settingTranslationEditorService
  ) {
    $this->queries = $queries;
    $this->settingTranslationEditorService = $settingTranslationEditorService;
  }


  /**
   * @return UntranslatedTypeCountDto[]
   */
  public function getUntranslatedTypesCounts() {
    $editorSettings = $this->settingTranslationEditorService->getTranslationEditorSetting();
    $queryData = [];
    $queryData['nativeEditorGlobalSetting'] = $editorSettings && $editorSettings->useNativeEditorForAllPostTypes();
    $queryData['nativeEditorSettingPerType'] = $editorSettings ? $editorSettings->getPostTypesUsingNativeEditor() : [];
    $counts = [];

    foreach ( $this->queries as $query ) {
      foreach ( $query->get( $queryData ) as $count ) {
        if ( $count->getCount() === 0 ) {
          continue;
        }

        $counts[] = $count;
      }
    }

    return $counts;
  }


  /**
   * @param int $numberOfIdsToFetch
   * @param int $offset
   * @param string $kind
   * @param string $type
   *
   * @return int[]
   */
  public function getSomeUntranslatedIds( $numberOfIdsToFetch, $offset, $kind, $type ) {
    foreach ( $this->queries as $query ) {
      if ( $query->forKind() !== $kind ) {
        continue;
      }

      $ids = $query->getSomeIds( $numberOfIdsToFetch, $offset, $type );
      return $ids;
    }

    return [];
  }


}
