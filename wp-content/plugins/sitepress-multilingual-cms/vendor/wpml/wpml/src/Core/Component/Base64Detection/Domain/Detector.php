<?php

namespace WPML\Core\Component\Base64Detection\Domain;

/**
 * Detects base64 encoded content in various formats
 */
class Detector {

  /**
   * Minimum length for base64 strings to reduce false positives
   */
  const MINIMUM_BASE64_LENGTH = 100;


  /**
   * Detects if the content is base64 encoded.
   *
   * @param string $content
   *
   * @return bool
   */
  public function isBase64EncodedText( string $content ): bool {
    if ( empty( $content ) ) {
      return false;
    }

    // Check for data-URI format first
    if ( $this->hasDataUriBase64( $content ) ) {
      return true;
    }

    // Check if the entire content is raw base64
    return $this->isRawBase64( $content );
  }


  /**
   * Checks if content contains embedded base64 strings within text
   * Decodes content first if it's base64, then searches for base64 patterns
   *
   * @param string $content The content to check
   *
   * @return bool True if embedded base64 is found
   */
  public function containsBase64EncodedText( string $content ): bool {
    // Try to decode the content first
    $decoded = $this->attemptDecode( $content );

    // If successfully decoded, search in the decoded content
    if ( $decoded !== null && $decoded !== $content ) {
      // Search for embedded base64 in the decoded content
      if ( $this->searchForEmbeddedBase64( $decoded ) ) {
        return true;
      }

      // Also check if decoded content contains data-URI base64
      if ( $this->hasDataUriBase64( $decoded ) ) {
        return true;
      }
    } else {
      // If not base64, search for embedded base64 in the original content
      if ( $this->searchForEmbeddedBase64( $content ) ) {
        return true;
      }
    }

    return false;
  }


  /**
   * Checks if content contains data-URI base64 encoded data
   *
   * @param string $content The content to check
   *
   * @return bool True if data-URI base64 is found
   */
  private function hasDataUriBase64( string $content ): bool {
    $pattern = '/data:[^;]+;base64,([a-zA-Z0-9+\/\r\n]+=*)/i';

    if ( preg_match_all( $pattern, $content, $matches ) ) {
      foreach ( $matches[1] as $base64Data ) {
        if ( $this->isValidBase64( $base64Data ) ) {
          return true;
        }
      }
    }

    return false;
  }


  /**
   * Checks if content is raw base64 encoded data
   *
   * @param string $content The content to check
   *
   * @return bool True if content is valid base64
   */
  private function isRawBase64( string $content ): bool {
    // Remove whitespace that might be present in base64 strings
    $cleanContent = preg_replace( '/\s+/', '', $content );

    if ( empty( $cleanContent ) ) {
      return false;
    }

    return $this->isValidBase64( $cleanContent );
  }


  /**
   * Attempts to decode content if it appears to be base64
   *
   * @param string $content The content to attempt decoding
   *
   * @return string|null Decoded content or null if not valid base64
   */
  private function attemptDecode( string $content ) {
    $cleanContent = preg_replace( '/\s+/', '', $content );

    if ( ! $cleanContent ) {
      return null;
    }

    // Only attempt decode if it looks like base64 and has minimum length requirement
    if ( strlen( $cleanContent ) >= self::MINIMUM_BASE64_LENGTH &&
         preg_match( '/^[a-zA-Z0-9+\/]*={0,2}$/', $cleanContent ) &&
         strlen( $cleanContent ) % 4 === 0 ) {

      $decoded = base64_decode( $cleanContent, true );
      if ( $decoded !== false ) {
        return $decoded;
      }
    }

    return null;
  }


  /**
   * Searches for embedded base64 strings in content
   * Reuses existing detection functions for consistency
   *
   * @param string $content The content to search
   *
   * @return bool True if base64 strings are found
   */
  private function searchForEmbeddedBase64( string $content ): bool {
    // Check for data-URI base64 patterns
    if ( $this->hasDataUriBase64( $content ) ) {
      return true;
    }

    // Look for raw base64 strings embedded in text
    // Use minimum length requirement to reduce false positives
    $minLengthWithPadding = self::MINIMUM_BASE64_LENGTH - 2; // Account for padding
    $pattern = '/([a-zA-Z0-9+\/]{' . $minLengthWithPadding . ',}={1,2}|' .
               '[a-zA-Z0-9+\/]{' . self::MINIMUM_BASE64_LENGTH . ',})/';

    if ( preg_match_all( $pattern, $content, $matches ) ) {
      foreach ( $matches[1] as $candidate ) {
        if ( $this->isRawBase64( $candidate ) ) {
          return true;
        }
      }
    }

    return false;
  }


  /**
   * Validates if a string is properly formatted base64
   *
   * @param string $data The data to validate
   *
   * @return bool True if valid base64 format
   */
  private function isValidBase64( string $data ): bool {
    // Remove whitespace for validation
    $cleanData = preg_replace( '/\s+/', '', $data );

    if ( ! $cleanData ) {
      return false;
    }

    // Check minimum length to reduce false positives
    if ( strlen( $cleanData ) < self::MINIMUM_BASE64_LENGTH ) {
      return false;
    }

    // Check if string contains only valid base64 characters
    if ( ! preg_match( '/^[a-zA-Z0-9+\/]*={0,2}$/', $cleanData ) ) {
      return false;
    }

    // Check if length is multiple of 4 (base64 requirement)
    if ( strlen( $cleanData ) % 4 !== 0 ) {
      return false;
    }

    // Verify it can be decoded
    $decoded = base64_decode( $cleanData, true );
    if ( $decoded === false ) {
      return false;
    }

    // Re-encode and compare to ensure it's actually base64
    $reencoded           = base64_encode( $decoded );
    $normalizedOriginal  = rtrim( $cleanData, '=' );
    $normalizedReencoded = rtrim( $reencoded, '=' );

    return $normalizedOriginal === $normalizedReencoded;
  }


}
