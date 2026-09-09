<?php

namespace WPML\Core\Component\ReportContentStats\Domain\Repository;

interface DailyTranslationCountRepositoryInterface {


  public function getCount(): int;


  /** @return void */
  public function increment();


}
