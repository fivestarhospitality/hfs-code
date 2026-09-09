<?php

namespace WPML\Core\SharedKernel\Component\Item\Application\Query\Dto;

class UntranslatedTypeCountDto {

  /** @var string */
  private $namePlural;

  /** @var string */
  private $nameSingular;

  /** @var int */
  private $count;

  /** @var 'post'|'package'|'string' */
  private $kind;

  /** @var string */
  private $type;


  /**
   * @param 'post'|'package'|'string' $kind
   */
  public function __construct(
    string $namePlural,
    string $nameSingular,
    int $count,
    $kind,
    string $type = ''
  ) {
    $this->namePlural   = $namePlural;
    $this->nameSingular = $nameSingular;
    $this->count        = $count;
    $this->kind         = $kind;
    $this->type         = $type;
  }


  public function getNamePlural(): string {
    return $this->namePlural;
  }


  public function getNameSingular(): string {
    return $this->nameSingular;
  }


  public function getCount(): int {
    return $this->count;
  }


  /**
   * @return array{namePlural: string, nameSingular: string, count: int, kind:string, type: string}
   */
  public function toArray(): array {
    return [
      'namePlural'   => $this->namePlural,
      'nameSingular' => $this->nameSingular,
      'count'        => $this->count,
      'kind'         => $this->kind,
      'type'         => $this->type,
    ];
  }


}
