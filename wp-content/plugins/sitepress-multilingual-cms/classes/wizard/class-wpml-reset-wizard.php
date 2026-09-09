<?php

namespace WPML\classes\wizard;

use Exception;
use wpdb;

class WPML_Reset_Wizard
{
	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @param wpdb|null $wpdb_instance Optional. WordPress database object.
	 */
	public function __construct( $wpdb_instance = null ) {
		if ( null === $wpdb_instance ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		} else {
			$this->wpdb = $wpdb_instance;
		}
	}

	/**
	 * Execute the wizard reset
	 *
	 * @return array Response array with success status
	 */
	public function execute(): array
	{
		try {
			$this->reset_languages_table();
			$this->delete_wizard_options();
			$this->delete_wizard_transients();
			$this->reset_sitepress_settings();
			$this->flush_cache();

			return [
				'success' => true
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * @return int|false Number of rows affected or false on error.
	 */
	private function reset_languages_table() {
		$query = "UPDATE {$this->wpdb->prefix}icl_languages SET active = 0, major = 0";


		return $this->wpdb->query($query);
	}

	/**
	 * Delete all wizard-related options
	 *
	 * @return array Array of deletion results
	 */
	public function delete_wizard_options(): array
	{
		$options_to_delete = [
			'WPML(setup)',
			'WPML_TM_Wizard_For_Manager_Current_Step',
			'WPML_TM_Wizard_For_Manager_Complete',
			'WPML_TM_Wizard_For_Admin_Complete',
			'WPML_TM_Wizard_Who_Mode',
		];

		$results = [];
		foreach ($options_to_delete as $option) {
			$results[$option] = delete_option($option);
		}

		return $results;
	}

	/**
	 * Delete all wizard-related transients
	 *
	 * @return array Array of query results
	 */
	private function delete_wizard_transients(): array
	{
		$queries = [
			"DELETE FROM {$this->wpdb->prefix}options WHERE option_name LIKE '%_transient_wpml_wizard%'",
			"DELETE FROM {$this->wpdb->prefix}options WHERE option_name LIKE '%_transient_timeout_wpml_wizard%'"
		];

		$results = [];
		foreach ($queries as $query) {
			$results[] = $this->wpdb->query($query);
		}

		return $results;
	}

	/**
	 * Reset the sitepress settings to defaults
	 *
	 * @return bool True on success, false on failure
	 */
	private function reset_sitepress_settings(): bool
	{
		$settings = get_option('icl_sitepress_settings', []);

		$settings['default_language'] = null;
		$settings['setup_complete'] = false;
		$settings['languages_order'] = [];
		$settings['existing_content_language_verified'] = 0;
		$settings['active_languages'] = [];

		return update_option('icl_sitepress_settings', $settings);
	}

	/**
	 * Flush the WordPress cache
	 *
	 * @return bool Always returns true
	 */
	private function flush_cache(): bool
	{
		return wp_cache_flush();
	}
}
