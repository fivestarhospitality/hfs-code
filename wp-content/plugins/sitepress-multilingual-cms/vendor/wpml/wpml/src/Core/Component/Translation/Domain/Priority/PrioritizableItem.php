<?php

namespace WPML\Core\Component\Translation\Domain\Priority;

/**
 * Represents an item that can be prioritized for translation.
 */
class PrioritizableItem {

    /** @var int */
    private $id;

    /** @var ItemType */
    private $type;

    /** @var string|null */
    private $postType;

    /** @var int */
    private $postParent;

    /** @var int */
    private $menuOrder;

    /** @var string */
    private $postTitle;

    /** @var int */
    private $postModifiedGmt;

    /** @var string|null */
    private $stringDomain;

    /** @var string|null */
    private $stringContext;

    /** @var bool */
    private $isFeatured;

    /** @var string|null */
    private $stockStatus;


    /**
     * @param int         $id
     * @param ItemType    $type
     * @param string|null $postType
     * @param int         $postParent
     * @param int         $menuOrder
     * @param string      $postTitle
     * @param int         $postModifiedGmt
     * @param string|null $stringDomain
     * @param string|null $stringContext
     * @param bool        $isFeatured
     * @param string|null $stockStatus
     */
  public function __construct(
        int $id,
        ItemType $type,
        $postType = null,
        int $postParent = 0,
        int $menuOrder = 0,
        string $postTitle = '',
        int $postModifiedGmt = 0,
        $stringDomain = null,
        $stringContext = null,
        bool $isFeatured = false,
        $stockStatus = null
    ) {
      $this->id              = $id;
      $this->type            = $type;
      $this->postType        = $postType;
      $this->postParent      = $postParent;
      $this->menuOrder       = $menuOrder;
      $this->postTitle       = $postTitle;
      $this->postModifiedGmt = $postModifiedGmt;
      $this->stringDomain    = $stringDomain;
      $this->stringContext   = $stringContext;
      $this->isFeatured      = $isFeatured;
      $this->stockStatus     = $stockStatus;
  }


  public function getId(): int {
      return $this->id;
  }


  public function getType(): ItemType {
      return $this->type;
  }


  /**
   * @return string|null
   */
  public function getPostType() {
      return $this->postType;
  }


  public function getPostParent(): int {
      return $this->postParent;
  }


  public function getMenuOrder(): int {
      return $this->menuOrder;
  }


  public function getPostTitle(): string {
      return $this->postTitle;
  }


  public function getPostModifiedGmt(): int {
      return $this->postModifiedGmt;
  }


  /**
   * @return string|null
   */
  public function getStringDomain() {
      return $this->stringDomain;
  }


  /**
   * @return string|null
   */
  public function getStringContext() {
      return $this->stringContext;
  }


  public function isFeatured(): bool {
      return $this->isFeatured;
  }


  /**
   * @return string|null
   */
  public function getStockStatus() {
      return $this->stockStatus;
  }


  public function isPage(): bool {
      return $this->type->isPost() && $this->postType === 'page';
  }


  public function isProduct(): bool {
      return $this->type->isPost() && $this->postType === 'product';
  }


  public function isBlogPost(): bool {
      return $this->type->isPost() && $this->postType === 'post';
  }


  public function hasNoParent(): bool {
      return $this->postParent === 0;
  }


}
