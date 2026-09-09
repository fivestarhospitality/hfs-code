<?php

namespace WPML\Infrastructure\WordPress\Component\UrlHandling\Application\Hook;

/**
 * Class SlugPercentEncodedFixAction
 *
 * Implements the percent-encoded slug fix.
 * This prevents empty slugs caused by invalid UTF-8 bytes after sanitize_title() processing.
 *
 * @package    wpml-core
 * @subpackage url-handling
 */
class SlugPercentEncodedFixAction {

  /**
   * @var bool Static flag to prevent recursion
   */
  private static $isProcessing = false;


  /**
   * Removes percent-encoded characters (e.g., %80) from URL slugs during translation processing.
   *
   * @param string|null $title     The sanitized title.
   * @param mixed       $rawTitle  The original title.
   * @param string      $context   The context for the sanitization.
   *
   * @return string|null The fixed title.
   */
  public function fixPercentInTranslationSlug(
    $title,
    $rawTitle = '',
    $context = 'save'
  ): ?string {
    if (
      self::$isProcessing
      || ! is_string( $rawTitle )
      || $context !== 'save'
      || $rawTitle === ''
      || strpos( $rawTitle, '%' ) === false
    ) {
      return $title;
    }

    $isTranslation = $this->isTranslationContext();

    if ( ! $isTranslation ) {
      return $title;
    }

    // If string is percent-encoded and decodes to valid UTF-8, use decoded version.
    // Otherwise, remove % only when followed by a digit.
    $decoded = rawurldecode( $rawTitle );
    if ( $decoded !== $rawTitle && mb_check_encoding( $decoded, 'UTF-8' ) ) {
      $rawTitle = $decoded;
    } else {
      $rawTitle = preg_replace( '/%(\d)/', '$1', $rawTitle ) ?? $rawTitle;
    }

    // Re-sanitize without this filter to avoid recursion.
    self::$isProcessing = true;
    $title              = sanitize_title( $rawTitle, '', $context );
    self::$isProcessing = false;

    return $title;
  }


  /**
   * Determines if we're currently in a WPML translation context.
   *
   * @return bool True if in translation context, false otherwise.
   */
  private function isTranslationContext(): bool {
    // WPML AJAX actions.
    // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
    if ( defined( 'DOING_AJAX' ) && isset( $_POST['action'] ) ) {
      // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
      $ajaxActions = [ 'wpml_translation_dialog_save_job' ];
      // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
      $action = is_string( $_POST['action'] ) ? $_POST['action'] : '';
      if ( in_array( sanitize_text_field( wp_unslash( $action ) ), $ajaxActions, true ) ) {
        return true;
      }
    }

    // WPML translation management POST vars.
    // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
    if ( isset( $_POST['icl_post_language'] ) || isset( $_POST['to_lang'] ) ) {
      return true;
    }

    // WPML translation editor save.
    // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
    if ( isset( $_POST['job_id'] ) && isset( $_POST['fields'] ) ) {
      return true;
    }

    // Non-default language in admin save context.
    if ( function_exists( 'wpml_get_current_language' ) && function_exists( 'wpml_get_default_language' ) ) {
      $current = wpml_get_current_language();
      $default = wpml_get_default_language();
      // @codingStandardsIgnoreLine WordPress.Security.NonceVerification.Missing
      if ( $current && $default && $current !== $default && isset( $_POST['trid'] ) ) {
        return true;
      }
    }

    return false;
  }


}
