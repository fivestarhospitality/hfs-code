<?php

namespace WPML\Core\Component\Translation\Domain\Priority;

/**
 * Represents the ordering payload to send to ATE API.
 */
class OrderingPayload {

    const MODE_WPML_PRIORITY_V1 = 'wpml_priority_v1';

    /** @var string */
    private $mode;

    /** @var int|null */
    private $homePostId;

    /** @var array<int, int> */
    private $positions;

    /** @var array<int, array{tier: int, rank: array<int|string>}> */
    private $meta;


    /**
     * @param string                                                 $mode
     * @param int|null                                               $homePostId
     * @param array<int, int>                                        $positions
     * @param array<int, array{tier: int, rank: array<int|string>}> $meta
     */
  public function __construct(
        string $mode,
        $homePostId,
        array $positions,
        array $meta
    ) {
      $this->mode       = $mode;
      $this->homePostId = $homePostId;
      $this->positions  = $positions;
      $this->meta       = $meta;
  }


  public function getMode(): string {
      return $this->mode;
  }


    /**
     * @return int|null
     */
  public function getHomePostId() {
      return $this->homePostId;
  }


    /**
     * @return array<int, int>
     */
  public function getPositions(): array {
      return $this->positions;
  }


    /**
     * @return array<int, array{tier: int, rank: array<int|string>}>
     */
  public function getMeta(): array {
      return $this->meta;
  }


    /**
     * Convert to array format for ATE API.
     *
     * @return array{mode: string, home_post_id: int|null, positions: array<string, int>, meta: array<string, array{tier: int, rank: array<int|string>}>}
     */
  public function toArray(): array {
      $stringKeys = array_map( 'strval', array_keys( $this->positions ) );
      /** @var array<string, int> $positionsWithStringKeys */
      $positionsWithStringKeys = array_combine( $stringKeys, array_values( $this->positions ) ) ?: [];

      $metaStringKeys = array_map( 'strval', array_keys( $this->meta ) );
      /** @var array<string, array{tier: int, rank: array<int|string>}> $metaWithStringKeys */
      $metaWithStringKeys = array_combine( $metaStringKeys, array_values( $this->meta ) ) ?: [];

      return [
          'mode'         => $this->mode,
          'home_post_id' => $this->homePostId,
          'positions'    => $positionsWithStringKeys,
          'meta'         => $metaWithStringKeys,
      ];
  }


}
