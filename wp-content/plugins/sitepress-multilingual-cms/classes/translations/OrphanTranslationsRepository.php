<?php

namespace WPML\Translation;

/**
 * Repository for "orphan-translation" queries on the `icl_translations` table.
 *
 * An *orphan trid* (relative to a source language `S` and an `element_type E`)
 * is a trid in `E` that has at least one non-`S` row whose `element_id` resolves
 * to a real existing post, but no `S`-language row in `E`. The classic case:
 * a Spanish or French post that has no English source — a candidate for the
 * editor's "Connect with translations" UI.
 *
 * Both methods key their `IS NULL` check on the PRIMARY KEY column
 * (`s.translation_id`), which is `NOT NULL` per the schema — so a NULL value
 * post-LEFT-JOIN can only mean "no row matched", never "a row matched but
 * the column is NULL". That property is what makes the anti-join shape safe
 * (see Trap E in `/project/spec/testing.md`).
 *
 * Callers are responsible for any WP-environment guards (active-language
 * validation, current-post-not-already-translated check, etc.) so the
 * repository stays a pure SQL boundary. SitePress::has_orphan_translations()
 * and SitePress::get_orphan_translations() wire those guards before calling
 * here.
 */
class OrphanTranslationsRepository {

	/** @var \wpdb */
	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Existence check: does the site have at least one orphan trid in `$element_type`
	 * relative to `$source_language`?
	 *
	 * `LIMIT 1` lets MySQL bail on the first orphan it sees, so cost is
	 * bounded by O(rows-until-first-orphan), not the full slice.
	 *
	 * @param string $element_type    e.g. 'post_post' (WPML element_type, not WP post_type).
	 * @param string $source_language e.g. 'en'.
	 * @return bool
	 */
	public function hasOrphans( $element_type, $source_language ) {
		$sql = "SELECT 1
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->prefix}icl_translations t
				ON p.ID = t.element_id
				AND t.element_type = %s
				AND t.language_code <> %s
			LEFT JOIN {$this->wpdb->prefix}icl_translations s
				ON s.trid = t.trid
				AND s.element_type = %s
				AND s.language_code = %s
			WHERE s.translation_id IS NULL
			LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_prepared = $this->wpdb->prepare( $sql, array( $element_type, $source_language, $element_type, $source_language ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $this->wpdb->get_var( $sql_prepared );
	}

	/**
	 * List query: one row per non-source-language post in any orphan trid.
	 *
	 * Each row has `value` (the trid) and `label` (`[lang] post_title`, with
	 * empty-title fallback to `LEFT(post_content, 30) + '...'`) — shaped for
	 * the editor's "Connect with translations" autocomplete.
	 *
	 * @param string $element_type
	 * @param string $source_language
	 * @return object[] result rows from $wpdb->get_results (unchanged shape).
	 */
	public function getOrphans( $element_type, $source_language ) {
		$sql = "SELECT t.trid AS value,
		       CONCAT('[', t.language_code, '] ', (CASE p.post_title WHEN '' THEN CONCAT(LEFT(p.post_content, 30), '...') ELSE p.post_title END)) AS label
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->prefix}icl_translations t
				ON p.ID = t.element_id
				AND t.element_type = %s
				AND t.language_code <> %s
			LEFT JOIN {$this->wpdb->prefix}icl_translations s
				ON s.trid = t.trid
				AND s.element_type = %s
				AND s.language_code = %s
			WHERE s.translation_id IS NULL
			ORDER BY t.trid";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_prepared = $this->wpdb->prepare( $sql, array( $element_type, $source_language, $element_type, $source_language ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql_prepared );
	}
}
