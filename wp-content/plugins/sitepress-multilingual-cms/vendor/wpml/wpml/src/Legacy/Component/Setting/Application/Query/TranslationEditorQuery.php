<?php

namespace WPML\Legacy\Component\Setting\Application\Query;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Query\TranslationEditorQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class TranslationEditorQuery implements TranslationEditorQueryInterface {

  const SITEPRESS_OPTIONS = 'icl_sitepress_settings';

  /** @var OptionsInterface */
  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  /**
   * @return TranslationEditorSetting|null
   */
  public function getTranslationEditorSetting() {
    /**
     * @var array{
     *   translation-management?: array{
     *     doc_translation_method?: string,
     *     post_translation_editor_native_for_post_type?: array<string, bool>|mixed,
     *     post_translation_editor_native?: bool|mixed
     *   }
     * } $rawSitepressOptions
     */
    $rawSitepressOptions = $this->options->get( self::SITEPRESS_OPTIONS );

    if ( ! isset( $rawSitepressOptions['translation-management']['doc_translation_method'] ) ) {
      return null;
    }

    $editorSettings = new TranslationEditorSetting(
      $this->getMappedTranslationEditorType(
        $rawSitepressOptions['translation-management']['doc_translation_method']
      ),
      $rawSitepressOptions['translation-management']['post_translation_editor_native'] ?? false,
      $rawSitepressOptions['translation-management']['post_translation_editor_native_for_post_type'] ?? []
    );

    if ( $editorSettings->getValue() === TranslationEditorSetting::ATE ) {
      $optionValue = $this->options->get( 'wpml-old-jobs-editor' );
      $editorSettings->setUseAteForOldTranslationsCreatedWithCte( $optionValue === 'ate' );
    }

    return $editorSettings;
  }


  private function getMappedTranslationEditorType( string $databaseValue ): string {
    $translationEditorValues = [
      'ATE' => TranslationEditorSetting::ATE,
      '0'   => TranslationEditorSetting::MANUAL,
      '1'   => TranslationEditorSetting::CLASSIC,
      '2'   => TranslationEditorSetting::PRO,
    ];

    return $translationEditorValues[ $databaseValue ];
  }


}
