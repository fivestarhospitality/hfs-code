<?php

namespace WPML\Core\Component\Translation\Application\Event;

use WPML\Core\Port\Event\Event;

/**
 * Event triggered when translation jobs are cancelled.
 *
 * Contains minimal data needed for external integrations (e.g., ATE) to process cancellations.
 */
class JobsCancelledEvent extends Event {


  /**
   * @param array<object> $jobData Array of job data objects with job_id, editor, editor_job_id
   */
  public function __construct( array $jobData ) {
    parent::__construct( 'wpml_tm_jobs_cancelled', [ $jobData ] );
  }


}
