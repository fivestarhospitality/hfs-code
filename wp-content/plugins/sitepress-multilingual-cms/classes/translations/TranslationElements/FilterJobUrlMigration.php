<?php

namespace WPML\TM\Translations\TranslationElements;

class FilterJobUrlMigration {
	/**
	 * Filters job elements after site migration to update old URLs to new URLs.
	 *
	 * @param \stdClass $job The translation job object.
	 * @param \SitePress $sitepress The SitePress instance.
	 *
	 * @return \stdClass The filtered translation job object.
	 */
	public function maybeFilterJobElementsAfterMigration( $job, $sitepress ) {
		if ( ! ICL_TM_COMPLETE === $job->status || ! isset( $job->elements ) ) {
			return $job;
		}

		$migrated_site = $sitepress->get_setting( 'migrated_site' );

		if ( ! isset( $migrated_site['old_url'] ) || ! isset( $migrated_site['new_url'] ) ) {
			return $job;
		}

		$old_url      = $migrated_site['old_url'];
		$new_url      = $migrated_site['new_url'];
		$url_replaced = false;

		foreach ( $job->elements as $element ) {
			if ( 'base64' !== $element->field_format ) {
				continue;
			}

			$original_field_data = $element->field_data;
			$element->field_data = $this->maybeFilterUrlInJobElement( $element->field_data, $old_url, $new_url );

			$original_field_data_translated = $element->field_data_translated;
			$element->field_data_translated = $this->maybeFilterUrlInJobElement( $element->field_data_translated, $old_url, $new_url );

			// Check if URL was replaced in either field.
			if (
				$original_field_data !== $element->field_data ||
				$original_field_data_translated !== $element->field_data_translated
			) {
				$url_replaced = true;
			}
		}

		/**
		 * If we click on a pen icon to edit a translated post, we will clone the ATE job ID and edit the existing job ID in ATE.
		 * The XLIFF file actually is not created, and we will see the old original and translation content.
		 * After replacing the URL, we need to create a new ATE job ID to update the XLIFF file, send it to ATE to see the updated content and URL in the editor.
		 */
		if ( $url_replaced && \WPML_TM_Editors::ATE === $job->editor && ! $job->needs_update ) {
			$job->editor_job_id = null;
		}

		return $job;
	}

	/**
	 * Replaces old URL with new URL in a job element if found.
	 *
	 * @param string $element The job element content (base64 encoded).
	 * @param string $old_url The old URL to replace.
	 * @param string $new_url The new URL to use as replacement.
	 *
	 * @return string The processed element content (base64 encoded).
	 */
	private function maybeFilterUrlInJobElement( $element, $old_url, $new_url ) {
		$decoded = base64_decode( $element );

		if ( strpos( $decoded, $old_url ) === false ) {
			return $element;
		}

		$decoded = str_replace( $old_url, $new_url, $decoded );

		return base64_encode( $decoded );
	}

	/**
	 * Checks if the site has been migrated.
	 *
	 * @param \SitePress $sitepress The SitePress instance.
	 *
	 * @return bool True if the site has been migrated, false otherwise.
	 */
	public function isSiteMigrated( $sitepress ) {
		$migrated_site = $sitepress->get_setting( 'migrated_site' );
		return ! empty( $migrated_site );
	}
}
