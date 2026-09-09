<?php

namespace WPML\Core\Component\Translation\Domain\TranslationEditor;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationEditorType;

class AteEditor implements EditorInterface {

  /** @var int|null */
  private $editorJobId;


  /**
   * @param int|null $editorJobId
   */
  public function __construct( $editorJobId = null ) {
    $this->editorJobId = $editorJobId;
  }


  public function get(): string {
    return TranslationEditorType::ATE;
  }


  /**
   * @return int|null
   */
  public function getEditorJobId() {
    return $this->editorJobId;
  }


}
