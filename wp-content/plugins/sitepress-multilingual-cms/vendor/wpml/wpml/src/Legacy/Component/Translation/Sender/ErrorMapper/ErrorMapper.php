<?php

namespace WPML\Legacy\Component\Translation\Sender\ErrorMapper;

class ErrorMapper {

  /** @var StrategyInterface[] */
  private $strategies;


  /**
   * @param StrategyInterface[] $strategies
   */
  public function __construct( array $strategies ) {
    $this->strategies = $strategies;
  }


  /**
   * @param array{id?: string, type?: string, text?: string}[] $errors
   *
   * @return string
   */
  public function map( array $errors ): string {
    foreach ( $this->strategies as $strategy ) {
      $message = $strategy->map( $errors );
      if ( $message ) {
        return $message;
      }
    }

    if ( count( $errors ) > 0 && isset( $errors[0]['text'] ) ) {
      return $this->sanitizeMessage( $errors[0]['text'] );
    }

    return __( 'The jobs could not be created.', 'wpml' );
  }


  /**
   * Mask sensitive data that might be present in raw error messages.
   *
   * @param string $message
   *
   * @return string
   */
  private function sanitizeMessage( string $message ): string {
    $replacement_field = 'censured_field';
    $replacement_value = '*******';

    $patterns = [
      // JSON fields with escaped quotes in body: \"accesskey\":\"value\" -> \"*******\":\"*******\"
      '/\\\\\"(?:accesskey|access_key|access-key)\\\\\":\\\\\"(?:[^\\\\\"]|\\\\.)*\\\\\"/i',
      // JSON fields with escaped quotes in body: \"api_key\":\"value\", \"token\":\"value\", etc.
      '/\\\\\"(?:api_key|apiKey|API_KEY|token|secret|authorization)\\\\\":\\\\\"(?:[^\\\\\"]|\\\\.)*\\\\\"/i',
      // Bearer tokens: Bearer abc.def.ghi
      '/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i',
    ];

    $replacements = [
      '\\"' . $replacement_field . '\\":\\"' . $replacement_value . '\\"',
      '\\"' . $replacement_field . '\\":\\"' . $replacement_value . '\\"',
      '$1' . $replacement_value,
    ];

    return (string) preg_replace( $patterns, $replacements, $message );
  }


}
