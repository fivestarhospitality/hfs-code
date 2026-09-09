<?php

namespace WPML\Core\SharedKernel\Component\Item\Application\Query;

use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;

interface UntranslatedTypesCountQueryInterface {
  const KIND_POST = 'post';
  const KIND_PACKAGE = 'package';
  const KIND_STRING = 'string';


  /** @return self::KIND_* */
  public function forKind();


  /**
   * @phpstan-param array{
   *    nativeEditorGlobalSetting?: bool,
   *    nativeEditorSettingPerType?: array<string, bool>
   * } $queryData
   *
   * @return UntranslatedTypeCountDto[]
   */
  public function get( array $queryData = [] ): array;


  /**
   * @param int $numberOfIdsToFetch
   * @param int $offset
   * @param string $type
   *
   * @return int[]
   */
  public function getSomeIds( $numberOfIdsToFetch, $offset, $type );


}
