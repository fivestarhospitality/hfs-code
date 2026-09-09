<?php

namespace WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints;

interface PostHogRecordingInterface {


  /**
   * @param string $siteKey
   * @param string $recordingMode
   * @param string $wpmlVersion
   * @param string $teaState
   *
   * @return array{
   *   success: bool,
   *   shouldRecord: bool,
   *   trackingMode: string,
   *   isResponseError: bool
   * }
   */
  public function run(
    string $siteKey,
    string $recordingMode = 'default',
    string $wpmlVersion = '',
    string $teaState = ''
  ): array;


}
