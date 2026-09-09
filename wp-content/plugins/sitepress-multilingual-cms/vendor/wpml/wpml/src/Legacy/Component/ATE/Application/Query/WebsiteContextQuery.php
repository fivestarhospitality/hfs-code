<?php

namespace WPML\Legacy\Component\ATE\Application\Query;

use WPML\Core\Component\ATE\Application\Query\Dto\WebsiteContextDto;
use WPML\Core\Component\ATE\Application\Query\WebsiteContextException;
use WPML\Core\Component\ATE\Application\Query\WebsiteContextQueryInterface;
use WPML\TM\API\ATE\WebsiteContext;

/**
 * @phpstan-type WebsiteContextArray array{
 *    context_present?: bool,
 *    last_sync?: string|null,
 *    context?: string|null,
 *    language_iso?: string|null,
 *    site_topic?: string|null,
 *    site_purpose?: string|null,
 *    site_audience? : string|null,
 *    status?: string|null,
 *    translate_names?: int|null
 *  }
 *
 *  @phpstan-type WebsiteContextErrorArray array{
 *    error: string,
 *  }
 */

class WebsiteContextQuery implements WebsiteContextQueryInterface {


  public function getWebsiteContext(): WebsiteContextDto {

    $apiResult = WebsiteContext::getWebsiteContext();
    if ( array_key_exists( 'error', (array) $apiResult ) ) {
      /** @var WebsiteContextErrorArray $apiResult */
      throw new WebsiteContextException( $apiResult['error'] );
    }

    /** @var WebsiteContextArray $apiResult */
    $defaults = [
      'context_present' => false,
      'last_sync' => null,
      'context' => '',
      'language_iso' => '',
      'site_topic' => '',
      'site_purpose' => '',
      'site_audience' => '',
      'status' => '',
      'translate_names' => 0,
    ];

    $data = array_merge( $defaults, $apiResult );

    return new WebsiteContextDto(
      $data['context_present'],
      $data['last_sync'],
      $data['context'],
      $data['language_iso'],
      $data['site_topic'],
      $data['site_purpose'],
      $data['site_audience'],
      $data['status'],
      $data['translate_names']
    );
  }


  public function isContextPresent(): bool {
    try {
        return $this->getWebsiteContext()->isContextPresent();
    } catch ( WebsiteContextException $e ) {
        return false;
    }
  }


}
