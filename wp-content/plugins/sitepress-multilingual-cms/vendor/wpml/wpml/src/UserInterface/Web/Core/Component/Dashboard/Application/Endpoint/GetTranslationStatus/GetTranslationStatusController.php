<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetTranslationStatus;

use WPML\Core\Component\Translation\Application\Query\Dto\TranslationStatusDto;
use WPML\Core\Component\Translation\Application\Query\TranslationStatusQueryInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;


class GetTranslationStatusController implements EndpointInterface {

  /** @var TranslationStatusQueryInterface */
  private $translationStatusQuery;


  public function __construct( TranslationStatusQueryInterface $translationStatusQuery ) {
    $this->translationStatusQuery = $translationStatusQuery;
  }


  /**
   * @psalm-suppress MoreSpecificImplementedParamType
   * @psalm-suppress PossiblyNullReference
   *
   * @param mixed $requestData jobIds (in some cases can contain non-numeric values that need filtering)
   *
   * @return array<array{itemId: int, type: string, targetLanguage: string, status: int, reviewStatus: string|null}>
   */
  public function handle( $requestData = null ): array {
    // removes non-numeric values (e.g., 'rest_route' => '/wpml/...')
    // For some specific setups like PHP 8.3 + Nginx behind Kubernetes the rest route
    // is extracted and sent in the $_GET params which can cause problems here.
    // @see wpmldev-6482
    $data = is_array( $requestData ) ? $requestData : [];
    /** @var array<array-key, int|numeric-string> $jobIds */
    $jobIds = array_filter( $data, 'is_numeric' );
    $jobIds = array_map( 'intval', array_values( $jobIds ) );
    $translations  = $this->translationStatusQuery->getByJobIds( $jobIds, true );

    return array_map(
      function ( TranslationStatusDto $translation ) {
        return $translation->toArray();
      },
      $translations
    );
  }


}
