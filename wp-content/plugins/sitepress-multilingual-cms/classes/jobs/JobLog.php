<?php
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionComment.ParamCommentFullStop,Squiz.Commenting.InlineComment.InvalidEndChar,Squiz.PHP.DisallowMultipleAssignments.Found,WordPress.DB.PreparedSQL.NotPrepared,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace,WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.YodaConditions.NotYoda,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.json_encode_json_encode


namespace WPML\TM\Jobs;

class JobLog {

	/**
	 * On-disk format (NDJSON, one JSON object per line):
	 *
	 *   { "type": "request_started",  "ts": …, "logUid": …, "requestUrl": …,
	 *     "requestParams": {…}, "requestDateTime": …, "pid": …, "blogId": … }
	 *   { "type": "group_started",    "ts": …, "groupId": 0, "label": …, "data": {…} }
	 *   { "type": "log",              "ts": …, "groupId": 0, "id": …,
	 *     "data": {…}, "trace": […], "logType": 0, …extraLogData }
	 *   { "type": "group_finished",   "ts": …, "groupId": 0 }
	 *   { "type": "request_finished", "ts": …, "hasErrorLogs": …,
	 *     "requestParams": {…enriched…}, "stringIdsByBatchId": {…} }
	 *
	 * File naming uses the `.in_progress.ndjson` → `.complete.ndjson` postfix
	 * pattern; the file is opened lazily on the first write and renamed at
	 * shutdown. A `.in_progress` file that survives is a smoking gun for a
	 * worker that died mid-request.
	 *
	 * The reader (FsJobLogStorage::getRequestSummaries / readEvents) exposes the
	 * data as either lightweight summaries or an event stream — the View
	 * consumes both directly, no tree reconstruction.
	 *
	 * Logs are grouped by logical groups. In case REST / ATE sync/download request there can be 1 logical group.
	 * In case admin "Send to translation" request there can be multiple logical groups, 1 group per each send_jobs function call.
	 * This allow to group logs into collapsible containers in the UI.
	 */

	/**
	 * Setting key — inverted semantics: present-and-true means the admin
	 * explicitly disabled JobLog. Absence or false = enabled.
	 *
	 * Defaulting to "on" lets us capture intermittent races on first
	 * occurrence; admins who want to opt out write this key via the UI
	 * toggle. No upgrade step needed: any site without the key gets the
	 * new default automatically.
	 */
	const IS_DISABLED_OPTION_NAME = 'wpml_tm_job_log_is_disabled';

	const LOG_TYPE_INFO  = 0;
	const LOG_TYPE_ERROR = 1;

	/** Sending content for translation – see translation-management.class.php. */
	const GROUP_ID_SEND_JOBS = 0;

	/** ATE sync – see /classes/ATE/Sync/Process.php. */
	const GROUP_ID_SYNC_JOBS = 1;

	/** ATE download – see /classes/ATE/Download/Process.php. */
	const GROUP_ID_DOWNLOAD_JOBS = 2;

	/** Site migration (copy/confirm) – see /classes/ATE/API/ClonedSites/Report.php. */
	const GROUP_ID_SITE_MIGRATION = 3;

	/** Cloned-site connect/disconnect – see /classes/ATE/API/ClonedSites/AutoMigration/Endpoints/{Connect,Disconnect}.php. */
	const GROUP_ID_CLONED_SITE_ACTIONS = 4;

	/**
	 * Translate Everything (TEA) lifecycle — mode toggle, poll-loop iterations,
	 * cancel-on-disable, and `wpml_after_save_post` refill.
	 * See /classes/ATE/Hooks/JobActions.php, /classes/ATE/TranslateEverything.php,
	 * /classes/automatic-translation/Actions.php.
	 */
	const GROUP_ID_TRANSLATE_EVERYTHING = 5;

	/**
	 * Job lifecycle mutations — admin-triggered Cancel/Retry/Fix REST actions
	 * that change a translation job's state. Critical for the 6742-style race
	 * diagnosis: cancel is the destructive operation that can race with an
	 * in-flight download.
	 * See /classes/API/REST/class-wpml-tm-rest-jobs.php (cancel_jobs).
	 */
	const GROUP_ID_JOB_LIFECYCLE = 6;

	const MAX_DEPTH         = 10;
	const MAX_STRING_LENGTH = 1000;
	const MAX_ARRAY_ITEMS   = 1000;

	/**
	 * Backstop cap for the JSON-encoded byte size of a single log line.
	 * dataToArray() bounds individual strings (1000 chars) and array widths
	 * (1000 items), but a top-level array of 1000 max-length strings still
	 * yields ~1 MiB per line — at that rate the 50 MiB storage cap is gone
	 * in ~50 lines. When a line exceeds this cap we replace the heavy
	 * payload fields (`data`, `requestParams`) with a `{ _truncated: true }`
	 * stub and append a `_line_oversized` marker so the reader knows why
	 * the body is missing.
	 */
	const MAX_LINE_BYTES = 65536;

	/**
	 * Soft cap on the number of `log` events one request may write. After
	 * this many `add()`/`addError()` calls land in the file, further
	 * invocations become no-ops and a single `events_truncated` marker is
	 * emitted so the operator knows the file is incomplete.
	 *
	 * Why it exists: the per-line cap (MAX_LINE_BYTES) protects against ONE
	 * runaway event, but not against a runaway *loop*. A `JobLog::add()`
	 * inside a 1 M-iteration foreach would still:
	 *   - pay 1 M × debug_backtrace() ≈ 30 s of CPU,
	 *   - write ~500 MB of NDJSON (the prune-on-finalise would clamp the
	 *     stored size but only after the worker burned the CPU writing it).
	 *
	 * 10 000 events is a generous ceiling: a heavy send_jobs is typically
	 * 50–500 events, a multi-content batch maybe a couple thousand. Anything
	 * past this is almost certainly a defect.
	 */
	const MAX_EVENTS_PER_REQUEST = 10000;

	/**
	 * Hard cap on the JSON-encoded byte size of a single `extraLogData`
	 * value. extraLogData is merged into EVERY subsequent log line in the
	 * group via `$line += self::$extraLogData`, so a 30 KB value attached
	 * to a 100-event group writes 3 MB of duplicated payload. `dataToArray`
	 * already bounds per-string and per-array width, but a top-level array
	 * of 1000 max-length strings still encodes to ~1 MB — which would then
	 * be paid per event.
	 *
	 * Values exceeding this cap are stored as a `{ _truncated: true,
	 * _too_big_for_extra_log_data: <original_bytes> }` stub instead.
	 */
	const MAX_EXTRA_LOG_DATA_BYTES = 1024;

	/**
	 * Cap on the raw bytes read from php://input during request-start
	 * parameter capture. A hostile or fat-payload POST (e.g. 50 MB JSON)
	 * would otherwise consume that much PHP memory before any log line is
	 * written. Beyond this, the raw body is discarded and requestParams
	 * carries an `_oversized_input` marker.
	 */
	const MAX_INPUT_BYTES = 1048576;

	/**
	 * Substrings matched (case-insensitive, with '-' normalised to '_') against
	 * array keys during dataToArray(). Any match replaces the value with
	 * '[REDACTED]'. The list intentionally errs toward over-redaction: a
	 * couple of false positives in debug fields is cheap; a leaked secret in
	 * logs is not. Tweak with care — adding overly broad terms like 'key'
	 * would clobber legitimate keys like cache_key or meta_key.
	 */
	const SECRET_KEY_NEEDLES = [
		'authorization',
		'cookie',
		'password',
		'passwd',
		'secret',
		'token',
		'bearer',
		'signature',
		'api_key',
		'apikey',
		'shared_key',
		'access_key',
		'private_key',
		'signing_key',
		'site_key',
		'license_key',
		'x_api',
		'x_auth',
	];

	/** @var bool Prevents activity before plugin init (notably during unit tests from classes $sitepress and $wpdb). */
	private static $isInitialised = false;

	/** @var bool Set when any addError() fires during this request. When it is setup request will render having error state in the logs UI. */
	private static $hasRequestAnyErrorLog = false;

	/** @var bool|null Memoised setting lookup. */
	private static $isEnabled;

	/**
	 * Stores current request URL. If this value is null that means request was never initialized.
	 * We want to log only explicit places in the code and avoid cases when we created some logs from some child class
	 * that called addLog out of the scope when we explicitly initialised the request. Such calls can create many
	 * useless entries in the database which give no value for the inspection of those logs and could make option entry grow fast.
	 * Example: we can log some method during send_jobs that can be used in some completelly different request and scenario.
	 *
	 * @var string|null
	 */
	private static $requestUrl;

	/** @var array */
	private static $requestParams = [];

	/** @var string|null ISO-8601 UTC. */
	private static $requestDateTime;

	/** @var string|null 13-char hex from uniqid(); also used by the download-by-uid UI. */
	private static $logUid;

	/** @var float|null Unix timestamp with microseconds. */
	private static $requestStartTime;

	/** @var int|null Currently open group, used as `groupId` on each log line. */
	private static $groupId;

	/**
	 * Extra data merged into each log entry. The caller should manually call removeExtraLog data to remove it.
	 * Otherwise it will be added to all next logs. It is needed to track some optional properties inside each log
	 * inside current group, for example current processed language during the step when we send content for translation.
	 *
	 * @var array<string, mixed>
	 */
	private static $extraLogData = [];

	/**
	 * Collected string batch IDs. We need to store them to select strings later and show string names and ids from each batch in UI.
	 *
	 * @var array<int>
	 */
	private static $stringBatchIds = [];

	/** @var array<int> Recursion guard(stores object ids) for dataToArray(); reset per top-level walk. */
	private static $alreadyAddedToLog = [];

	/**
	 * Per-request map of trace hashes already written inline. Subsequent
	 * occurrences of the same hash within this request are stored as a
	 * `traceHash` reference instead of the full frames array, so a heavy
	 * send_jobs loop pays the trace cost once instead of once-per-iteration.
	 *
	 * The reader (FsJobLogStorage::readEvents) rebuilds the same map as it
	 * walks the file and resolves references to the original frames — every
	 * `traceHash` reference is guaranteed to have a prior inline copy in the
	 * same file.
	 *
	 * @var array<string, true>
	 */
	private static $seenTraceHashes = [];

	/** @var resource|null */
	private static $fp;

	/** @var string|null Path of the `.in_progress.ndjson` file currently open. */
	private static $inProgressPath;

	/** @var bool Latched after first open attempt, success or failure. */
	private static $streamOpenAttempted = false;

	/** @var bool Set if fopen() failed; further writes become no-ops. */
	private static $streamOpenFailed = false;

	/** @var bool True once request_started has been written to disk. */
	private static $requestStartedWritten = false;

	/**
	 * True once request_finished has been written. Lets the PHP-level
	 * fatal-capture handler decide whether to emit a synthetic envelope
	 * — needed when WP's shutdown action chain aborted mid-fatal before
	 * our normal shutdown() ran.
	 *
	 * @var bool
	 */
	private static $requestFinishedWritten = false;

	/** @var bool One-shot guard: error_log on the first fwrite()/rename() failure only, to avoid spamming. */
	private static $writeFailureReported = false;

	/** @var bool One-shot guard: error_log on the first add() called outside a group. */
	private static $orphanAddReported = false;

	/** @var int Count of `log`-type events written via add() this request, used by the MAX_EVENTS_PER_REQUEST cap. */
	private static $logEventCount = 0;

	/** @var bool Latched once the MAX_EVENTS_PER_REQUEST cap fired so the truncation marker is emitted only once. */
	private static $eventsTruncatedReported = false;

	/** @var int|null Cached `getmypid()` for the lifetime of this PHP process. */
	private static $phpPid;

	/**
	 * Canonical map of recognised entity-ID keys → bucket name in the
	 * per-request entityIds index. Picked up wherever they appear:
	 *
	 *   - As an `addExtraLogData($key, …)` key.
	 *   - As any key (at any depth) inside a log's `$data` payload.
	 *
	 * The bucket layout matches what `getRequestSummaries()` exposes to
	 * callers — adding a new ID key is just one entry here.
	 */
	private static $ID_KEY_MAP = [
		// Translation status (icl_translation_status.rid)
		'rid'                  => 'rids',
		'wpmlJobId'            => 'rids',           // ATE responses; comment in Sync/Process.php confirms wpmlJobId IS the rid

		// Translation job (icl_translate_job.job_id)
		'job_id'               => 'job_ids',
		'jobId'                => 'job_ids',        // Sync/Download Job objects after Map::fromRid

		// External ATE job identifier
		'ateJobId'             => 'ate_job_ids',
		'ate_job_id'           => 'ate_job_ids',
		'editor_job_id'        => 'ate_job_ids',    // ate_repository getter

		// WP posts
		'post_id'              => 'post_ids',
		'new_post_id'          => 'post_ids',
		'source_post_id'       => 'post_ids',
		'original_doc_id'      => 'post_ids',
		'original_element_id'  => 'post_ids',
		'originalElementId'    => 'post_ids',

		// Element (post id when type=post, batch id when type=st-batch)
		'element_id'           => 'element_ids',
		'elementId'            => 'element_ids',

		// Translation group
		'trid'                 => 'trids',

		// Languages
		'target_lang'          => 'target_langs',
		'language_code'        => 'target_langs',   // Download Process appendNeedsReviewAndAutomaticValues
		'source_lang'          => 'source_langs',
		'source_language_code' => 'source_langs',

		// Element type (post / st-batch / package / …)
		'element_type'         => 'element_types',
	];

	/**
	 * Set-like accumulator: $idsTouched[bucket][value] = true. Built up by
	 * addExtraLogData() and add() during the request, emitted as a flat
	 * `ids` field on the request_finished line. Resetting happens implicitly
	 * at the PHP process boundary (one request = one process for JobLog).
	 *
	 * @var array<string, array<int|string, bool>>
	 */
	private static $idsTouched = [];

	public static function isSendJobsLogsGroup( $groupId ) {
		return self::GROUP_ID_SEND_JOBS === (int) $groupId;
	}

	public static function init() {
		self::$isInitialised = true;
		add_action( 'shutdown', [ __CLASS__, 'shutdown' ], PHP_INT_MAX );
		// Track edits to source posts that happen while translation jobs are
		// still in flight. The VIP credit-runaway case showed users editing
		// the source repeatedly before deliveries came back — each edit
		// creating new jobs that superseded the still-translating prior ones.
		// This event records the upstream trigger; translation_job_superseded
		// records the consequence on the job-creation side.
		add_action( 'wpml_after_save_post', [ __CLASS__, 'onSourcePostSaved' ], 10, 4 );

		// PHP-level fallback: WP's `shutdown` action runs as a single
		// register_shutdown_function callback (shutdown_action_hook). If
		// any hook in that chain triggers a fatal, the chain ABORTS and
		// every hook after the offending one — including our own at
		// PHP_INT_MAX priority — never runs, leaving the joblog file
		// stuck in `.in_progress` with no error context inside it.
		// This second register_shutdown_function runs after WP's, in
		// PHP's own shutdown sequence, even when the WP action chain
		// aborted on a fatal. It captures error_get_last(), writes a
		// `php_fatal` event into the still-open file, synthesises a
		// `request_finished` envelope with `aborted: true`, and renames
		// the file to `.complete` so it shows up in the admin list and
		// carries the actionable cause.
		register_shutdown_function( [ __CLASS__, 'onPhpShutdown' ] );
	}

	/**
	 * Capture a fatal that aborted the WP shutdown action chain and
	 * cleanly finalise the joblog file so the operator sees the cause
	 * directly in the log instead of having to cross-reference the PHP
	 * error log. No-op when the normal shutdown() ran to completion (in
	 * which case $fp is null and the file is already `.complete`).
	 *
	 * @return void
	 */
	public static function onPhpShutdown() {
		// Guard against multi-fire (some PHP setups call shutdown twice
		// in edge cases) and against running before init.
		static $alreadyRan = false;
		if ( $alreadyRan ) {
			return;
		}
		$alreadyRan = true;

		if ( ! self::wasRequestInitialised() ) {
			return;
		}

		// $fp is nulled by shutdown() after finaliseStream(). If it's
		// not a resource here, the normal completion path ran fine.
		if ( ! is_resource( self::$fp ) ) {
			return;
		}

		$error   = error_get_last();
		$isFatal = is_array( $error )
			&& in_array(
				(int) ( $error['type'] ?? 0 ),
				[ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ],
				true
			);

		if ( $isFatal ) {
			// Use writeLine directly rather than add()/addError() — those
			// route through canLog()/dataToArray()/recordEntityIds(), any
			// of which could theoretically be in a bad state if the fatal
			// originated inside JobLog itself. writeLine is the cheapest
			// path that still gets caps + JSON encoding right.
			self::writeLine(
                [
					'type'    => 'log',
					'ts'      => microtime( true ),
					'groupId' => self::$groupId,
					'id'      => 'php_fatal',
					'data'    => [
						'error_type'      => (int) $error['type'],
						'error_type_name' => self::phpErrorTypeName( (int) $error['type'] ),
						'message'         => substr( (string) ( $error['message'] ?? '' ), 0, 1000 ),
						'file'            => (string) ( $error['file'] ?? '' ),
						'line'            => isset( $error['line'] ) ? (int) $error['line'] : null,
					],
					'logType' => self::LOG_TYPE_ERROR,
				]
            );
			self::$hasRequestAnyErrorLog = true;
		}

		// Synthesise a request_finished envelope so the file finalises
		// to `.complete` and shows up in the admin list — marked
		// `aborted: true` to distinguish from a normal completion.
		if ( ! self::$requestFinishedWritten ) {
			self::writeLine(
                [
					'type'          => 'request_finished',
					'ts'            => microtime( true ),
					'hasErrorLogs'  => self::$hasRequestAnyErrorLog,
					'requestParams' => self::dataToArray( self::$requestParams ),
					'ids'           => self::buildEntityIdsSummary(),
					'aborted'       => true,
				]
            );
			self::$requestFinishedWritten = true;
		}

		FsJobLogStorage::finaliseStream( self::$fp, self::$inProgressPath );
		self::$fp             = null;
		self::$inProgressPath = null;
	}

	/**
	 * Human-readable PHP error-type name for `php_fatal` event payloads.
	 * Falls back to a numeric marker for unknown constants.
	 *
	 * @param int $type
	 *
	 * @return string
	 */
	/**
	 * Safely invoke a no-arg getter on an object so a missing method (or
	 * a method that throws) can never abort the calling payload-assembly
	 * mid-flight.
	 *
	 * Before this helper existed, the `ate_job_bound` payload called
	 * `$translationJob->get_trid()` directly — `WPML_Post_Translation_Job`
	 * didn't have that method and the resulting fatal aborted the WP
	 * shutdown action chain, leaving a request stuck in `.in_progress`
	 * with no error context inside (the wpmldev-tea-issue case). Every
	 * accessor in a JobLog payload that isn't on a guaranteed type
	 * should funnel through here so the worst case is a `null` log
	 * field, never a request-killing fatal.
	 *
	 * Returns $default when:
	 *   - $obj is not an object
	 *   - the method doesn't exist
	 *   - the method exists but throws on call
	 *
	 * @param mixed  $obj
	 * @param string $method
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function safeCall( $obj, $method, $default = null ) {
		if ( ! is_object( $obj ) || ! method_exists( $obj, $method ) ) {
			return $default;
		}
		try {
			return $obj->$method();
		} catch ( \Throwable $e ) {
			return $default;
		}
	}

	/**
	 * Safely read a public property from an object. Same rationale as
	 * safeCall(): never let a missing-property warning or strict-mode
	 * fatal escape into JobLog payload assembly.
	 *
	 * @param mixed  $obj
	 * @param string $property
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function safeProp( $obj, $property, $default = null ) {
		if ( ! is_object( $obj ) ) {
			return $default;
		}
		try {
			if ( ! isset( $obj->$property ) ) {
				return $default;
			}
			return $obj->$property;
		} catch ( \Throwable $e ) {
			return $default;
		}
	}

	private static function phpErrorTypeName( $type ) {
		$map = [
			E_ERROR             => 'E_ERROR',
			E_PARSE             => 'E_PARSE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_WARNING           => 'E_WARNING',
			E_NOTICE            => 'E_NOTICE',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		];
		return isset( $map[ $type ] ) ? $map[ $type ] : 'UNKNOWN(' . $type . ')';
	}

	/**
	 * `wpml_after_save_post` handler — fire `source_edited_with_pending_translation`
	 * when a source post is saved AND there are translation jobs for its trid
	 * that haven't been delivered yet.
	 *
	 * @param int    $post_id
	 * @param int    $trid
	 * @param string $language_code
	 * @param string $source_language Set only when this post IS a translation;
	 *                                empty/null means we're saving the source.
	 *
	 * @return void
	 */
	public static function onSourcePostSaved( $post_id, $trid = null, $language_code = null, $source_language = null ) {
		if ( ! self::canLog() ) {
			return;
		}
		// Only fire for SOURCE posts — a translation post being saved is a
		// completely different flow and would generate noisy false positives.
		if ( ! empty( $source_language ) ) {
			return;
		}
		if ( ! $trid ) {
			return;
		}

		// Skip autosaves and revisions: WP fires save_post for both, and
		// neither is a meaningful "source edited" event. On VIP-scale sites
		// these dominate save_post traffic and would otherwise drive the
		// query below for every keystroke autosave.
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}

		global $wpdb;

		// Cheap precheck: a single bookmark lookup against icl_translation_status.
		// On the overwhelming majority of saves (post has no in-flight jobs)
		// this returns nothing and we skip the 3-table JOIN entirely. Status
		// IN (1,2,3) covers the "waiting / in-progress / needs-update" states
		// that constitute "in flight" for the race we're looking for.
		$hasInFlight = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
				 FROM {$wpdb->prefix}icl_translations t
				 INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				 WHERE t.trid = %d
				   AND s.status IN (1, 2, 3)
				 LIMIT 1",
				(int) $trid
			)
		);

		if ( ! $hasInFlight ) {
			return;
		}

		// Default payload is a single COUNT — the actionable signal is "edited
		// while N jobs were pending". The per-job breakdown is debug-grade and
		// gated behind WPML_JOB_LOG_VERBOSE_SOURCE_EDIT so VIP-scale installs
		// don't pay the 3-table JOIN on every source save.
		$verbose = defined( 'WPML_JOB_LOG_VERBOSE_SOURCE_EDIT' ) && WPML_JOB_LOG_VERBOSE_SOURCE_EDIT;

		$pending          = [];
		$pendingJobCount  = 0;

		if ( $verbose ) {
			// LIMIT 50 defends against a pathological trid carrying thousands
			// of translation rows — the encoded log line caps at 65 KB anyway,
			// and 50 rows is more than enough for diagnosis.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT j.job_id,
					        j.rid,
					        s.status,
					        t.language_code,
					        UNIX_TIMESTAMP() - UNIX_TIMESTAMP(s.timestamp) AS age_seconds
					 FROM {$wpdb->prefix}icl_translate_job j
					 INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
					 INNER JOIN {$wpdb->prefix}icl_translations t       ON t.translation_id = s.translation_id
					 WHERE t.trid = %d
					   AND j.translated = 0
					 LIMIT 50",
					(int) $trid
				),
				ARRAY_A
			);

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$pending[] = [
						'job_id'      => (int) $row['job_id'],
						'rid'         => (int) $row['rid'],
						'target_lang' => isset( $row['language_code'] ) ? (string) $row['language_code'] : '',
						'status'      => isset( $row['status'] ) ? (int) $row['status'] : null,
						'age_seconds' => isset( $row['age_seconds'] ) ? (int) $row['age_seconds'] : 0,
					];
				}
				$pendingJobCount = count( $pending );
			}
		} else {
			$pendingJobCount = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					 FROM {$wpdb->prefix}icl_translate_job j
					 INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
					 INNER JOIN {$wpdb->prefix}icl_translations t       ON t.translation_id = s.translation_id
					 WHERE t.trid = %d
					   AND j.translated = 0",
					(int) $trid
				)
			);
		}

		// EXISTS precheck said in-flight, but the verbose/count query could
		// still see zero (status was 1/2/3 but j.translated became 1 between
		// the two queries — a benign race). Nothing actionable to log.
		if ( $pendingJobCount === 0 ) {
			return;
		}

		// Auto-open a group if the save happened outside any existing
		// group so the event doesn't trigger an orphan-add warning. Nests
		// cleanly inside save_translation / send_jobs callers that already
		// have one open.
		$ownsGroup = ! self::isGroupOpen();
		if ( $ownsGroup ) {
			self::createNewGroup( self::GROUP_ID_JOB_LIFECYCLE, 'Source post saved with pending translations', [] );
		}
		try {
			$payload = [
				'post_id'           => (int) $post_id,
				'trid'              => (int) $trid,
				'source_lang'       => $language_code,
				'pending_job_count' => $pendingJobCount,
			];
			if ( $verbose ) {
				$payload['pending_jobs'] = $pending;
			}
			self::addError( 'source_edited_with_pending_translation', $payload );
		} finally {
			if ( $ownsGroup ) {
				self::finishCurrentGroup();
			}
		}
	}

	/**
	 * Clear every static so a fresh test starts from a clean slate. The class
	 * holds extensive request-scoped state (open stream handle, trace dedup
	 * set, entity-ID accumulator, request envelope fields …) — without this
	 * helper, leakage between tests produces flaky failures that look like
	 * real bugs.
	 *
	 * @internal Test use only.
	 *
	 * @return void
	 */
	public static function resetForTests() {
		if ( is_resource( self::$fp ) ) {
			@fclose( self::$fp );
		}
		self::$isInitialised          = false;
		self::$hasRequestAnyErrorLog  = false;
		self::$isEnabled              = null;
		self::$requestUrl             = null;
		self::$requestParams          = [];
		self::$requestDateTime        = null;
		self::$logUid                 = null;
		self::$requestStartTime       = null;
		self::$groupId                = null;
		self::$extraLogData           = [];
		self::$stringBatchIds         = [];
		self::$alreadyAddedToLog      = [];
		self::$seenTraceHashes        = [];
		self::$fp                     = null;
		self::$inProgressPath         = null;
		self::$streamOpenAttempted    = false;
		self::$streamOpenFailed       = false;
		self::$requestStartedWritten  = false;
		self::$requestFinishedWritten = false;
		self::$writeFailureReported   = false;
		self::$orphanAddReported      = false;
		self::$phpPid                 = null;
		self::$idsTouched             = [];
		self::$logEventCount          = 0;
		self::$eventsTruncatedReported = false;
	}

	public static function isEnabled() {
		if ( ! self::$isInitialised ) {
			return false;
		}

		if ( ! is_null( self::$isEnabled ) ) {
			return self::$isEnabled;
		}

		// CLI (wp-cli) and WP-Cron contexts fire constantly, rarely hit the
		// race conditions JobLog is built to catch, and would dominate the
		// log buffer with non-diagnostic noise. Skipped unconditionally.
		if ( self::isCliOrCronContext() ) {
			return self::$isEnabled = false;
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return false;
		}

		// Inverted semantics: explicit `_is_disabled = true` opts out; absence
		// defaults to enabled.
		$isDisabled             = (bool) $sitepress->get_setting( self::IS_DISABLED_OPTION_NAME, false );
		return self::$isEnabled = ! $isDisabled;
	}

	/**
	 * True for CLI invocations (wp-cli, plain `php …`) and for WP-Cron
	 * requests. We treat these as "noise" surfaces and skip JobLog entirely
	 * there.
	 *
	 * @return bool
	 */
	private static function isCliOrCronContext() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( PHP_SAPI === 'cli' ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		return false;
	}

	/**
	 * Combined gate: request is initialised AND the feature is on. Public so
	 * call sites that want to skip expensive precomputation (e.g. an extra
	 * DB read just to feed the log payload) can short-circuit when JobLog
	 * isn't going to write anything anyway.
	 */
	public static function canLog() {
		return self::wasRequestInitialised() && self::isEnabled();
	}

	public static function setIsEnabled( $isEnabled ) {
		global $sitepress;
		// Persist only the "disabled" state — the absence of the key means
		// "enabled (default)", so an enable-toggle effectively reverts to
		// the default by writing false.
		$sitepress->set_setting( self::IS_DISABLED_OPTION_NAME, ! (bool) $isEnabled );
		$sitepress->save_settings();
	}

	/**
	 * This call should start the logging process and be called before starting any logging.
	 *
	 * @return void
	 */
	public static function maybeInitRequest() {
		if ( ! is_null( self::$requestDateTime ) ) {
			return;
		}

		self::$requestUrl       = $_SERVER['REQUEST_URI'] ?? null;
		self::$requestParams    = self::getUrlParams();
		self::$requestDateTime  = gmdate( 'Y-m-d\TH:i:s\Z' );
		self::$logUid           = uniqid();
		self::$requestStartTime = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
			? (float) $_SERVER['REQUEST_TIME_FLOAT']
			: microtime( true );
	}

	public static function wasRequestInitialised() {
		return is_string( self::$requestUrl );
	}

	/**
	 * True while a logging group is open (between createNewGroup() and the
	 * matching finishCurrentGroup()). Public so call sites that may run
	 * either standalone OR nested inside a parent group can decide whether
	 * they need to own their own group.
	 *
	 * @return bool
	 */
	public static function isGroupOpen() {
		return self::$groupId !== null;
	}

	/**
	 * Start a new logging group. Writes a `group_started` line eagerly so that a
	 * mid-group crash still leaves a marker on disk.
	 *
	 * @param int    $groupId
	 * @param string $groupLabel
	 * @param array  $groupData
	 */
	public static function createNewGroup( $groupId, $groupLabel = '', $groupData = [] ) {
		if ( ! self::canLog() ) {
			return;
		}

		if ( ! self::ensureStreamOpen() ) {
			return;
		}

		self::$groupId = $groupId;

		self::$alreadyAddedToLog = [];

		// Pull entity IDs out of the group context (e.g. Sync's $ateJobIds
		// list passed at group start) so they reach the per-request index.
		if ( is_array( $groupData ) ) {
			self::recordEntityIds( $groupData );
		}

		self::writeLine(
            [
				'type'    => 'group_started',
				'ts'      => microtime( true ),
				'groupId' => $groupId,
				'label'   => $groupLabel,
				'data'    => self::dataToArray( $groupData ),
			]
        );
	}

	public static function finishCurrentGroup() {
		if ( ! self::canLog() ) {
			return;
		}

		if ( self::$groupId === null ) {
			return;
		}

		if ( ! self::ensureStreamOpen() ) {
			self::$groupId      = null;
			self::$extraLogData = [];
			return;
		}

		self::writeLine(
            [
				'type'    => 'group_finished',
				'ts'      => microtime( true ),
				'groupId' => self::$groupId,
			]
        );

		self::$groupId = null;

		// Reset auto-merged metadata at the group boundary so it cannot leak
		// into a later group in the same request (e.g. element_id/target_lang
		// from a send_jobs loop bleeding into a sync/download group).
		self::$extraLogData = [];
	}

	/**
	 * Add extra metadata which will be auto appended to all next logs.
	 *
	 * The caller is responsible for calling removeExtraLogData() once the
	 * metadata is no longer applicable — otherwise it leaks into subsequent
	 * log entries (existing behaviour; fix tracked separately).
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function addExtraLogData( $key, $value ) {
		if ( ! self::canLog() ) {
			return;
		}

		if (
			$key === 'element_id'
			&& isset( self::$extraLogData['type'] )
			&& self::$extraLogData['type'] === 'st-batch'
		) {
			if ( ! in_array( $value, self::$stringBatchIds, true ) ) {
				self::$stringBatchIds[] = $value;
			}
		}

		// extraLogData is merged into every subsequent log line in the group
		// raw — without sanitisation a single careless caller passing a huge
		// array would bloat every line. Walk through dataToArray() once at
		// insert time so the stored value is already bounded by per-string,
		// per-array, and recursion-depth limits. recordEntityIds() still
		// receives the raw value so entity matching is unaffected.
		self::$alreadyAddedToLog = [];
		$bounded                 = self::dataToArray( $value );

		// Hard cap on the encoded size of a single extraLogData value.
		// dataToArray() bounds per-string and per-array width, but a 1000-
		// item array of 1000-char strings still encodes to ~1 MB — and
		// extraLogData multiplies that by every event in the group. Replace
		// oversized values with a self-describing stub so the merge stays
		// cheap regardless of caller carelessness. Scalars and small
		// arrays — the overwhelming majority of legitimate uses — pass
		// through unchanged.
		$encoded       = json_encode( $bounded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$originalBytes = is_string( $encoded ) ? strlen( $encoded ) : 0;
		if ( $originalBytes > self::MAX_EXTRA_LOG_DATA_BYTES ) {
			$bounded = [
				'_truncated'                  => true,
				'_too_big_for_extra_log_data' => $originalBytes,
				'_cap_bytes'                  => self::MAX_EXTRA_LOG_DATA_BYTES,
			];
		}
		self::$extraLogData[ $key ] = $bounded;

		// Funnel into the per-request entity-IDs index so findRequestsByEntity
		// can answer without scanning every event.
		self::recordEntityIds( [ $key => $value ] );
	}

	public static function removeExtraLogData( $key ) {
		if ( ! self::canLog() ) {
			return;
		}

		unset( self::$extraLogData[ $key ] );
	}

	/**
	 * Add a log entry. Written to disk immediately.
	 *
	 * @param string|int $id
	 * @param mixed      $data
	 * @param int        $logType
	 */
	public static function add( $id, $data = [], $logType = self::LOG_TYPE_INFO ) {
		if ( ! self::canLog() ) {
			return;
		}

		// Lazy-strict group requirement: when no group is currently open we
		// still write the line *if* the stream is already open (i.e. a
		// prior group ran in this request and the file is mid-flight) so
		// downstream helpers called outside an explicit group still leave
		// breadcrumbs in the right request. But we refuse to OPEN a new
		// stream purely for orphan add() calls — that prevents random
		// scattered logging from materialising new joblog files for
		// requests that never legitimately enrolled.
		//
		// The one-time error_log warning surfacing the missing
		// createNewGroup() is opt-in via WPML_JOB_LOG_SAVE_ORPHAN_GROUPS_CALLS
		// — set on dev/QA installs that want the diagnostic, off in
		// production where it would otherwise be noisy stderr output.
		if ( self::$groupId === null ) {
			if (
				defined( 'WPML_JOB_LOG_SAVE_ORPHAN_GROUPS_CALLS' )
				&& ! self::$orphanAddReported
			) {
				self::$orphanAddReported = true;
				@error_log(
					'WPML JobLog: add() called without an open group (id=' . (string) $id . '). '
					. 'Call JobLog::createNewGroup() before logging.'
				);
			}
			if ( ! is_resource( self::$fp ) ) {
				return;
			}
		}

		if ( ! self::ensureStreamOpen() ) {
			return;
		}

		// Per-request event-count cap. A runaway loop calling add() inside
		// a tight foreach would otherwise pay debug_backtrace + JSON encode
		// + fwrite per iteration even though the file's eventual prune
		// would just throw the bytes away. Latching at the cap drops to
		// effectively-free no-ops after the truncation marker.
		if ( self::$logEventCount >= self::MAX_EVENTS_PER_REQUEST ) {
			if ( ! self::$eventsTruncatedReported ) {
				self::$eventsTruncatedReported = true;
				self::writeLine( [
					'type'    => 'log',
					'ts'      => microtime( true ),
					'groupId' => self::$groupId,
					'id'      => 'events_truncated',
					'data'    => [
						'cap'                  => self::MAX_EVENTS_PER_REQUEST,
						'last_id'              => $id,
						'remaining_calls_dropped_until_shutdown' => true,
					],
					'logType' => self::LOG_TYPE_ERROR,
				] );
				self::$hasRequestAnyErrorLog = true;
			}
			return;
		}
		self::$logEventCount++;

		self::$alreadyAddedToLog = [];

		// Pull entity IDs out of the data payload (rid, post_id, …) so the
		// request_finished envelope can carry the union of everything this
		// request touched.
		if ( is_array( $data ) ) {
			self::recordEntityIds( $data );
		}

		$traceFrames = self::getTrace();
		$traceHash   = self::hashTrace( $traceFrames );

		// First occurrence of this trace in the current request → store the
		// full frames inline. Subsequent occurrences carry only `traceHash`
		// and rely on the reader's per-file resolver to rehydrate them.
		$traceFields = isset( self::$seenTraceHashes[ $traceHash ] )
			? [ 'traceHash' => $traceHash ]
			: [
				'trace'     => $traceFrames,
				'traceHash' => $traceHash,
			];

		self::$seenTraceHashes[ $traceHash ] = true;

		$line = array_merge(
			[
				'type'    => 'log',
				'ts'      => microtime( true ),
				'groupId' => self::$groupId,
				'id'      => $id,
				'data'    => self::dataToArray( $data ),
			],
			$traceFields,
			[ 'logType' => $logType ]
		);

		// extraLogData uses `+=` (union) so it fills in only keys not already
		// present in the canonical envelope. send_jobs calls
		// addExtraLogData('type', 'st-batch'|'post'|'package') for UI grouping,
		// and prior array_merge ordering let that clobber the line's
		// `type` => `log` field — every log line then carried
		// `type: "st-batch"` and the reader's `case 'log':` branch dropped
		// them, leaving action groups visibly empty in the View.
		$line += self::$extraLogData;

		self::writeLine( $line );
	}

	/**
	 * 10 hex chars from md5 — ~1 in 10^12 collision over millions of distinct
	 * traces, plenty for per-request dedup where the keyspace is at most a
	 * few dozen.
	 *
	 * @param string[] $frames
	 *
	 * @return string
	 */
	private static function hashTrace( array $frames ) {
		return substr( md5( implode( "\n", $frames ) ), 0, 10 );
	}

	/**
	 * Recursively walk a payload and add any recognised entity-ID key/value
	 * pairs to $idsTouched. Uses the value as the array key for free
	 * deduplication.
	 *
	 * Accepts arrays *and* objects — the Sync/Download Process classes log
	 * collections of Job objects (jobId, ateJobId, language_code, …) and we
	 * want those IDs to flow into the per-request index without each call
	 * site having to flatten its data first.
	 *
	 * @param mixed $payload
	 *
	 * @return void
	 */
	private static function recordEntityIds( $payload, $depth = 0 ) {
		// Match dataToArray's depth ceiling — without one, pathological or
		// circular payloads could blow the stack here even though
		// dataToArray bounds them later.
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		if ( is_object( $payload ) ) {
			$payload = get_object_vars( $payload );
		}
		if ( ! is_array( $payload ) ) {
			return;
		}
		foreach ( $payload as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				self::recordEntityIds( $value, $depth + 1 );
				continue;
			}
			if ( ! isset( self::$ID_KEY_MAP[ $key ] ) ) {
				continue;
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$str = (string) $value;
			if ( $str === '' ) {
				continue;
			}
			$bucket                              = self::$ID_KEY_MAP[ $key ];
			self::$idsTouched[ $bucket ][ $str ] = true;
		}
	}

	/**
	 * Flatten the $idsTouched set-map into the shape that lands in
	 * request_finished and surfaces through getRequestSummaries():
	 *
	 *   { "rids": [5879, 5891], "post_ids": [42, 433], … }
	 *
	 * Numeric strings are emitted as ints (PHP auto-coerces array keys);
	 * non-numeric strings stay as strings. Either way comparisons in
	 * findRequestsByEntity use loose equality.
	 *
	 * @return array<string, array<int|string>>
	 */
	private static function buildEntityIdsSummary() {
		$out = [];
		foreach ( self::$idsTouched as $bucket => $valueMap ) {
			if ( empty( $valueMap ) ) {
				continue;
			}
			$out[ $bucket ] = array_keys( $valueMap );
		}
		return $out;
	}

	public static function isErrorLog( $log ) {
		return (int) $log['logType'] === self::LOG_TYPE_ERROR;
	}

	/**
	 * Add an error log entry. Error log entries will be displayed in special way in the UX.
	 *
	 * @param string|int $id
	 * @param mixed      $data
	 */
	public static function addError( $id, $data = [] ) {
		self::$hasRequestAnyErrorLog = true;
		self::add( $id, $data, self::LOG_TYPE_ERROR );
	}

	public static function getLogsCount() {
		return FsJobLogStorage::getLogsCount();
	}

	/**
	 * Lightweight per-request metadata for the list view.
	 *
	 * @return array<int, array>
	 */
	public static function getSummaries() {
		return FsJobLogStorage::getRequestSummaries();
	}

	/**
	 * Event stream for one request. Yields events one at a time.
	 *
	 * @param string $logUid
	 *
	 * @return \Generator
	 */
	public static function getEvents( $logUid ) {
		return FsJobLogStorage::readEvents( $logUid );
	}

	public static function clearLogs() {
		return FsJobLogStorage::clearAllLogs();
	}

	/**
	 * Shutdown handler — emit `request_finished` with enrichments, close the
	 * stream, and rename `.in_progress.ndjson` → `.complete.ndjson`.
	 *
	 * If the stream was never opened (e.g. nothing was ever logged), nothing
	 * is done; if the worker dies before this runs, the `.in_progress` file
	 * stays on disk as the surviving evidence.
	 */
	public static function shutdown() {
		if ( ! self::canLog() ) {
			return;
		}

		if ( ! self::$requestStartedWritten ) {
			return;
		}

		// If a group was left open by an early return or thrown exception,
		// close it cleanly so the reader doesn't have to guess.
		if ( self::$groupId !== null ) {
			self::finishCurrentGroup();
		}

		$enrichedRequestParams = self::enrichRequestParams( self::$requestParams );
		$stringIdsByBatchId    = self::resolveStringIdsByBatchId( self::$stringBatchIds );

		self::$alreadyAddedToLog = [];

		self::writeLine(
            [
				'type'               => 'request_finished',
				'ts'                 => microtime( true ),
				'hasErrorLogs'       => self::$hasRequestAnyErrorLog,
				'requestParams'      => self::dataToArray( $enrichedRequestParams ),
				'stringIdsByBatchId' => $stringIdsByBatchId,
				'ids'                => self::buildEntityIdsSummary(),
			]
        );
		self::$requestFinishedWritten = true;

		if ( ! FsJobLogStorage::finaliseStream( self::$fp, self::$inProgressPath ) ) {
			self::reportIoFailure( 'finalise' );
		}

		self::$fp             = null;
		self::$inProgressPath = null;
	}

	/**
	 * Lazily open the NDJSON stream and write the request envelope as the first
	 * line. Subsequent calls are cheap no-ops once the stream is open (or once
	 * a previous open failed).
	 *
	 * @return bool True if writes can proceed.
	 */
	private static function ensureStreamOpen() {
		if ( self::$streamOpenFailed ) {
			return false;
		}

		if ( is_resource( self::$fp ) ) {
			return true;
		}

		if ( self::$streamOpenAttempted ) {
			return false;
		}

		self::$streamOpenAttempted = true;

		// Cap enforcement happens once, at finaliseStream() after the file
		// rename from .in_progress → .complete. The pre-open prune was
		// redundant and doubled the per-request glob+stat cost; the cap is
		// a soft limit (prunes 25% on overflow) so a tiny temporary excess
		// between requests is acceptable and self-corrects on the next
		// finalise.

		[ $fp, $path ] = FsJobLogStorage::openRequestStream( self::$logUid, self::$requestStartTime );
		if ( ! is_resource( $fp ) ) {
			self::$streamOpenFailed = true;
			return false;
		}

		self::$fp             = $fp;
		self::$inProgressPath = $path;

		// Reset the recursion guard before serialising request envelope so it
		// gets its own isolated tracking state.
		self::$alreadyAddedToLog = [];

		// Goes through self::writeLine so request_started gets the same
		// timestamp_utc / elapsed_ms / php_pid enrichment as every other event.
		$ok = self::writeLine(
            [
				'type'            => 'request_started',
				'ts'              => self::$requestStartTime,
				'logUid'          => self::$logUid,
				'requestUrl'      => self::$requestUrl,
				'requestParams'   => self::dataToArray( self::$requestParams ),
				'requestDateTime' => self::$requestDateTime,
				'blogId'          => is_multisite() ? get_current_blog_id() : 0,
			]
        );

		if ( ! $ok ) {
			self::$streamOpenFailed = true;
			return false;
		}

		self::$requestStartedWritten = true;

		return true;
	}

	/**
	 * Append one NDJSON line via the storage layer. Every line is enriched with
	 * `timestamp_utc` (ISO-8601 UTC w/ ms), `elapsed_ms` (ms since request
	 * start), and `php_pid` so race conditions between concurrent PHP workers
	 * are correlatable across files with a simple grep.
	 *
	 * The line's existing `ts` (microtime float) is used as the source for the
	 * derived fields so each event type carries its own meaningful time
	 * (e.g. `request_started`'s timestamp is the request start, not the
	 * moment the file happened to be opened).
	 *
	 * @param array $line
	 *
	 * @return bool True on a fully written line.
	 */
	private static function writeLine( array $line ) {
		if ( ! is_resource( self::$fp ) ) {
			return false;
		}

		$line    = self::enrichLineWithTimings( $line );
		$encoded = self::encodeAndCap( $line );
		if ( ! is_string( $encoded ) ) {
			self::reportIoFailure( 'write' );
			return false;
		}

		$ok = FsJobLogStorage::writeEncodedLine( self::$fp, $encoded );
		if ( ! $ok ) {
			self::reportIoFailure( 'write' );
		}
		return $ok;
	}

	/**
	 * Encode the line, enforce the per-line byte cap, and return the final
	 * JSON bytes ready to write. We do the cap check on the first encode
	 * (so it doubles as a size measurement) instead of encoding twice —
	 * dataToArray() enforces per-field limits but the aggregate JSON line
	 * can still balloon (1000 items × 1000-char strings ≈ 1 MiB). When
	 * over MAX_LINE_BYTES, the heavy carriers (`data`, `requestParams`)
	 * become `{ "_truncated": true }`, a `_line_oversized` marker is
	 * stamped, and the line is re-encoded once.
	 *
	 * @param array $line
	 *
	 * @return string|null Encoded JSON, or null on encode failure.
	 */
	private static function encodeAndCap( array $line ) {
		$encoded = json_encode( $line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return null;
		}

		$originalBytes = strlen( $encoded );
		if ( $originalBytes <= self::MAX_LINE_BYTES ) {
			return $encoded;
		}

		if ( isset( $line['data'] ) ) {
			$line['data'] = [ '_truncated' => true ];
		}
		if ( isset( $line['requestParams'] ) ) {
			$line['requestParams'] = [ '_truncated' => true ];
		}
		$line['_line_oversized'] = [
			'original_bytes' => $originalBytes,
			'cap_bytes'      => self::MAX_LINE_BYTES,
		];

		$reencoded = json_encode( $line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $reencoded ) ? $reencoded : null;
	}

	/**
	 * Prepend `timestamp_utc`, `elapsed_ms`, `php_pid` to a line. Uses
	 * `$line + [...]` so that when a caller deliberately pre-sets one of
	 * these keys their value wins — the array `+` operator keeps the
	 * left-hand value on duplicate keys.
	 *
	 * The enrichment fields are appended (not prepended) in array order,
	 * but `json_encode` preserves insertion order so reader/grep ergonomics
	 * are unaffected.
	 *
	 * @param array $line
	 *
	 * @return array
	 */
	private static function enrichLineWithTimings( array $line ) {
		$ts = isset( $line['ts'] ) ? (float) $line['ts'] : microtime( true );

		return $line + [
			'timestamp_utc' => self::formatUtcMs( $ts ),
			'elapsed_ms'    => self::elapsedMs( $ts ),
			'php_pid'       => self::pid(),
		];
	}

	/**
	 * Format a microtime float as ISO-8601 UTC with millisecond precision,
	 * e.g. `2026-05-13T13:45:22.103Z`.
	 *
	 * @param float $microtimeFloat
	 *
	 * @return string
	 */
	private static function formatUtcMs( $microtimeFloat ) {
		$seconds = (int) $microtimeFloat;
		$ms      = (int) floor( ( $microtimeFloat - $seconds ) * 1000 );
		if ( $ms < 0 ) {
			$ms = 0;
		} elseif ( $ms > 999 ) {
			$ms = 999;
		}
		return gmdate( 'Y-m-d\TH:i:s', $seconds ) . sprintf( '.%03dZ', $ms );
	}

	/**
	 * Milliseconds elapsed between the request start and the supplied moment.
	 * Returns 0 when the request hasn't been initialised yet.
	 *
	 * @param float $microtimeFloat
	 *
	 * @return int
	 */
	private static function elapsedMs( $microtimeFloat ) {
		if ( self::$requestStartTime === null ) {
			return 0;
		}
		return (int) round( ( $microtimeFloat - self::$requestStartTime ) * 1000 );
	}

	/**
	 * Memoised PHP worker pid. Constant for the lifetime of this PHP-FPM /
	 * Apache mod_php / CLI process, so we cache it after the first call.
	 *
	 * @return int
	 */
	private static function pid() {
		if ( self::$phpPid === null ) {
			self::$phpPid = function_exists( 'getmypid' ) ? (int) getmypid() : 0;
		}
		return self::$phpPid;
	}

	/**
	 * Emit a single PHP error_log entry per request when the on-disk write
	 * pipeline fails (fwrite short/false, rename false). Subsequent failures
	 * in the same request are swallowed to avoid log spam.
	 *
	 * @param string $stage write|finalise
	 *
	 * @return void
	 */
	private static function reportIoFailure( $stage ) {
		if ( self::$writeFailureReported ) {
			return;
		}
		self::$writeFailureReported = true;
		@error_log(
			'WPML JobLog: failed during ' . $stage . ' to ' . (string) self::$inProgressPath
			. ' (likely disk full or permission denied).'
		);
	}

	/**
	 * Resolve post IDs and string IDs in the request params to human-readable
	 * labels for the UI. Lifted from the previous shutdown handler — runs once
	 * per request at finalisation time.
	 *
	 * @param array $requestParams
	 *
	 * @return array
	 */
	private static function enrichRequestParams( array $requestParams ) {
		global $wpdb;

		if ( isset( $requestParams['posts'] ) && is_array( $requestParams['posts'] ) ) {
			for ( $i = 0; $i < count( $requestParams['posts'] ); $i++ ) {
				$postId = $requestParams['posts'][ $i ];
				$post   = get_post( $postId );
				if ( is_object( $post ) ) {
					$requestParams['posts'][ $i ] = $requestParams['posts'][ $i ] . ' (post title=' . $post->post_name . ')';
				}
			}
		}

		if ( isset( $requestParams['strings'] ) && is_array( $requestParams['strings'] ) ) {
			$stringIds = array_map( 'intval', $requestParams['strings'] );
			if ( $stringIds ) {
				$placeholders = implode( ',', array_fill( 0, count( $stringIds ), '%d' ) );
				$sql          = "
					SELECT id, value, context, gettext_context
					FROM {$wpdb->prefix}icl_strings
					WHERE id IN ($placeholders)
				";

				$results = $wpdb->get_results( $wpdb->prepare( $sql, $stringIds ), OBJECT_K );

				foreach ( $requestParams['strings'] as $i => $stringId ) {
					$id = (int) $stringId;
					if ( isset( $results[ $id ] ) ) {
						$value   = $results[ $id ]->value;
						$domain  = $results[ $id ]->context;
						$context = $results[ $id ]->gettext_context;

						$requestParams['strings'][ $i ] = $id . ' (string value=`' . $value . '`, context=`' . $context . '`, domain=`' . $domain . '`)';
					}
				}
			}
		}

		return $requestParams;
	}

	/**
	 * Build a batch_id → [string_id, …] map for the st-batch enrichment that
	 * the reader applies at view time. Skipped when no batches were seen.
	 *
	 * @param int[] $stringBatchIds
	 *
	 * @return array<int, int[]>
	 */
	private static function resolveStringIdsByBatchId( array $stringBatchIds ) {
		if ( count( $stringBatchIds ) === 0 ) {
			return [];
		}

		global $wpdb;
		$stringBatchIds = array_map( 'intval', $stringBatchIds );
		$placeholders   = implode( ',', array_fill( 0, count( $stringBatchIds ), '%d' ) );
		$sql            = "
			SELECT *
			FROM {$wpdb->prefix}icl_string_batches
			WHERE batch_id IN ($placeholders)
		";

		$results            = $wpdb->get_results( $wpdb->prepare( $sql, $stringBatchIds ), ARRAY_A );
		$stringIdsByBatchId = [];

		if ( ! is_array( $results ) ) {
			return $stringIdsByBatchId;
		}

		foreach ( $results as $result ) {
			$batchId  = (int) $result['batch_id'];
			$stringId = (int) $result['string_id'];

			if ( ! isset( $stringIdsByBatchId[ $batchId ] ) ) {
				$stringIdsByBatchId[ $batchId ] = [];
			}

			$stringIdsByBatchId[ $batchId ][] = $stringId;
		}

		return $stringIdsByBatchId;
	}

	private static function getObjectId( $object ) {
		return (string) spl_object_hash( $object );
	}

	/**
	 * Normalise log data to a JSON-safe structure (string truncation, depth
	 * cap, array-item cap, recursion detection, signed-URL redaction).
	 *
	 * @param mixed $data
	 * @param int   $depth
	 *
	 * @return mixed
	 */
	private static function dataToArray( $data, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return '[DEPTH_LIMIT]';
		}

		if ( is_callable( $data ) ) {
			return 'callable';
		}
		if ( is_resource( $data ) ) {
			return 'resource';
		}
		if ( is_string( $data ) ) {
			$data = self::stripUrlSecrets( $data );
			if ( strlen( $data ) > self::MAX_STRING_LENGTH ) {
				return substr( $data, 0, self::MAX_STRING_LENGTH ) . '…[TRUNCATED]';
			}
			return $data;
		}

		if ( is_object( $data ) ) {
			$id = self::getObjectId( $data );
			if ( in_array( $id, self::$alreadyAddedToLog ) ) {
				return '[RECURSION]';
			}
			self::$alreadyAddedToLog[] = $id;

			$data = get_object_vars( $data );
		}

		$count = 0;
		if ( is_array( $data ) ) {
			$result = [];

			foreach ( $data as $key => $value ) {
				if ( ++$count > self::MAX_ARRAY_ITEMS ) {
					$result[ $key ] = '[ARRAY_TRUNCATED]';
					break;
				}

				if ( self::isSensitiveKey( $key ) ) {
					$result[ $key ] = '[REDACTED]';
					continue;
				}

				$result[ $key ] = self::dataToArray( $value, $depth + 1 );
			}

			return $result;
		}

		return $data;
	}

	/**
	 * Tests whether an array key (or stringifiable scalar) should have its
	 * value redacted. Matches case-insensitively against SECRET_KEY_NEEDLES
	 * after normalising '-' to '_' so HTTP-style headers (X-Api-Key,
	 * Set-Cookie, Authorization) and snake_case fields hit the same rules.
	 *
	 * @param mixed $key
	 *
	 * @return bool
	 */
	private static function isSensitiveKey( $key ) {
		if ( ! is_string( $key ) && ! is_numeric( $key ) ) {
			return false;
		}
		$normalised = str_replace( '-', '_', strtolower( (string) $key ) );
		foreach ( self::SECRET_KEY_NEEDLES as $needle ) {
			if ( strpos( $normalised, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Replaces query-string credential values (?signature=…&token=…) in any
	 * string value the walker encounters, not just on signedUrl-keyed fields.
	 * Covers the case where an ATE URL is embedded inside response bodies,
	 * exception messages, debug dumps, etc. Cheap '=' precheck avoids the
	 * regex run on the overwhelming majority of strings.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private static function stripUrlSecrets( $value ) {
		if ( strpos( $value, '=' ) === false ) {
			return $value;
		}
		return preg_replace(
			'/(?:^|[?&])(signature|token|shared_key|api_key|access_token|refresh_token|password|secret|authorization|bearer)=([^&\s]+)/i',
			'$1=[REMOVED]',
			$value
		);
	}

	private static function getUrlParams() {
		// Short-circuit on the Content-Length header when present — saves the
		// stream read entirely for fat payloads. Header isn't trustworthy with
		// chunked transfer encoding, so the read below still bounds itself.
		$contentLength = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $contentLength > self::MAX_INPUT_BYTES ) {
			return [
				'_oversized_input' => [
					'content_length' => $contentLength,
					'cap_bytes'      => self::MAX_INPUT_BYTES,
				],
			];
		}

		// Read one byte past the cap so we can detect oversize when the
		// header was missing or wrong (chunked encoding, proxy stripping).
		$body = @file_get_contents( 'php://input', false, null, 0, self::MAX_INPUT_BYTES + 1 );

		if ( $body === false || $body === '' ) {
			return [];
		}

		if ( strlen( $body ) > self::MAX_INPUT_BYTES ) {
			return [
				'_oversized_input' => [
					'cap_bytes' => self::MAX_INPUT_BYTES,
				],
			];
		}

		$params = json_decode( $body, true );

		return is_array( $params ) ? $params : [];
	}

	/**
	 * Simplified backtrace: an array of `file:line class/function` strings.
	 *
	 * @return string[]
	 */
	private static function getTrace() {
		// Cap at 20 frames — deep WP plumbing (hook dispatch, filter chains)
		// rarely carries diagnostic value, and uncapped backtraces on 30+
		// deep stacks are ~1 ms each. At thousands of events per heavy
		// send_jobs the cap saves seconds.
		$debug_backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
		$traces          = [];

		foreach ( $debug_backtrace as $trace ) {
			$trace = [
				'file'     => $trace['file'] ?? '',
				'line'     => $trace['line'] ?? '',
				'class'    => $trace['class'] ?? '',
				'type'     => $trace['type'] ?? '',
				'function' => $trace['function'] ?? '',
			];

			$traces[] = $trace['file'] . ':' . $trace['line'] . ' ' . $trace['class'] . '/' . $trace['function'];
		}

		return $traces;
	}
}
