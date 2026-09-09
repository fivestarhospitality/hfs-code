<?php

namespace WPML\Core\Component\Translation\Domain\Entity;

class JobError {

  /** @var int */
  private $jobId;

  /** @var int */
  private $ateJobId;

  /** @var string */
  private $errorType;

  /** @var string */
  private $errorMessage;

  /** @var array<string, mixed> */
  private $errorData;

  /** @var int */
  private $counter;


  /**
   * @param int $jobId
   * @param int $ateJobId
   * @param string $errorType
   * @param string $errorMessage
   * @param array<string, mixed> $errorData
   * @param int $counter
   */
  public function __construct(
    int $jobId,
    int $ateJobId,
    string $errorType,
    string $errorMessage,
    array $errorData = [],
    int $counter = 1
  ) {
    $this->jobId = $jobId;
    $this->ateJobId = $ateJobId;
    $this->errorType = $errorType;
    $this->errorMessage = $errorMessage;
    $this->errorData = $errorData;
    $this->counter = $counter;
  }


  public function getJobId(): int {
    return $this->jobId;
  }


  public function getAteJobId(): int {
    return $this->ateJobId;
  }


  public function getErrorType(): string {
    return $this->errorType;
  }


  public function getErrorMessage(): string {
    return $this->errorMessage;
  }


  /**
   * @return array<string, mixed>
   */
  public function getErrorData(): array {
    return $this->errorData;
  }


  public function getCounter(): int {
    return $this->counter;
  }


}
