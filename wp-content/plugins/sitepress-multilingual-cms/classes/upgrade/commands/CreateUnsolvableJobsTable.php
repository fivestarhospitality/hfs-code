<?php

namespace WPML\TM\Upgrade\Commands;

use SitePress_Setup;

class CreateUnsolvableJobsTable implements \IWPML_Upgrade_Command {

	const TABLE_NAME = 'icl_translate_unsolvable_jobs';

	/** @var \WPML_Upgrade_Schema $schema */
	private $schema;

	/** @var bool $result */
	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$this->result = self::create_table_if_not_exists( $wpdb );
		return $this->result;
	}

	public static function create_table_if_not_exists( $wpdb ) {

			$table_name      = $wpdb->prefix . self::TABLE_NAME;
			$charset_collate = SitePress_Setup::get_charset_collate();

			$query = "
				CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`job_id` BIGINT(20) UNSIGNED NOT NULL,
				`ate_job_id` BIGINT(20) UNSIGNED NOT NULL,
				`error_type` VARCHAR(500) NOT NULL,
				`error_message` text NULL,
				`error_data` text NULL,
				`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`counter` tinyint(4)  NOT NULL,
				PRIMARY KEY (`job_id`),
				KEY `ate_job_id` (`ate_job_id`)
				) ENGINE=INNODB {$charset_collate};
			";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->query( $query );
	}

	/**
	 * Runs in admin pages.
	 *
	 * @return bool
	 */
	public function run_admin() {
		return $this->run();
	}

	/**
	 * Unused.
	 *
	 * @return null
	 */
	public function run_ajax() {
		return null;
	}

	/**
	 * Unused.
	 *
	 * @return null
	 */
	public function run_frontend() {
		return null;
	}

	/**
	 * @return bool
	 */
	public function get_results() {
		return $this->result;
	}
}
