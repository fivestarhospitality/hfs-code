<?php

/**
 * Class WPML_Taxonomy_Notice_Display_Validator
 *
 * Serializable callback validator for taxonomy translation help notices.
 *
 * This class serves as a callable object that can be safely serialized and stored
 * in the database along with notice data. It stores only the taxonomy ID (string)
 * and delegates the actual validation logic to static methods in the main notice class.
 *
 * Why this approach:
 * - Closures cannot be serialized in PHP
 * - Instance method callbacks serialize entire object graphs (including database connections)
 * - This validator stores only primitive data (taxonomy ID string)
 * - Implements __invoke() to make the object callable
 *
 * Usage:
 *     $validator = new WPML_Taxonomy_Notice_Display_Validator( 'product_cat' );
 *     $notice->add_display_callback( $validator );
 *     // Later, when notice is retrieved from database:
 *     if ( $validator() ) { // Calls __invoke()
 *         // Display notice
 *     }
 *
 */
class WPML_Taxonomy_Notice_Display_Validator {

	/**
	 * @var string The taxonomy slug to validate
	 */
	private $taxonomy_id;

	/**
	 * Constructor
	 *
	 * @param string $taxonomy_id The taxonomy slug (e.g., 'product_cat', 'category').
	 */
	public function __construct( $taxonomy_id ) {
		$this->taxonomy_id = $taxonomy_id;
	}

	/**
	 * Magic method to make this object callable
	 *
	 * This method is invoked when the object is used as a callback.
	 * It validates whether the taxonomy notice should be displayed.
	 *
	 * @return bool True if notice should display, false otherwise
	 */
	public function __invoke() {
		return WPML_Taxonomy_Translation_Help_Notice::validate_display_for_taxonomy( $this->taxonomy_id );
	}

	/**
	 * Get the taxonomy ID this validator is for
	 *
	 * @return string
	 */
	public function get_taxonomy_id() {
		return $this->taxonomy_id;
	}
}

