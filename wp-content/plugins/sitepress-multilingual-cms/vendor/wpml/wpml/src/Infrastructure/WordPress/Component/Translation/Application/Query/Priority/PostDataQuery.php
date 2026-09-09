<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

/**
 * WordPress implementation for querying post data needed for priority sorting.
 *
 * @phpstan-type PostRow array{
 *   ID: string,
 *   post_type: string,
 *   post_parent: string,
 *   menu_order: string,
 *   post_title: string,
 *   post_modified_gmt: string
 * }
 */
class PostDataQuery implements PostDataQueryInterface {

    /** @phpstan-var QueryHandlerInterface<int, PostRow> $queryHandler */
    private $queryHandler;

    /** @var QueryPrepareInterface */
    private $queryPrepare;


    /**
     * @phpstan-param QueryHandlerInterface<int, PostRow> $queryHandler
     * @param QueryPrepareInterface                       $queryPrepare
     */
  public function __construct(
        QueryHandlerInterface $queryHandler,
        QueryPrepareInterface $queryPrepare
    ) {
      $this->queryHandler = $queryHandler;
      $this->queryPrepare = $queryPrepare;
  }


    /**
     * @param int[] $postIds
     *
     * @return array<array{
     *   ID: int,
     *   post_type: string,
     *   post_parent: int,
     *   menu_order: int,
     *   post_title: string,
     *   post_modified_gmt: string,
     *   is_featured?: bool,
     *   stock_status?: string|null
     * }>
     */
  public function getPostsData( array $postIds ): array {
    if ( empty( $postIds ) ) {
        return [];
    }
      $placeholders = implode( ',', array_fill( 0, count( $postIds ), '%d' ) );
      $sql          = "
			SELECT ID, post_type, post_parent, menu_order, post_title, post_modified_gmt
			FROM {$this->queryPrepare->prefix()}posts
			WHERE ID IN ($placeholders)
		";
      $sql          = $this->queryPrepare->prepare( $sql, ...$postIds );
    try {
        $results = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
        return [];
    }
      $postsData = [];
    foreach ( $results as $row ) {
        $postsData[] = [
            'ID'                => (int) $row['ID'],
            'post_type'         => $row['post_type'],
            'post_parent'       => (int) $row['post_parent'],
            'menu_order'        => (int) $row['menu_order'],
            'post_title'        => $row['post_title'],
            'post_modified_gmt' => $row['post_modified_gmt'],
            'is_featured'       => false,
            'stock_status'      => null,
        ];
    }
      return $postsData;
  }


    /**
     * @param int[] $postIds
     *
     * @return array<int, int> Map of post_id => post_parent
     */
  public function getParentMap( array $postIds ): array {
    if ( empty( $postIds ) ) {
        return [];
    }
      $placeholders = implode( ',', array_fill( 0, count( $postIds ), '%d' ) );
      $sql          = "
			SELECT ID, post_parent
			FROM {$this->queryPrepare->prefix()}posts
			WHERE ID IN ($placeholders)
		";
      $sql          = $this->queryPrepare->prepare( $sql, ...$postIds );
    try {
        $results = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
        return [];
    }
      $parentMap = [];
    foreach ( $results as $row ) {
        $parentMap[ (int) $row['ID'] ] = (int) $row['post_parent'];
    }
      return $parentMap;
  }


}
