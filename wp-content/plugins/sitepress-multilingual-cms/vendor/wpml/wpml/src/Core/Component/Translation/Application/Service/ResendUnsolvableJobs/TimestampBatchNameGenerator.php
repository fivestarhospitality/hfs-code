<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs;

/**
 * Generates batch names using timestamp prefix.
 */
class TimestampBatchNameGenerator {


  /**
   * Generate a batch name with timestamp.
   *
   * @return string Batch name in format "Resend-{timestamp}"
   */
  public function generate(): string {
    return 'Resend-' . time();
  }


}
