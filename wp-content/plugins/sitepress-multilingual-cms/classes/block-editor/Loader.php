<?php


namespace WPML\BlockEditor;

use WPML\BlockEditor\Blocks\LanguageSwitcher;
use WPML\LIB\WP\Hooks;
use WPML\Core\WP\App\Resources;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

class Loader implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const BLOCK_LANGUAGE_SWITCHER            = 'wpml/language-switcher';
	const BLOCK_LANGUAGE_SWITCHER_NAVIGATION = 'wpml/navigation-language-switcher';

	const SCRIPT_NAME = 'wpml-blocks';

	/** @var array Contains the script data that needs to be localized for the registered blocks. */
	private $localizedScriptData = [];

	/** @var [string] Name of blocks which css is already loaded. */
	private $cssLoaded = [];

	/** @var bool */
	private $keyboard_script_enqueued = false;

	public function add_hooks() {
		if ( \WPML_Block_Editor_Helper::is_active() ) {
			Hooks::onAction( 'init' )
				->then( [ $this, 'registerBlocks' ] )
				->then( [ $this, 'maybeEnqueueNavigationBlockStyles' ] );

			add_filter(
				'render_block',
				[ $this, 'frontendPrintStyleIfBlockIsUsed' ],
				10,
				2
			);

			Hooks::onAction( 'enqueue_block_assets' )
			     ->then( [ $this, 'enqueueBlockAssets' ] );

			Hooks::onFilter( 'block_categories_all', 10, 2 )
				->then( spreadArgs( [ $this, 'registerCategory' ] ) );
		}
	}

	/**
	 * @param array[] $block_categories
	 *
	 * @return mixed
	 */
	public function registerCategory( $block_categories ) {
		array_push(
			$block_categories,
			[
				'slug'  => 'wpml',
				'title' => __( 'WPML', 'sitepress-multilingual-cms' ),
				'icon'  => null,
			]
		);

		return $block_categories;
	}

	/**
	 * Register blocks that need server side render.
	 */
	public function registerBlocks() {
		$LSLocalizedScriptData     = make( LanguageSwitcher::class )->register();
		$this->localizedScriptData = array_merge( $this->localizedScriptData, $LSLocalizedScriptData );
	}

	/**
	 * @return void
	 */
	public function enqueueBlockAssets() {
		// Only enqueue in editor context to avoid loading on frontend
		if ( ! is_admin() ) {
			return;
		}

		$dependencies        = array_merge( [
			'wp-blocks',
			'wp-i18n',
			'wp-element',
		], $this->getEditorDependencies() );
		$localizedScriptData = [ 'name' => 'WPMLBlocks', 'data' => $this->localizedScriptData ];
		$this->enqueueBlocksApp( $localizedScriptData, $dependencies );
	}

	/**
	 * Enqueue the block editor bundle with filemtime-based versioning so
	 * iterative branch testing does not reuse stale cached assets.
	 *
	 * @param array    $localizedScriptData
	 * @param string[] $dependencies
	 *
	 * @return void
	 */
	private function enqueueBlocksApp( array $localizedScriptData, array $dependencies ) {
		$handle         = 'wpml-blocks-ui';
		$scriptRelative = '/dist/js/blocks/app.js';
		$styleRelative  = '/dist/css/blocks/styles.css';
		$scriptPath     = WPML_PLUGIN_PATH . $scriptRelative;
		$stylePath      = WPML_PLUGIN_PATH . $styleRelative;
		$scriptVersion  = file_exists( $scriptPath ) ? (string) filemtime( $scriptPath ) : (string) ICL_SITEPRESS_SCRIPT_VERSION;
		$styleVersion   = file_exists( $stylePath ) ? (string) filemtime( $stylePath ) : $scriptVersion;

		wp_register_script(
			$handle,
			ICL_PLUGIN_URL . $scriptRelative,
			$dependencies,
			$scriptVersion,
			false
		);

		wp_localize_script( $handle, $localizedScriptData['name'], $localizedScriptData['data'] );
		wp_enqueue_script( $handle );

		if ( file_exists( $stylePath ) ) {
			wp_enqueue_style(
				$handle,
				ICL_PLUGIN_URL . $styleRelative,
				[],
				$styleVersion
			);
		}

		wp_set_script_translations( $handle, 'sitepress', WPML_PLUGIN_PATH . '/locale/jed' );
	}

	public function frontendPrintStyleIfBlockIsUsed( $content, $block ) {
		if ( is_admin() ) {
			// Dependening on the setup the backend might also use render_block.
			// We don't want to include the style in that case as the full css
			// file is already loaded. Same for ajax requests.
			return $content;
		}

		if (
			self::BLOCK_LANGUAGE_SWITCHER !== $block['blockName'] &&
			self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION !== $block['blockName']
		) {
			// No language switcher block.
			return $content;
		}

		// Always include language switcher styles.
		$css = $this->styleLanguageSwitcher();
		$this->enqueueLanguageSwitcherKeyboardScript();

		// Check if the navigation language switcher is used.
		if ( self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION === $block['blockName'] ) {
			$css .= $this->styleLanguageSwitcherNavigation();

			// Both css files are loaded, so we can remove the filter.
			remove_filter(
				'render_block',
				[ $this, 'frontendPrintStyleIfBlockIsUsed' ]
			);
		}

		return ! empty( $css )
			? '<style>' . $css . '</style>' . $content
			: $content;
	}

	/**
	 * @return string
	 */
	private function styleLanguageSwitcher() {
		if ( in_array( self::BLOCK_LANGUAGE_SWITCHER, $this->cssLoaded, true ) ) {
			return '';
		}

		$this->cssLoaded[] = self::BLOCK_LANGUAGE_SWITCHER;
		$css               = file_get_contents(
			WPML_PLUGIN_PATH . '/dist/css/blocks/language-switcher.css'
		);

		return $css ?: '';
	}

	/**
	 * Enqueue the keyboard accessibility script for the Language Switcher blocks.
	 *
	 * @return void
	 * @see \WPML_LS_Render::add_menu_accessibility_script()
	 */
	private function enqueueLanguageSwitcherKeyboardScript() {
		if ( $this->keyboard_script_enqueued ) {
			return;
		}

		$this->keyboard_script_enqueued = true;
		$scriptRelativePath             = '/dist/js/language-switcher-block-keyboard-navigation/app.js';
		$scriptPath                     = WPML_PLUGIN_PATH . $scriptRelativePath;
		$scriptVersion                  = file_exists( $scriptPath ) ? (string) filemtime( $scriptPath ) : (string) ICL_SITEPRESS_SCRIPT_VERSION;

		wp_register_script(
			'wpml-language-switcher-block-keyboard-navigation',
			ICL_PLUGIN_URL . $scriptRelativePath,
			[],
			$scriptVersion,
			true
		);

		wp_enqueue_script( 'wpml-language-switcher-block-keyboard-navigation' );
	}

	/**
	 * @return string
	 */
	private function styleLanguageSwitcherNavigation() {
		if ( in_array( self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION, $this->cssLoaded, true ) ) {
			return '';
		}

		$this->cssLoaded[] = self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION;
		$css               = file_get_contents(
			WPML_PLUGIN_PATH . '/dist/css/blocks/language-switcher-navigation.css'
		);

		return $css ?: '';
	}

	/**
	 * We inherit the WP navigation block styles while rendering our Language Switcher Block,
	 * so when there's no navigation block is rendered, we still need to enqueue the wp-block-navigation styles so that.,
	 * the Language Switcher Block renders properly.
	 *
	 * @return void
	 * @see wpmldev-2422
	 * @see wpmldev-2491
	 *
	 */
	public function maybeEnqueueNavigationBlockStyles() {

		if ( ! wp_style_is( 'wp-block-navigation', 'enqueued' ) || ! wp_style_is( 'wp-block-navigation', 'queue' ) ) {
			add_filter( 'render_block', function ( $blockContent, $block ) {
				if ( $block['blockName'] === LanguageSwitcher::BLOCK_LANGUAGE_SWITCHER ) {
					wp_enqueue_style( 'wp-block-navigation' );
				}

				return $blockContent;
			}, 10, 2 );
		}
	}

	/**
	 * @return string[]
	 */
	public function getEditorDependencies() {
		global $pagenow;

		if ( is_admin() && 'widgets.php' === $pagenow ) {
			return [ 'wp-edit-widgets' ];
		}

		return [ 'wp-editor' ];
	}
}
