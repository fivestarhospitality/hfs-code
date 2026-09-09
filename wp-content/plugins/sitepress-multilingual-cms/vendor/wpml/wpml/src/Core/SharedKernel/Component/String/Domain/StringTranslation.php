<?php

// phpcs:ignore PHPCompatibility.Keywords.ForbiddenNamesAsDeclared.stringFound
namespace WPML\Core\SharedKernel\Component\String\Domain;

class StringTranslation {

  /** @var int */
  private $id;

  /** @var int */
  private $stringId;

  /** @var string|null */
  private $value;

  /** @var string|null */
  private $moString;

  /** @var string */
  private $language;


  /**
   * @param int         $id
   * @param int         $stringId
   * @param string|null $value
   * @param string|null $moString
   * @param string      $language
   */
  public function __construct( int $id, int $stringId, $value, $moString, string $language ) {
    $this->id       = $id;
    $this->stringId = $stringId;
    $this->value    = $value;
    $this->moString = $moString;
    $this->language = $language;
  }


  public function getId(): int {
    return $this->id;
  }


  public function getStringId(): int {
    return $this->stringId;
  }


  /**
   * @return string|null
   */
  public function getValue() {
    return $this->value;
  }


  /**
   * @return string|null
   */
  public function getMoString() {
    return $this->moString;
  }


  public function getLanguage(): string {
    return $this->language;
  }


  public function isUntranslated(): bool {
    return empty( $this->value ) && empty( $this->moString );
  }


}
