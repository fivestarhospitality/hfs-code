<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

/**
 * Factory for creating AteSyncOrderingService instances.
 */
class AteSyncOrderingServiceFactory {


    /**
     * Create an AteSyncOrderingService with default configuration.
     *
     * @return AteSyncOrderingService
     */
  public static function create(): AteSyncOrderingService {
      $priorityService = JobPriorityServiceFactory::createDefault();

      return new AteSyncOrderingService( $priorityService );
  }


}
