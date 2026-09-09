<?php

namespace WPML\Legacy\Component\Translation\Sender\ErrorMapper;

class TranslationServiceUnavailable implements StrategyInterface {


  /**
   * The expected error message should have following structure:
   * "(34) hub staging 03 does not accept new translation jobs at this moment<br />Please contact hub staging 03 support to get more information and assistance."
   * For each row in $errors, we must check if the error message matches the expected structure.
   * If it does, we must extract the service name and return a user-friendly message.
   *
   * @param array{type?: string, text?: string}[] $errors
   *
   * @return string|null
   */
  public function map( array $errors ) {
    $pattern = '/does not accept new translation jobs at this moment/i';

    foreach ( $errors as $error ) {
      if ( preg_match( $pattern, $error['text'] ?? '' ) ) {
        // Try to extract the service name from the error message
        $serviceNamePattern = '/\(\d+\)\s+([^<]+?)\s+does not accept/i';
        if ( preg_match(
          $serviceNamePattern,
          $error['text'] ?? '',
          $matches
        )
        ) {
          $serviceName = trim( $matches[1] );

          return sprintf(
            __(
              'The translation service "%s" is not accepting new translation jobs at this moment. '
              .
              'Please contact the service support for more information and assistance.',
              'wpml'
            ),
            $serviceName
          );
        }

        // Fallback message if we can't extract the service name
        return __(
          'The translation service is not accepting new translation jobs at this moment. '
          . 'Please contact the service support for more information and assistance.',
          'wpml'
        );
      }
    }

    return null;
  }


}
