<?php

namespace WPML\Core\Port\Persistence;

interface OptionsInterface {


  /**
   * @param string $optionName
   * @param mixed  $defaultValue
   *
   * @return mixed
   */
  public function get( string $optionName, $defaultValue = false );


  /**
   * @param string $optionName
   * @param mixed  $value
   * @param bool   $autoload
   *
   * @return void
   */
  public function save( string $optionName, $value, $autoload = false );


  /**
   * @param string $optionName
   *
   * @return void
   */
  public function delete( string $optionName );


  /**
   * Add option atomically (only if it doesn't exist)
   *
   * @param string $optionName
   * @param mixed  $value
   * @param bool   $autoload
   *
   * @return bool True if option was added, false if it already exists
   */
  public function add( string $optionName, $value, bool $autoload = true ): bool;


}
