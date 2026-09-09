<?php

namespace WPML\Core\Component\ReportContentStats\Domain\Repository;

interface EventReasonRepositoryInterface {


    /**
     * @return string|null
     */
  public function get();


    /**
     * @return void
     */
  public function set( string $reason );


    /**
     * @return void
     */
  public function clear();


}
