<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config;

interface AssetInterface {


  public function id(): string;


  /** @return ?string */
  public function src();


  /**
   * @return array<string>
   */
  public function dependencies(): array;


  public function supportsHMR(): bool;


}
