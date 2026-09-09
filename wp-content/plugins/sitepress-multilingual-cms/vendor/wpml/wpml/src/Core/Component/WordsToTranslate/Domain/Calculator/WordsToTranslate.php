<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Calculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Ideogram\PrepareContent as PrepareContentIdeogram;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Letter\PrepareContent as PrepareContentLetter;
use WPML\Core\Component\WordsToTranslate\Domain\Config;
use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslation;

class WordsToTranslate {

  /** @var array<int,int> */
  private static $freshWordCount = [];

  /** @var Diff */
  private $diff;

  /** @var Count */
  private $count;

  /** @var PrepareContentLetter */
  private $prepareContentLetter;

  /** @var PrepareContentIdeogram */
  private $prepareContentIdeogram;


  public function __construct(
    Diff $diff,
    Count $count,
    PrepareContentLetter $prepareContentLetter,
    PrepareContentIdeogram $prepareContentIdeogram
  ) {
    $this->diff = $diff;
    $this->count = $count;
    $this->prepareContentLetter = $prepareContentLetter;
    $this->prepareContentIdeogram = $prepareContentIdeogram;
  }


  /** @return void */
  public function forLastTranslation( LastTranslation $lastTranslation, Item $original ) {
    $sourceLang = strtolower( $original->getSourceLang() );
    $prepare = $this->prepareContentLetter;

    $lastTranslationOriginalContent = $lastTranslation->getOriginalContent() ?? '';
    $isFreshTranslation = $lastTranslationOriginalContent === '';

    // Use cached fresh translation word count if available.
    // This way we avoid recalculating the same fresh content for multiple languages.
    if ( $isFreshTranslation && isset( self::$freshWordCount[ $original->getId() ] ) ) {
      $lastTranslation->setWordsToTranslate( self::$freshWordCount[ $original->getId() ] );
      return;
    }

    // Some languages use ideograms - these languages have a different calculation.
    $countFactor = 1;
    if ( isset( Config::LANGS[$sourceLang][Config::KEY_WORDS_PER_IDEOGRAM] ) ) {
      $prepare = $this->prepareContentIdeogram;
      $countFactor = Config::LANGS[$sourceLang][Config::KEY_WORDS_PER_IDEOGRAM];
    }

    $diff = $this->diff->diffArrays(
      $prepare->prepareForDiff( $lastTranslationOriginalContent ),
      $prepare->prepareForDiff( $original->getContent() ?? '' )
    );

    $count = (int) ( round( $this->count->wordsToTranslate( $diff ) * $countFactor ) );

    if ( $isFreshTranslation ) {
      self::$freshWordCount[ $original->getId() ] = $count;
    }

    $lastTranslation->setWordsToTranslate( $count );

    // Following is just for debugging.
    // $lastTranslation->setDiffWordsToOriginal( $diff );
  }


}
