<?php

namespace WPML\TM\ATE\Download;

class Job {

	/** @var int $ateJobId */
	public $ateJobId;

	/** @var string $url */
	public $url;

	/** @var int */
	public $ateStatus;

	/**
	 * This property is not part of the database data,
	 * but it can be added when the job is downloaded
	 * to provide more information to the UI.
	 *
	 * @var int $jobId
	 */
	public $jobId;

	/** @var int */
	public $status = ICL_TM_IN_PROGRESS;

	/** @var bool  if true the job is unsolvable and need to be resent */
	public $isUnsolvable = false;

	/** @var string  */
	public $message = '';

	/** @var string|null  */
	public $errorType = null;

	/** @var object|null  */
	public $errorData = null;

	/** @var int|null  */
	public $originalElementId = null;

	/** @var int|null  */
	public $elementId = null;

	/**
	 * @param \stdClass $item
	 *
	 * @return Job
	 */
	public static function fromAteResponse( \stdClass $item ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$job               = new self();
		$job->ateJobId     = $item->ate_id;
		$job->url          = $item->download_link;
		$job->ateStatus    = (int) $item->status;
		$job->isUnsolvable = (bool) ( $item->is_unsolvable ?? false );
		$job->message      = $item->message ?? '';
		$job->jobId        = (int) $item->id;
		if ( $job->isUnsolvable ) {
			$job->errorType = 'SyncError';
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $job;
	}

	/**
	 * @param \stdClass $row
	 *
	 * @return Job
	 */
	public static function fromDb( \stdClass $row ) {
		$job           = new self();
		$job->ateJobId = $row->editor_job_id;
		$job->url      = $row->download_url;

		return $job;
	}
}
