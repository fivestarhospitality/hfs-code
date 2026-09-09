<?php

#[AllowDynamicProperties]
class WPML_LS_Menu_Item {

	/**
	 * @see wp_setup_nav_menu_item() to decorate the object
	 */
	/** @var mixed The term_id if the menu item represents a taxonomy term. */
	public $ID;

	/** @var mixed The title attribute of the link element for this menu item. */
	public $attr_title;

	/** @var mixed The aria-label attribute of the link element for this menu item. */
	public $aria_label;

	/** @var mixed The aria-expanded attribute for parent menu items with submenus. */
	public $aria_expanded;

	/** @var mixed The aria-controls attribute linking to submenu ID. */
	public $aria_controls;

	/** @var string The role attribute for the link element. */
	public $link_role = '';

	/** @var string The role attribute for the li element. */
	public $item_role = '';

	/** @var array The array of class attribute values for the link element of this menu item. */
	public $classes = array();

	/** @var mixed The DB ID of this item as a nav_menu_item object, if it exists (0 if it doesn't exist). */
	public $db_id;

	/** @var mixed The description of this menu item. */
	public $description;

	/** @var mixed The DB ID of the nav_menu_item that is this item's menu parent, if any. 0 otherwise. */
	public $menu_item_parent;

	/** @var string The type of object originally represented, such as "category," "post", or "attachment." */
	public $object = 'wpml_ls_menu_item';

	/** @var mixed The DB ID of the original object this menu item represents, e.g. ID for posts and term_id for categories. */
	public $object_id;

	/** @var mixed The DB ID of the original object's parent object, if any (0 otherwise). */
	public $post_parent;

	/** @var mixed A "no title" label if menu item represents a post that lacks a title. */
	public $post_title;

	/** @var mixed The target attribute of the link element for this menu item. */
	public $target;

	/** @var mixed The title of this menu item. */
	public $title;

	/** @var string The family of objects originally represented, such as "post_type" or "taxonomy." */
	public $type = 'wpml_ls_menu_item';

	/** @var mixed The singular label used to describe this type of menu item. */
	public $type_label;

	/** @var mixed The URL to which this menu item points. */
	public $url;

	/** @var mixed The XFN relationship expressed in the link of this menu item. */
	public $xfn;

	/** @var bool Whether the menu item represents an object that no longer exists. */
	public $_invalid = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
	/** @var mixed The menu order of this menu item. */
	public $menu_order;

	/** @var string The post type of this menu item. Extra property => see [wpmlcore-3855]. */
	public $post_type = 'nav_menu_item';

	/**
	 * WPML_LS_Menu_Item constructor.
	 *
	 * @param array  $language
	 * @param string $item_content
	 */
	public function __construct( $language, $item_content ) {
		$this->decorate_object( $language, $item_content );
	}

	/**
	 * @param array  $lang
	 * @param string $item_content
	 */
	private function decorate_object( $lang, $item_content ) {
		$this->ID               = isset( $lang['db_id'] ) ? $lang['db_id'] : null;
		$this->object_id        = isset( $lang['db_id'] ) ? $lang['db_id'] : null;
		$this->db_id            = isset( $lang['db_id'] ) ? $lang['db_id'] : null;
		$this->menu_item_parent = isset( $lang['menu_item_parent'] ) ? $lang['menu_item_parent'] : null;

		$is_current_lang       = isset( $lang['is_current'] ) ? $lang['is_current'] : null;
		$is_dropdown_ls_parent = isset( $lang['is_parent'] ) ? $lang['is_parent'] : false;
		$ls_menu_item_label    = ! $is_current_lang ? $lang['menu_item_label'] : '';

		$this->aria_label = $ls_menu_item_label;
		$this->attr_title = $ls_menu_item_label;

		if ( $is_dropdown_ls_parent ) {
			$this->aria_expanded = 'false';
			$this->aria_controls = 'wpml-ls-submenu-' . ( isset( $lang['db_id'] ) ? $lang['db_id'] : 'default' );
		}

		$this->title      = $item_content;
		$this->post_title = $item_content;
		$this->url        = isset( $lang['url'] ) ? $lang['url'] : null;

		if ( isset( $lang['css_classes'] ) ) {
			$this->classes = $lang['css_classes'];
			if ( is_string( $lang['css_classes'] ) ) {
				$this->classes = explode( ' ', $lang['css_classes'] );
			}
		}
	}

	/**
	 * @param string $property
	 *
	 * @return mixed
	 */
	public function __get( $property ) {
		return isset( $this->{$property} ) ? $this->{$property} : null;
	}
}
