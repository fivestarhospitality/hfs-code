<?php
// phpcs:disable Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition,Squiz.Commenting.FunctionComment.ParamCommentFullStop,Squiz.Commenting.InlineComment.InvalidEndChar,Squiz.PHP.CommentedOutCode.Found,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.YodaConditions.NotYoda,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.json_encode_json_encode,WordPress.WP.AlternativeFunctions.rename_rename,WordPress.WP.AlternativeFunctions.unlink_unlink


namespace WPML\TM\Jobs;

class FsJobLogStorage {

	/**
	 * Default cap on the joblog queue directory's total size (bytes).
	 * Operators can override this from the admin UI via the size badge
	 * popup; getMaxTotalBytes() returns the configured value when set,
	 * this constant when not.
	 */
	const MAX_TOTAL_BYTES = 52428800;   // 50 MiB

	/**
	 * Default ceiling on file count. The admin UI exposes an override on
	 * the same popup as the size cap; getMaxStoredRequestsCount() reads
	 * the configured value when set. Even if every request produces tiny
	 * files we never want unbounded count — getRequestSummaries() walks
	 * every file.
	 */
	const MAX_STORED_REQUESTS_COUNT = 1500;

	/**
	 * Operator-configurable cap overrides. Stored on the sitepress
	 * settings object so they move with the rest of the JobLog feature
	 * toggle (and inherit its multisite behaviour). Zero / unset / non-
	 * positive values fall back to the MAX_* constants above.
	 */
	const OPTION_MAX_MB    = 'wpml_tm_job_log_max_mb';
	const OPTION_MAX_FILES = 'wpml_tm_job_log_max_files';

	/**
	 * Hard sanity bounds on the operator inputs to prevent typos that
	 * would either disable the cap (huge value) or pin it useless-small.
	 * Range chosen wide enough to cover every realistic VIP-incident
	 * retention need without permitting a runaway disk fill.
	 */
	const MIN_CONFIGURABLE_MB    = 1;
	const MAX_CONFIGURABLE_MB    = 10000;   // 10 GiB ceiling
	const MIN_CONFIGURABLE_FILES = 50;
	const MAX_CONFIGURABLE_FILES = 100000;

	/** Fraction of files to drop when either cap is exceeded. */
	const PRUNE_RATIO = 0.25;

	/** Seconds after which an `.in_progress` file is treated as stuck. */
	const DEFAULT_STUCK_THRESHOLD_SECONDS = 300;

	/**
	 * Seconds after which an `.in_progress` file is considered abandoned and
	 * eligible for prune-time deletion. Deliberately much longer than the
	 * stuck-UI threshold (300 s): a stuck file is useful diagnostic evidence
	 * we want to preserve in the UI, but past one hour we accept it as dead
	 * weight from a long-gone worker and reclaim it when caps trip.
	 * Comfortably exceeds any realistic PHP max_execution_time.
	 */
	const ABANDONED_IN_PROGRESS_SECONDS = 3600;

	/** Request lifecycle states surfaced by getRequestSummaries(). */
	const STATUS_COMPLETE    = 'complete';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_STUCK       = 'stuck';
	const STATUS_LEGACY      = 'legacy';
	/**
	 * Request finalised via the PHP shutdown fallback because the WP
	 * shutdown action chain aborted on a fatal — file carries a
	 * `php_fatal` event and a synthesised request_finished with
	 * `aborted: true`.
	 */
	const STATUS_ABORTED = 'aborted';

	/**
	 * Test-only override for the queue directory location. When set, every
	 * read/write/prune operation in this class points at the override path
	 * instead of the real WP_LANG_DIR/wpml/joblog/ tree. Production code
	 * paths never touch this — only the test harness calls
	 * setQueueDirForTests() before running, and clears it in tearDown().
	 *
	 * @var string|null
	 */
	private static $queueDirOverride = null;

	// ---------------------------------------------------------------------
	// Write side — unchanged from the streaming refactor.
	// ---------------------------------------------------------------------

	/**
	 * Open a new NDJSON stream for the current request.
	 *
	 * The file is created as `.in_progress.ndjson`; finaliseStream() renames it
	 * to `.complete.ndjson` on a clean shutdown. If a worker dies before
	 * finalisation, the file stays `.in_progress` and is detectable as stuck.
	 *
	 * @param string $logUid           Stable per-request identifier (`uniqid()`).
	 * @param float  $requestStartTime Unix timestamp with microseconds.
	 *
	 * @return array{0: resource|null, 1: string} [$fp, $inProgressFilepath]
	 */
	public static function openRequestStream( $logUid, $requestStartTime ) {
		self::ensureQueueDir();

		$filename = self::generateFilename( $logUid, $requestStartTime, 'in_progress' );
		$filepath = self::getFilepath( $filename );

		$fp = @fopen( $filepath, 'ab' );
		if ( ! is_resource( $fp ) ) {
			return [ null, $filepath ];
		}

		// Disable PHP's userspace stream buffer so every fwrite() becomes a write(2)
		// syscall; this is what makes lines survive a worker crash without an
		// explicit fflush() per call.
		stream_set_write_buffer( $fp, 0 );

		$chmod = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		@chmod( $filepath, $chmod );

		return [ $fp, $filepath ];
	}

	/**
	 * Append one NDJSON line to an open request stream.
	 *
	 * @param resource $fp
	 * @param array    $line
	 *
	 * @return bool True if the full line was written. False on encode failure or
	 *              partial/failed fwrite (disk full, broken pipe, etc.).
	 */
	public static function writeLine( $fp, array $line ) {
		if ( ! is_resource( $fp ) ) {
			return false;
		}

		$json = json_encode( $line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return false;
		}

		return self::writeEncodedLine( $fp, $json );
	}

	/**
	 * Append a pre-encoded JSON string to the stream. Lets callers that have
	 * already paid the encode cost (e.g. JobLog::capLineSize() needs the
	 * encoded bytes to measure line size) avoid encoding a second time.
	 *
	 * @param resource $fp
	 * @param string   $json A complete JSON object — no trailing newline.
	 *
	 * @return bool True if the full line was written.
	 */
	public static function writeEncodedLine( $fp, $json ) {
		if ( ! is_resource( $fp ) || ! is_string( $json ) ) {
			return false;
		}
		$payload = $json . "\n";
		$written = @fwrite( $fp, $payload );
		return is_int( $written ) && $written === strlen( $payload );
	}

	/**
	 * Close the stream and atomically transition `.in_progress` → `.complete`.
	 *
	 * @param resource|null $fp
	 * @param string|null   $inProgressPath
	 *
	 * @return bool True on successful rename. False if the file is missing, the
	 *              path is invalid, or rename() itself fails.
	 */
	public static function finaliseStream( $fp, $inProgressPath ) {
		if ( is_resource( $fp ) ) {
			@fclose( $fp );
		}

		if ( ! is_string( $inProgressPath ) || $inProgressPath === '' ) {
			return false;
		}

		if ( ! file_exists( $inProgressPath ) ) {
			return false;
		}

		$completePath = self::deriveCompletePath( $inProgressPath );
		$renamed      = @rename( $inProgressPath, $completePath );

		self::pruneIfLimitsExceeded();

		return (bool) $renamed;
	}

	// ---------------------------------------------------------------------
	// Read side — event-native API.
	// ---------------------------------------------------------------------

	/**
	 * Lightweight metadata for every stored request (complete, in-progress,
	 * stuck, and legacy). Each entry summarises the file *without* loading its
	 * full event stream, so this is the right entry point for list views.
	 *
	 * @return array<int, array{
	 *     logUid: string,
	 *     requestUrl: string,
	 *     requestDateTime: string,
	 *     startedAt: float|null,
	 *     finishedAt: float|null,
	 *     durationMs: int|null,
	 *     hasErrorLogs: bool,
	 *     status: string,
	 *     php_pid: int|null,
	 *     ageSeconds: int,
	 *     filePath: string,
	 *     format: string,
	 * }>
	 */
	public static function getRequestSummaries() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files = array_merge(
			glob( $dir . '*.complete.ndjson' ) ?: [],
			glob( $dir . '*.in_progress.ndjson' ) ?: [],
			self::globLegacyJsonFiles( $dir )
		);

		// Newest first by filename — both formats embed the timestamp at the
		// start, so lexicographic order = chronological order within each
		// format. Cross-format ordering is best-effort.
		rsort( $files );

		$now       = time();
		$summaries = [];

		foreach ( $files as $file ) {
			$summary = self::isLegacyJsonFile( $file )
				? self::summariseLegacyJson( $file, $now )
				: self::summariseNdjson( $file, $now );

			if ( is_array( $summary ) ) {
				$summaries[] = $summary;
			}
		}

		return $summaries;
	}

	/**
	 * Stream the events of a single request. Returns a generator so the caller
	 * never has to hold the full event list in memory.
	 *
	 * Legacy `.json` files are translated on-the-fly into the new event shape
	 * (request_started → group_started/log/group_finished* → request_finished)
	 * so consumers can be format-agnostic.
	 *
	 * @param string $logUid
	 *
	 * @return \Generator yielding `array` events
	 */
	public static function readEvents( $logUid ) {
		$file = self::findFileByLogUid( $logUid );
		if ( $file === null ) {
			return;
		}

		if ( self::isLegacyJsonFile( $file ) ) {
			foreach ( self::synthesiseEventsFromLegacyJson( $file ) as $event ) {
				yield $event;
			}
			return;
		}

		$fp = @fopen( $file, 'rb' );
		if ( ! is_resource( $fp ) ) {
			return;
		}

		// Per-file resolver: writer stores the full trace inline on its first
		// occurrence and a `traceHash` reference on subsequent ones. Because
		// the inline copy is always emitted first, a single forward scan with
		// a local map is enough to rehydrate every reference.
		$traceMap = [];

		while ( ( $rawLine = fgets( $fp ) ) !== false ) {
			$line = trim( $rawLine );
			if ( $line === '' ) {
				continue;
			}
			$event = json_decode( $line, true );
			if ( ! is_array( $event ) ) {
				continue;
			}

			if ( isset( $event['traceHash'] ) ) {
				$hash = (string) $event['traceHash'];
				if ( isset( $event['trace'] ) && is_array( $event['trace'] ) ) {
					$traceMap[ $hash ] = $event['trace'];
				} elseif ( isset( $traceMap[ $hash ] ) ) {
					$event['trace'] = $traceMap[ $hash ];
				}
				unset( $event['traceHash'] );
			}

			yield $event;
		}

		@fclose( $fp );
	}

	/**
	 * `.in_progress.ndjson` files older than the threshold — these represent
	 * worker crashes mid-request (the primary diagnostic surface for 6742-class
	 * race conditions).
	 *
	 * @param int $thresholdSeconds
	 *
	 * @return array<int, array> Same shape as getRequestSummaries() entries.
	 */
	public static function getStuckRequests( $thresholdSeconds = self::DEFAULT_STUCK_THRESHOLD_SECONDS ) {
		$dir = self::getQueueDir();
		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files = glob( $dir . '*.in_progress.ndjson' ) ?: [];
		$now   = time();

		$stuck = [];
		foreach ( $files as $file ) {
			$mtime = @filemtime( $file );
			if ( $mtime === false ) {
				continue;
			}
			if ( ( $now - $mtime ) < $thresholdSeconds ) {
				continue;
			}
			$summary = self::summariseNdjson( $file, $now );
			if ( is_array( $summary ) ) {
				$stuck[] = $summary;
			}
		}

		return $stuck;
	}

	/**
	 * Find requests that touched a given entity (e.g. rid, job_id, post_id,
	 * element_id, trid, target_lang).
	 *
	 * Fast path: the writer emits a per-request `ids` map on `request_finished`,
	 * which `summariseNdjson` exposes as `entityIds` on each summary. A query
	 * is an O(files) check against those sets — no event walking.
	 *
	 * Fallback: summaries without an `entityIds` field (legacy `.json`,
	 * `.in_progress` files, requests written before this index was added)
	 * fall back to a full event scan so results stay correct across the
	 * upgrade boundary.
	 *
	 * @param string     $idType e.g. "rid" or "rids", "element_id", "post_id".
	 * @param string|int $id
	 *
	 * @return array<int, array> Matching request summaries.
	 */
	public static function findRequestsByEntity( $idType, $id ) {
		$idType  = (string) $idType;
		$bucket  = substr( $idType, -1 ) === 's' ? $idType : $idType . 's';
		$idValue = (string) $id;
		$matches = [];

		foreach ( self::getRequestSummaries() as $summary ) {
			if ( isset( $summary['entityIds'] ) && is_array( $summary['entityIds'] ) ) {
				if ( self::indexedMatch( $summary['entityIds'], $bucket, $idValue ) ) {
					$matches[] = $summary;
				}
				continue;
			}

			// Backward-compat path: no entityIds → walk events.
			foreach ( self::readEvents( $summary['logUid'] ) as $event ) {
				if ( self::eventMatchesEntity( $event, $idType, $idValue ) ) {
					$matches[] = $summary;
					break;
				}
			}
		}

		return $matches;
	}

	/**
	 * Check the request-level index for a value in the given bucket. Loose
	 * string comparison because numeric IDs survive JSON round-trip as ints
	 * while query inputs typically arrive as strings.
	 *
	 * @param array  $entityIds  e.g. ['rids' => [5879, 5891], …]
	 * @param string $bucket     e.g. "rids"
	 * @param string $idValue    e.g. "5879"
	 *
	 * @return bool
	 */
	private static function indexedMatch( array $entityIds, $bucket, $idValue ) {
		if ( ! isset( $entityIds[ $bucket ] ) || ! is_array( $entityIds[ $bucket ] ) ) {
			return false;
		}
		foreach ( $entityIds[ $bucket ] as $candidate ) {
			if ( (string) $candidate === $idValue ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolved size cap (bytes). Returns the operator-configured override
	 * when set on the sitepress settings object, otherwise falls back to
	 * the MAX_TOTAL_BYTES default. Values outside the sanity range
	 * (MIN/MAX_CONFIGURABLE_MB) are treated as unset and ignored — the UI
	 * validates on save but we defend the resolver too in case raw option
	 * editing puts a bad value on disk.
	 *
	 * @return int
	 */
	public static function getMaxTotalBytes() {
		global $sitepress;
		if ( ! $sitepress ) {
			return self::MAX_TOTAL_BYTES;
		}
		$mb = (int) $sitepress->get_setting( self::OPTION_MAX_MB, 0 );
		if ( $mb < self::MIN_CONFIGURABLE_MB || $mb > self::MAX_CONFIGURABLE_MB ) {
			return self::MAX_TOTAL_BYTES;
		}
		return $mb * 1024 * 1024;
	}

	/**
	 * Resolved file-count cap. Same fallback / sanity behaviour as
	 * getMaxTotalBytes().
	 *
	 * @return int
	 */
	public static function getMaxStoredRequestsCount() {
		global $sitepress;
		if ( ! $sitepress ) {
			return self::MAX_STORED_REQUESTS_COUNT;
		}
		$files = (int) $sitepress->get_setting( self::OPTION_MAX_FILES, 0 );
		if ( $files < self::MIN_CONFIGURABLE_FILES || $files > self::MAX_CONFIGURABLE_FILES ) {
			return self::MAX_STORED_REQUESTS_COUNT;
		}
		return $files;
	}

	/**
	 * Total bytes used by every file in the joblog queue directory —
	 * `.complete.ndjson`, `.in_progress.ndjson`, and legacy `.json`. Shown
	 * in the admin toolbar next to the Clear-logs button so operators see
	 * the on-disk footprint at a glance.
	 *
	 * @return int
	 */
	public static function getTotalSize() {
		$dir = self::getQueueDir();
		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$total = 0;
		$files = array_merge(
			glob( $dir . '*.complete.ndjson' ) ?: [],
			glob( $dir . '*.in_progress.ndjson' ) ?: [],
			self::globLegacyJsonFiles( $dir )
		);
		foreach ( $files as $f ) {
			$total += (int) ( @filesize( $f ) ?: 0 );
		}
		return $total;
	}

	/**
	 * Human-readable byte size: bytes / KB / MB / GB depending on magnitude.
	 *
	 * @param int $bytes
	 *
	 * @return string
	 */
	public static function formatBytes( $bytes ) {
		$bytes = max( 0, (int) $bytes );
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1024 * 1024 ) {
			return number_format( $bytes / 1024, 1 ) . ' KB';
		}
		if ( $bytes < 1024 * 1024 * 1024 ) {
			return number_format( $bytes / 1024 / 1024, 1 ) . ' MB';
		}
		return number_format( $bytes / 1024 / 1024 / 1024, 2 ) . ' GB';
	}

	/**
	 * Remove all stored request log files (any format, any state).
	 *
	 * @return bool
	 */
	public static function clearAllLogs() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$files = array_merge(
			glob( $dir . '*.complete.ndjson' ) ?: [],
			glob( $dir . '*.in_progress.ndjson' ) ?: [],
			self::globLegacyJsonFiles( $dir )
		);

		foreach ( $files as $file ) {
			@unlink( $file );
		}

		return true;
	}

	/**
	 * Count the stored completed (and legacy) request logs. Used by the
	 * "logs in storage" UI counter — in-progress files are excluded.
	 *
	 * @return int
	 */
	public static function getLogsCount() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$files = array_merge(
			glob( $dir . '*.complete.ndjson' ) ?: [],
			self::globLegacyJsonFiles( $dir )
		);

		return count( $files );
	}

	// ---------------------------------------------------------------------
	// Read-side internals.
	// ---------------------------------------------------------------------

	/**
	 * Build a summary for an NDJSON file by scanning lines and picking out
	 * `request_started`, `request_finished`, and error log counts. Avoids the
	 * full tree reconstruction.
	 *
	 * @param string $file
	 * @param int    $now
	 *
	 * @return array|null
	 */
	private static function summariseNdjson( $file, $now ) {
		$isInProgress = self::isInProgressFile( $file );

		$fp = @fopen( $file, 'rb' );
		if ( ! is_resource( $fp ) ) {
			return null;
		}

		$requestStarted  = null;
		$requestFinished = null;
		$hasErrorLogs    = false;

		while ( ( $rawLine = fgets( $fp ) ) !== false ) {
			$line = trim( $rawLine );
			if ( $line === '' ) {
				continue;
			}
			$event = json_decode( $line, true );
			if ( ! is_array( $event ) || ! isset( $event['type'] ) ) {
				continue;
			}

			switch ( $event['type'] ) {
				case 'request_started':
					$requestStarted = $event;
					break;
				case 'request_finished':
					// On a completed file this line is authoritative for
					// hasErrorLogs / entityIds / duration — no need to keep
					// scanning the rest of the file just to count log lines.
					// In-progress files never reach this branch, so they
					// still take the full walk (their hasErrorLogs comes
					// from accumulating log-type events as before).
					$requestFinished = $event;
					break 2;
				case 'log':
					if ( ! $hasErrorLogs && isset( $event['logType'] ) && (int) $event['logType'] === 1 ) {
						$hasErrorLogs = true;
					}
					break;
			}
		}
		@fclose( $fp );

		if ( $requestStarted === null ) {
			return null;
		}

		$mtime   = @filemtime( $file );
		$fileAge = $mtime === false ? 0 : max( 0, $now - $mtime );

		if ( $isInProgress ) {
			$status = $fileAge >= self::DEFAULT_STUCK_THRESHOLD_SECONDS
				? self::STATUS_STUCK
				: self::STATUS_IN_PROGRESS;
		} else {
			$status = self::STATUS_COMPLETE;
		}

		$startedAt  = isset( $requestStarted['ts'] ) ? (float) $requestStarted['ts'] : null;
		$finishedAt = is_array( $requestFinished ) && isset( $requestFinished['ts'] )
			? (float) $requestFinished['ts']
			: null;
		$durationMs = null;
		if ( $startedAt !== null && $finishedAt !== null && $finishedAt >= $startedAt ) {
			$durationMs = (int) round( ( $finishedAt - $startedAt ) * 1000 );
		}

		if ( is_array( $requestFinished ) && isset( $requestFinished['hasErrorLogs'] ) ) {
			$hasErrorLogs = (bool) $requestFinished['hasErrorLogs'];
		}

		$entityIds = null;
		if (
			is_array( $requestFinished )
			&& isset( $requestFinished['ids'] )
			&& is_array( $requestFinished['ids'] )
		) {
			$entityIds = $requestFinished['ids'];
		}

		// Aborted requests carry an explicit marker on their synthesised
		// request_finished line. When set, override the status so the
		// admin list can paint these distinctly from clean completions.
		$aborted = is_array( $requestFinished ) && ! empty( $requestFinished['aborted'] );
		if ( $aborted ) {
			$status = self::STATUS_ABORTED;
		}

		return [
			'logUid'          => $requestStarted['logUid'] ?? '',
			'requestUrl'      => $requestStarted['requestUrl'] ?? '',
			'requestDateTime' => $requestStarted['requestDateTime'] ?? '',
			'startedAt'       => $startedAt,
			'finishedAt'      => $finishedAt,
			'durationMs'      => $durationMs,
			'hasErrorLogs'    => $hasErrorLogs,
			'status'          => $status,
			'php_pid'         => isset( $requestStarted['php_pid'] ) ? (int) $requestStarted['php_pid'] : null,
			'ageSeconds'      => $fileAge,
			'entityIds'       => $entityIds,
			'filePath'        => $file,
			'format'          => 'ndjson',
		];
	}

	/**
	 * Build a summary for a legacy single-blob JSON file.
	 *
	 * @param string $file
	 * @param int    $now
	 *
	 * @return array|null
	 */
	private static function summariseLegacyJson( $file, $now ) {
		$content = @file_get_contents( $file );
		if ( ! is_string( $content ) ) {
			return null;
		}
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$mtime   = @filemtime( $file );
		$fileAge = $mtime === false ? 0 : max( 0, $now - $mtime );

		return [
			'logUid'          => $decoded['logUid'] ?? '',
			'requestUrl'      => $decoded['requestUrl'] ?? '',
			'requestDateTime' => $decoded['requestDateTime'] ?? '',
			'startedAt'       => null,
			'finishedAt'      => null,
			'durationMs'      => null,
			'hasErrorLogs'    => ! empty( $decoded['hasErrorLogs'] ),
			'status'          => self::STATUS_LEGACY,
			'php_pid'         => null,
			'ageSeconds'      => $fileAge,
			'entityIds'       => null,
			'filePath'        => $file,
			'format'          => 'legacy_json',
		];
	}

	/**
	 * Locate the on-disk file for a given logUid across all known states and
	 * formats. Returns the first match found.
	 *
	 * @param string $logUid
	 *
	 * @return string|null
	 */
	private static function findFileByLogUid( $logUid ) {
		$dir = self::getQueueDir();
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		// New format: `..._<logUid>.{complete|in_progress}.ndjson`
		$matches = glob( $dir . '*_' . $logUid . '.*.ndjson' ) ?: [];
		if ( ! empty( $matches ) ) {
			return $matches[0];
		}

		// Legacy: `<ts>_<logUid>.json`
		$matches = glob( $dir . '*_' . $logUid . '.json' ) ?: [];
		foreach ( $matches as $candidate ) {
			if ( ! self::isLegacyJsonFile( $candidate ) ) {
				continue;
			}
			return $candidate;
		}

		return null;
	}

	/**
	 * Translate a legacy `.json` tree into a synthesised event stream so the
	 * event-native API stays format-agnostic.
	 *
	 * @param string $file
	 *
	 * @return \Generator
	 */
	private static function synthesiseEventsFromLegacyJson( $file ) {
		$content = @file_get_contents( $file );
		if ( ! is_string( $content ) ) {
			return;
		}
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		yield [
			'type'            => 'request_started',
			'logUid'          => $decoded['logUid'] ?? '',
			'requestUrl'      => $decoded['requestUrl'] ?? '',
			'requestParams'   => $decoded['requestParams'] ?? [],
			'requestDateTime' => $decoded['requestDateTime'] ?? '',
		];

		$groups = isset( $decoded['logsByGroup'] ) && is_array( $decoded['logsByGroup'] )
			? $decoded['logsByGroup']
			: [];

		foreach ( $groups as $group ) {
			yield [
				'type'    => 'group_started',
				'groupId' => $group['groupId'] ?? null,
				'label'   => $group['label'] ?? '',
				'data'    => isset( $group['data'] ) && is_array( $group['data'] ) ? $group['data'] : [],
			];

			$logs = isset( $group['logs'] ) && is_array( $group['logs'] ) ? $group['logs'] : [];
			foreach ( $logs as $log ) {
				$event = [ 'type' => 'log' ];
				foreach ( $log as $k => $v ) {
					$event[ $k ] = $v;
				}
				if ( ! isset( $event['groupId'] ) ) {
					$event['groupId'] = $group['groupId'] ?? null;
				}
				yield $event;
			}

			yield [
				'type'    => 'group_finished',
				'groupId' => $group['groupId'] ?? null,
			];
		}

		yield [
			'type'         => 'request_finished',
			'hasErrorLogs' => ! empty( $decoded['hasErrorLogs'] ),
		];
	}

	/**
	 * Match an event against an entity (idType=value) pair. Checks top-level
	 * keys (extraLogData lives there) then recurses into the event's `data`.
	 *
	 * @param array  $event
	 * @param string $idType
	 * @param string $idValue
	 *
	 * @return bool
	 */
	private static function eventMatchesEntity( array $event, $idType, $idValue ) {
		if ( isset( $event[ $idType ] ) && (string) $event[ $idType ] === $idValue ) {
			return true;
		}

		if ( isset( $event['data'] ) && is_array( $event['data'] ) ) {
			return self::arrayContainsId( $event['data'], $idType, $idValue );
		}

		return false;
	}

	/**
	 * Recursively look for a key=value match in an arbitrary array.
	 *
	 * @param array  $data
	 * @param string $idType
	 * @param string $idValue
	 *
	 * @return bool
	 */
	private static function arrayContainsId( array $data, $idType, $idValue ) {
		foreach ( $data as $k => $v ) {
			if ( $k === $idType && (string) $v === $idValue ) {
				return true;
			}
			if ( is_array( $v ) && self::arrayContainsId( $v, $idType, $idValue ) ) {
				return true;
			}
		}
		return false;
	}

	// ---------------------------------------------------------------------
	// Filesystem helpers.
	// ---------------------------------------------------------------------

	/**
	 * Prune-then-continue cap enforcement. If the queue directory exceeds
	 * EITHER limit (total bytes > MAX_TOTAL_BYTES, or file count >
	 * MAX_STORED_REQUESTS_COUNT), delete the oldest PRUNE_RATIO (25%) of
	 * eligible files in one pass.
	 *
	 * Eligible = `.complete.ndjson` + legacy `.json` + ABANDONED
	 * `.in_progress.ndjson` (mtime older than ABANDONED_IN_PROGRESS_SECONDS).
	 * Recent `.in_progress.ndjson` files are NEVER pruned — they may still
	 * be under active write by another worker, or stuck files we want to
	 * surface in the UI. Including abandoned in-progress files in both the
	 * trigger and the deletion pool prevents a directory dominated by
	 * crashed-worker debris from escaping the cap undetected.
	 *
	 * Called from JobLog::ensureStreamOpen() (before opening a new request's
	 * file) and from finaliseStream() (after closing one). Dynamic — no
	 * cache. Cost at 1500-file cap: ~10ms local SSD, ~150ms slow disk; runs
	 * twice per request maximum, only when logging is enabled.
	 *
	 * @return void
	 */
	public static function pruneIfLimitsExceeded() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$completedFiles  = array_merge(
			glob( $dir . '*.complete.ndjson' ) ?: [],
			self::globLegacyJsonFiles( $dir )
		);
		$inProgressFiles = glob( $dir . '*.in_progress.ndjson' ) ?: [];

		// Count both classes for the trigger so stuck-file accumulation
		// can't push the directory past the cap unnoticed.
		$fileCount = count( $completedFiles ) + count( $inProgressFiles );
		if ( $fileCount === 0 ) {
			return;
		}

		$maxFiles = self::getMaxStoredRequestsCount();
		$maxBytes = self::getMaxTotalBytes();

		$overCount = $fileCount > $maxFiles;

		// Skip the expensive total-size scan when file count is still well
		// below cap. With the per-line 64 KiB cap upstream, average request
		// files are small and total size is dominated by file count — so
		// below this threshold we can't realistically be at the size cap
		// either. Saves a full directory stat per request (~1500 filesize()
		// calls at full cap).
		$sizeCheckThreshold = (int) ceil( $maxFiles * 0.75 );
		$overSize           = false;
		if ( ! $overCount && $fileCount >= $sizeCheckThreshold ) {
			$overSize = self::getTotalSize() > $maxBytes;
		}

		if ( ! $overCount && ! $overSize ) {
			return;
		}

		// Filemtime only runs on the in-progress glob *after* we already
		// decided to prune — so the no-prune hot path stays free of the
		// per-file stat cost.
		$abandonedInProgress = self::filterAbandonedInProgress( $inProgressFiles );
		$eligible            = array_merge( $completedFiles, $abandonedInProgress );

		if ( empty( $eligible ) ) {
			return;
		}

		$toDeleteCount = (int) ceil( count( $eligible ) * self::PRUNE_RATIO );

		// Two-pass priority-aware prune: clean completions die first;
		// aborted + hasErrorLogs requests are preserved as long as
		// possible because they're the actionable diagnostic surface.
		// Legacy .json and abandoned .in_progress files count as
		// low-priority (no readable hasErrorLogs marker; old / dead).
		list( $lowPriority, $highPriority ) = self::partitionByPrunePriority( $eligible );

		sort( $lowPriority );
		sort( $highPriority );

		$victims = array_slice( $lowPriority, 0, $toDeleteCount );
		if ( count( $victims ) < $toDeleteCount ) {
			// Low-priority pool was exhausted — spill into the oldest
			// high-priority files. Only happens when the directory is
			// dominated by error/aborted requests, which is itself a
			// signal worth seeing (operators should investigate why).
			$stillNeed = $toDeleteCount - count( $victims );
			$victims   = array_merge( $victims, array_slice( $highPriority, 0, $stillNeed ) );
		}

		foreach ( $victims as $file ) {
			@unlink( $file );
		}
	}

	/**
	 * Split the prune-eligible file list into two pools so the prune can
	 * preserve diagnostic-bearing requests longer than routine clean ones.
	 *
	 * Low-priority (prune first):
	 *   - Clean `.complete.ndjson` (request_finished present, hasErrorLogs=false, no aborted flag)
	 *   - Legacy `.json` (no readable error marker; old format)
	 *   - Abandoned `.in_progress.ndjson` (older than ABANDONED_IN_PROGRESS_SECONDS — never finalised)
	 *
	 * High-priority (preserved as long as possible):
	 *   - `.complete.ndjson` with `request_finished.aborted = true`
	 *     (the PHP-fatal capture path — carries a php_fatal event inside)
	 *   - `.complete.ndjson` with `request_finished.hasErrorLogs = true`
	 *
	 * @param string[] $files
	 *
	 * @return array{0: string[], 1: string[]} [$low, $high]
	 */
	private static function partitionByPrunePriority( array $files ) {
		$low  = [];
		$high = [];
		foreach ( $files as $f ) {
			if ( self::isHighPriorityForPrune( $f ) ) {
				$high[] = $f;
			} else {
				$low[] = $f;
			}
		}
		return [ $low, $high ];
	}

	/**
	 * Cheap classifier: read only the file's tail and look at the last
	 * line for `request_finished`. Returns true when the envelope has
	 * `aborted: true` or `hasErrorLogs: true`. Anything else (no
	 * envelope, legacy file, abandoned in-progress, clean completion)
	 * is low priority.
	 *
	 * Cost: one `file_get_contents` with offset/length = a single read of
	 * up to 16 KiB per file. Fast enough that classifying 1500 files
	 * adds ~50-100 ms to a prune that only runs when caps are exceeded.
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	private static function isHighPriorityForPrune( $file ) {
		// Legacy + in-progress files don't carry a reliable hasErrorLogs
		// signal we can cheaply extract; classify as low priority.
		if ( self::isLegacyJsonFile( $file ) || self::isInProgressFile( $file ) ) {
			return false;
		}

		$finished = self::readRequestFinishedFromTail( $file );
		if ( ! is_array( $finished ) ) {
			return false;
		}
		if ( ! empty( $finished['aborted'] ) ) {
			return true;
		}
		if ( ! empty( $finished['hasErrorLogs'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Read the last line of a `.complete.ndjson` file looking for the
	 * `request_finished` envelope. JobLog::shutdown() writes it as the
	 * final line before the file rename, so this is reliably reachable
	 * by reading the last few KiB of the file rather than the whole
	 * thing.
	 *
	 * 16 KiB tail is well above any realistic request_finished line size
	 * (envelope has hasErrorLogs flag, capped requestParams, ids index,
	 * stringIdsByBatchId — all subject to MAX_LINE_BYTES = 64 KiB upstream
	 * but typically a few KiB at most).
	 *
	 * @param string $file
	 *
	 * @return array|null
	 */
	private static function readRequestFinishedFromTail( $file ) {
		$size = @filesize( $file );
		if ( ! $size || $size <= 0 ) {
			return null;
		}
		$chunkSize = (int) min( $size, 16384 );
		$offset    = $size - $chunkSize;
		$tail      = @file_get_contents( $file, false, null, $offset, $chunkSize );
		if ( ! is_string( $tail ) ) {
			return null;
		}
		$lines = preg_split( '/\n+/', trim( $tail ) );
		if ( empty( $lines ) ) {
			return null;
		}
		$last  = end( $lines );
		$event = json_decode( $last, true );
		if ( ! is_array( $event ) ) {
			return null;
		}
		if ( ( $event['type'] ?? '' ) !== 'request_finished' ) {
			return null;
		}
		return $event;
	}

	/**
	 * Filter an `.in_progress.ndjson` file list down to entries whose mtime
	 * is older than ABANDONED_IN_PROGRESS_SECONDS — i.e. workers that
	 * almost certainly will never call finaliseStream(). Recent files are
	 * left untouched so a live worker's stream is never yanked.
	 *
	 * @param string[] $files
	 *
	 * @return string[]
	 */
	private static function filterAbandonedInProgress( array $files ) {
		if ( empty( $files ) ) {
			return [];
		}
		$cutoff = time() - self::ABANDONED_IN_PROGRESS_SECONDS;
		$out    = [];
		foreach ( $files as $f ) {
			$mtime = @filemtime( $f );
			if ( $mtime !== false && $mtime < $cutoff ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	/**
	 * `glob('*.json')` also matches `*.ndjson` because `.ndjson` ends in
	 * `.json`. This helper strips those out.
	 *
	 * @param string $dir
	 *
	 * @return string[]
	 */
	private static function globLegacyJsonFiles( $dir ) {
		$files = glob( $dir . '*.json' ) ?: [];
		return array_values(
            array_filter(
                $files,
                static function ( $f ) {
					return substr( $f, -7 ) !== '.ndjson';
				}
            )
        );
	}

	private static function isInProgressFile( $file ) {
		return substr( $file, -19 ) === '.in_progress.ndjson';
	}

	private static function isLegacyJsonFile( $file ) {
		return substr( $file, -5 ) === '.json' && substr( $file, -7 ) !== '.ndjson';
	}

	private static function ensureQueueDir() {
		$wpmlDir = self::getWpmlDir();
		if ( ! file_exists( $wpmlDir ) ) {
			@mkdir( $wpmlDir, 0777, true );
		}

		$queueDir = self::getQueueDir();
		if ( ! file_exists( $queueDir ) ) {
			@mkdir( $queueDir, 0777, true );
		}

		// WP_LANG_DIR is web-served on most stacks; NDJSON bodies contain
		// request URLs, params, stack traces, and HTTP body excerpts.
		// Block direct access at both directory levels.
		self::writeAccessGuards( $wpmlDir );
		self::writeAccessGuards( $queueDir );
	}

	/**
	 * Drop standard "silence is golden" + Apache deny guards into a directory
	 * if they aren't there yet. Cheap idempotent check; only writes on first
	 * encounter (or after the admin deleted the files).
	 *
	 * @param string $dir
	 *
	 * @return void
	 */
	private static function writeAccessGuards( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$indexPath = rtrim( $dir, '/\\' ) . '/index.php';
		if ( ! file_exists( $indexPath ) ) {
			@file_put_contents( $indexPath, "<?php\n// Silence is golden.\n" );
		}

		$htaccessPath = rtrim( $dir, '/\\' ) . '/.htaccess';
		if ( ! file_exists( $htaccessPath ) ) {
			// Apache 2.4 first, then 2.2 fallback. Covers the realistic stacks
			// where WP_LANG_DIR is served by Apache; nginx hosts must block
			// the directory in server config (this file is inert there).
			$body  = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
			$body .= "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
			@file_put_contents( $htaccessPath, $body );
		}
	}

	private static function getWpmlDir() {
		return WP_LANG_DIR . '/wpml/';
	}

	private static function getQueueDir() {
		if ( self::$queueDirOverride !== null ) {
			return rtrim( self::$queueDirOverride, '/\\' ) . '/';
		}

		$subdir = '';

		if ( is_multisite() ) {
			$subdir = get_current_blog_id() . '/';
		}

		return self::getWpmlDir() . 'joblog/' . $subdir;
	}

	/**
	 * Redirect all queue-dir operations to a custom path. Used by tests to
	 * point at a per-test temp directory so suites don't trample each other
	 * (or production logs). Pass null to clear the override.
	 *
	 * @internal Test use only.
	 *
	 * @param string|null $dir
	 *
	 * @return void
	 */
	public static function setQueueDirForTests( $dir ) {
		self::$queueDirOverride = $dir;
	}

	private static function getFilepath( $filename ) {
		return self::getQueueDir() . $filename;
	}

	/**
	 * Filename anatomy:
	 *   request_{Ymd-His}-{ms}_{pid}_{logUid}.{state}.ndjson
	 *
	 * @param string $logUid
	 * @param float  $requestStartTime
	 * @param string $state            in_progress|complete
	 *
	 * @return string
	 */
	private static function generateFilename( $logUid, $requestStartTime, $state ) {
		$ts   = (int) $requestStartTime;
		$date = gmdate( 'Ymd-His', $ts );
		$ms   = (int) ( fmod( $requestStartTime, 1 ) * 1000 );
		$pid  = function_exists( 'getmypid' ) ? getmypid() : 0;

		return 'request_' . $date . '-' . str_pad( (string) $ms, 3, '0', STR_PAD_LEFT )
			. '_' . $pid . '_' . $logUid
			. '.' . $state . '.ndjson';
	}

	private static function deriveCompletePath( $inProgressPath ) {
		return preg_replace( '/\.in_progress\.ndjson$/', '.complete.ndjson', $inProgressPath );
	}
}
