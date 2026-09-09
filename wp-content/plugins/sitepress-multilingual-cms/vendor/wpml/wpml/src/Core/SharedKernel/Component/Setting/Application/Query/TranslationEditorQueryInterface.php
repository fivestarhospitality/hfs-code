<?php

namespace WPML\Core\SharedKernel\Component\Setting\Application\Query;

use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

interface TranslationEditorQueryInterface {


  /**
   * @return TranslationEditorSetting|null
   */
  public function getTranslationEditorSetting();


}
