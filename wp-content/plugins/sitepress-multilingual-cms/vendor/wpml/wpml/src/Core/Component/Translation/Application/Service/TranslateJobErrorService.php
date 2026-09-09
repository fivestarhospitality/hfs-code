<?php
namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\Core\Component\Translation\Domain\Repository\JobErrorRepositoryInterface;

class TranslateJobErrorService {

  /** @var JobErrorRepositoryInterface $repository */
  private $repository;


  public function __construct( JobErrorRepositoryInterface $repository ) {
    $this->repository = $repository;
  }


  /**
   *
   * @param int    $jobId        The translation job ID.
   * @param int    $ateJobId     The ATE job ID.
   * @param string $errorType    The error type.
   * @param string $errorMessage The error message.
   * @param array<string, mixed> $errorData The error data.
   *
   * @return void
   */
  public function logError(
    int $jobId,
    int $ateJobId,
    string $errorType,
    string $errorMessage,
    array $errorData = []
  ) {

    $existing = $this->repository->findByJobId( $jobId );

    if ( $existing ) {
      if ( $existing->getErrorType() === $errorType && $existing->getErrorMessage() === $errorMessage ) {
        $this->repository->incrementCounter( $jobId );
        return;
      }

      $this->repository->delete( $jobId );
    }

    $jobError = new JobError(
      $jobId,
      $ateJobId,
      $errorType,
      $errorMessage,
      $errorData
    );

    $this->repository->insert( $jobError );
  }


  /**
   * Deletes a translation job error.
   *
   * @param int $jobId The translation job ID.
   *
   * @return void
   */
  public function deleteError( int $jobId ) {
    $this->repository->delete( $jobId );
  }


  /**
   * Get the count of all job errors.
   *
   * @return int
   */
  public function getCount(): int {
    return $this->repository->count();
  }


}
