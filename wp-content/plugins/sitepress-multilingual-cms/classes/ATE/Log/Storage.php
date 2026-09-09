<?php

namespace WPML\TM\ATE\Log;

use WPML\Collect\Support\Collection;
use WPML\WP\OptionManager;

class Storage {

	const OPTION_GROUP = 'TM\ATE\Log';
	const OPTION_NAME  = 'logs';
	const MAX_ENTRIES  = 50;

	public static function add( Entry $entry, $avoidDuplication = false ) {
		$entry->timestamp = $entry->timestamp ?: time();

		// Mirror to JobLog so ATE-log entries surface in the same diagnostic
		// UI as everything else we instrumented. Storage::add is the single
		// chokepoint for ATE-log writes (Download/Process catch blocks,
		// FixJob, ATE API failures, SitekeyLogger, retry actions, …) — one
		// mirror here covers all callers without touching each site. Wrapped
		// defensively so JobLog cannot interfere with ATE-log writing.
		self::mirrorToJobLog( $entry );

		$entries = self::getAll();

		if ( $avoidDuplication ) {
			$entries = $entries->reject(
				function( $iteratedEntry ) use ( $entry ) {
					return (
					$iteratedEntry->wpmlJobId === $entry->wpmlJobId
					&& $entry->ateJobId === $iteratedEntry->ateJobId
					&& $entry->description === $iteratedEntry->description
					&& $entry->eventType === $iteratedEntry->eventType
					);
				}
			);
		}

		$entries->prepend( $entry );

		$newOptionValue = $entries->forPage( 1, self::MAX_ENTRIES )
								->map(
									function( Entry $entry ) {
										return (array) $entry; }
								)
								  ->toArray();
		OptionManager::updateWithoutAutoLoad( self::OPTION_NAME, self::OPTION_GROUP, $newOptionValue );
	}

	/**
	 * @param Entry $entry
	 */
	public static function remove( Entry $entry ) {
		$entries        = self::getAll();
		$entries        = $entries->reject(
			function( $iteratedEntry ) use ( $entry ) {
				return $iteratedEntry->timestamp === $entry->timestamp && $entry->ateJobId === $iteratedEntry->ateJobId;
			}
		);
		$newOptionValue = $entries->forPage( 1, self::MAX_ENTRIES )
				->map(
					function( Entry $entry ) {
						return (array) $entry; }
				)
				->toArray();
		OptionManager::updateWithoutAutoLoad( self::OPTION_NAME, self::OPTION_GROUP, $newOptionValue );
	}

	/**
	 * @return Collection Collection of Entry objects.
	 */
	public static function getAll() {
		return wpml_collect( OptionManager::getOr( [], self::OPTION_NAME, self::OPTION_GROUP ) )
			->map(
				function( array $item ) {
					return new Entry( $item );
				}
			);
	}

	public function getCount(): int {
		return count( OptionManager::getOr( [], self::OPTION_NAME, self::OPTION_GROUP ) );
	}

	/**
	 * Mirror an ATE log entry into JobLog so it appears in the new admin
	 * diagnostic UI alongside the rest of our instrumentation.
	 *
	 * Entries with a non-empty description are treated as error-context
	 * (logException / logError typically populate description with the
	 * exception message), which flips the hosting request's hasErrorLogs
	 * flag and surfaces a red badge in the joblog list. Entries without a
	 * description (createForType for informational JOBS_SYNC events etc.)
	 * are mirrored as plain INFO.
	 *
	 * The `wpmlJobId` and `ateJobId` keys are recognised by JobLog's entity
	 * ID extractor (mapped to `rids` and `ate_job_ids` buckets), so a
	 * findRequestsByEntity('rid', X) query returns ATE-log-driven errors
	 * automatically.
	 *
	 * @param Entry $entry
	 *
	 * @return void
	 */
	private static function mirrorToJobLog( Entry $entry ) {
		try {
			if ( ! class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
				return;
			}

			$data = [
				'description' => $entry->description,
				'event_type'  => $entry->eventType,
				'wpmlJobId'   => $entry->wpmlJobId,
				'ateJobId'    => $entry->ateJobId,
				'extraData'   => $entry->extraData,
			];

			$eventLabel = 'ate_log_event_type_' . (int) $entry->eventType;

			if ( ! empty( $entry->description ) ) {
				\WPML\TM\Jobs\JobLog::addError( $eventLabel, $data );
			} else {
				\WPML\TM\Jobs\JobLog::add( $eventLabel, $data );
			}
		} catch ( \Throwable $e ) {
			// Defensive — never let a JobLog malfunction break ATE logging.
		}
	}
}
