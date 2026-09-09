<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use WPML\Core\Port\Persistence\OptionsInterface;

class Options implements OptionsInterface {


  public function get( string $optionName, $defaultValue = false ) {
    return \get_option( $optionName, $defaultValue );
  }


  /**
   * @param string $optionName
   * @param mixed  $value
   * @param bool   $autoload
   *
   * @return void
   */
  public function save( string $optionName, $value, $autoload = false ) {
    \update_option( $optionName, $value, $autoload );
  }


  /**
   * @param string $optionName
   *
   * @return void
   */
  public function delete( string $optionName ) {
    \delete_option( $optionName );
  }


  /**
   * @param string $optionName
   * @param mixed  $value
   * @param bool   $autoload
   *
   * @return bool
   */
  public function add( string $optionName, $value, bool $autoload = true ): bool {
    return \add_option( $optionName, $value, '', $autoload ? 'yes' : 'no' );
  }


}
