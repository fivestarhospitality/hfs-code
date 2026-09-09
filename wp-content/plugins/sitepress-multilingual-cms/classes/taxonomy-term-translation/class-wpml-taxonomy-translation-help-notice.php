<?php

/**
 * Class WPML_Taxonomy_Translation_Help_Notice
 */
class WPML_Taxonomy_Translation_Help_Notice {

	const NOTICE_GROUP = 'taxonomy-term-help-notices';

	/**
	 * @var WPML_Notices
	 */
	private $wpml_admin_notices;

	/**
	 * @var WPML_Notice
	 */
	private $notice = false;

	/**
	 * @var SitePress
	 */
	private $sitepress;

	/**
	 * WPML_Taxonomy_Translation_Help_Notice constructor.
	 *
	 * @param WPML_Notices $wpml_admin_notices
	 * @param SitePress    $sitepress
	 */
	public function __construct( WPML_Notices $wpml_admin_notices, SitePress $sitepress ) {
		$this->wpml_admin_notices = $wpml_admin_notices;
		$this->sitepress          = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'add_help_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Create and add notice.
	 */
	public function add_help_notice() {
		$notice = $this->create_and_set_term_translation_help_notice();
		if ( false !== $notice ) {
			$this->add_term_help_notice_to_admin_notices();
		}
	}

	/**
	 * @return WP_Taxonomy|false
	 */
	private function get_current_translatable_taxonomy() {
		return self::get_current_translatable_taxonomy_static();
	}

	/**
	 * @return WPML_Notice
	 */
	private function create_and_set_term_translation_help_notice() {
		$taxonomy = $this->get_current_translatable_taxonomy();
		if ( false !== $taxonomy ) {
			$link_to_taxonomy_translation_screen = $this->build_tag_to_taxonomy_translation( $taxonomy );
			$text                                = sprintf( esc_html__( 'Translating %1$s? Use the %2$s table for easier translation.', 'sitepress' ), $taxonomy->labels->name, $link_to_taxonomy_translation_screen );
			$this->set_notice( new WPML_Notice( $taxonomy->name, $text, self::NOTICE_GROUP ) );
		}

		return $this->get_notice();
	}

	/**
	 * Add the taxonomy help notice to the admin notices system.
	 *
	 * Uses a serializable validator object instead of a closure to prevent
	 * serialization errors when notices are stored in the database.
	 *
	 * @since 4.6.15 Changed to use WPML_Taxonomy_Notice_Display_Validator
	 */
	private function add_term_help_notice_to_admin_notices() {
		$notice = $this->get_notice();
		$notice->set_css_class_types( 'info' );
		// Use a serializable validator object to prevent closure serialization errors.
		// The validator stores only the taxonomy ID (string) and can be safely serialized.
		// Type cast to string to satisfy PHPStan (WPML_Notice::get_id() returns int|string).
		$taxonomy_id = (string) $notice->get_id();
		$validator   = new WPML_Taxonomy_Notice_Display_Validator( $taxonomy_id );
		$notice->add_display_callback( $validator );
		$action = $this->wpml_admin_notices->get_new_notice_action( esc_html__( 'Dismiss', 'sitepress' ), '#', false, false, false );
		$action->set_js_callback( 'wpml_dismiss_taxonomy_translation_notice' );
		$action->set_group_to_dismiss( $notice->get_group() );
		$notice->add_action( $action );
		$this->wpml_admin_notices->add_notice( $notice );
	}

	/**
	 * Validate if a taxonomy notice should be displayed on the current page.
	 *
	 * This static method is called by the validator object during runtime to determine
	 * if the notice should be shown. It checks if we're on a taxonomy term screen and
	 * if the current taxonomy matches the taxonomy ID stored in the notice.
	 *
	 * @param string $taxonomy_id The taxonomy slug to validate (e.g., 'product_cat', 'category').
	 * @return bool True if the notice should be displayed, false otherwise.
	 */
	public static function validate_display_for_taxonomy( $taxonomy_id ) {
		if ( ! self::is_taxonomy_term_screen_static() ) {
			return false;
		}

		$current_taxonomy = self::get_current_translatable_taxonomy_static();
		if ( $current_taxonomy && $current_taxonomy->name === $taxonomy_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the current admin screen is a taxonomy term screen.
	 */
	private static function is_taxonomy_term_screen_static() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->base, array( 'edit-tags', 'term' ), true );
	}

	/**
	 * Get the current translatable taxonomy from the URL parameter.
	 *
	 * Retrieves and validates the taxonomy from the 'taxonomy' URL parameter,
	 * ensuring it exists and is configured as translatable in WPML.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading taxonomy from URL for display purposes only.
	private static function get_current_translatable_taxonomy_static() {
		if ( empty( $_GET['taxonomy'] ) ) {
			return false;
		}

		$taxonomy_slug = sanitize_key( $_GET['taxonomy'] );

		if ( ! self::is_translatable_taxonomy_static( $taxonomy_slug ) ) {
			return false;
		}

		return get_taxonomy( $taxonomy_slug );
	}

	/**
	 * @param string $taxonomy The taxonomy slug to check.
	 * @return bool True if the taxonomy is translatable, false otherwise.
	 *
	 * Check if a taxonomy is configured as translatable in WPML.
	 *
	 * Verifies that the taxonomy exists and is in the list of translatable
	 * taxonomies for its associated post type.
	 */
	private static function is_translatable_taxonomy_static( $taxonomy ) {
		global $sitepress;

		if ( ! $sitepress ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object ) {
			return false;
		}

		$post_type               = isset( $taxonomy_object->object_type[0] ) ? $taxonomy_object->object_type[0] : 'post';
		$translatable_taxonomies = $sitepress->get_translatable_taxonomies( true, $post_type );

		return in_array( $taxonomy, $translatable_taxonomies, true );
	}

	/**
	 * @param \WP_Taxonomy $taxonomy
	 *
	 * @return string
	 */
	private function build_tag_to_taxonomy_translation( $taxonomy ) {

		$url = add_query_arg(
			array(
				'page'     => WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php',
				'taxonomy' => $taxonomy->name,
			),
			admin_url( 'admin.php' )
		);
		$url = apply_filters( 'wpml_taxonomy_term_translation_url', $url, $taxonomy->name );

		return '<a href="' . esc_url( $url ) . '">' .
			   sprintf( esc_html__( ' %s translation', 'sitepress' ), $taxonomy->labels->singular_name ) . '</a>';
	}

	/**
	 * @param WPML_Notice $notice
	 */
	public function set_notice( WPML_Notice $notice ) {
		$this->notice = $notice;
	}

	/**
	 * @return WPML_Notice
	 */
	public function get_notice() {
		return $this->notice;
	}

	/**
	 * Enqueue JS callback script.
	 */
	public function enqueue_scripts() {
		$notice = $this->get_notice();
		if ( $notice ) {
			wp_register_script( 'wpml-dismiss-taxonomy-help-notice', ICL_PLUGIN_URL . '/res/js/dismiss-taxonomy-help-notice.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_localize_script(
				'wpml-dismiss-taxonomy-help-notice',
				'wpml_notice_information',
				array(
					'notice_id'    => $notice->get_id(),
					'notice_group' => $notice->get_group(),
				)
			);
			wp_enqueue_script( 'wpml-dismiss-taxonomy-help-notice' );
		}
	}
}
