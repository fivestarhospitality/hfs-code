<?php

namespace WPML\Core\Port\Remote;

use WPML\PHP\Exception\JsonEncodeException;
use WPML\PHP\Exception\RemoteException;

interface RemoteInterface {


  /**
   * @param string $url
   * @param array<string, mixed> $data
   * @param bool $asJson
   * @param bool $blocking
   * @param int $timeout
   * @param array<string, string> $headers
   *
   * @return mixed
   *
   * @throws RemoteException
   */
  public function post(
    $url,
    $data,
    $asJson = true,
    $blocking = false,
    $timeout = 1,
    $headers = []
  );


  /**
   * @param array<string, mixed> $data
   *
   * @return string
   *
   * @throws JsonEncodeException
   */
  public function jsonEncode( $data );


}
