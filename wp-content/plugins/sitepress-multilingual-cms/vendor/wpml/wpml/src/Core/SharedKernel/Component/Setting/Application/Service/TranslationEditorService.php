<?php

namespace WPML\Core\SharedKernel\Component\Setting\Application\Service;

use WPML\Core\SharedKernel\Component\Setting\Application\Query\TranslationEditorQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class TranslationEditorService {

  /** @var TranslationEditorQueryInterface */
  private $translationEditorQuery;


  public function __construct(
    TranslationEditorQueryInterface $translationEditorQuery
  ) {
    $this->translationEditorQuery = $translationEditorQuery;
  }


  /** @return TranslationEditorSetting|null */
  public function getTranslationEditorSetting() {
    return $this->translationEditorQuery->getTranslationEditorSetting();
  }


}
