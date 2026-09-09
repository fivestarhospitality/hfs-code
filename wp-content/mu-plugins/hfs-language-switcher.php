<?php
/**
 * Plugin Name: HFS — language switcher as last Main Menu item
 * Description: Appends the WPML language links (RO/EN) as the last item of
 * the "Main Menu", styled like the other menu entries. Shows the OTHER
 * language(s) only; falls back to the language home when the current page
 * has no translation.
 *
 * @since July 25, 2026 (owner batch feedback)
 */

defined('ABSPATH') || exit;

/**
 * Per-language menu swap. The Elementor header widget references the menu by
 * slug ("main-menu"); in a non-default language WPML filters that RO term
 * away and the widget would render nothing. Swap in the language twin
 * ("main-menu-en", ...) BEFORE WPML's own wp_nav_menu_args filter runs.
 */
add_filter('wp_nav_menu_args', function ($args) {
    if (is_admin()) {
        return $args;
    }
    $slug = is_object($args['menu'] ?? null) ? ($args['menu']->slug ?? '') : (string) ($args['menu'] ?? '');
    if ('main-menu' !== $slug) {
        return $args;
    }
    $lang = apply_filters('wpml_current_language', null);
    $default = apply_filters('wpml_default_language', null);
    if (!$lang || $lang === $default) {
        return $args;
    }
    // WPML may hide the twin from get_term_by in this context — query raw.
    global $wpdb;
    $tid = $wpdb->get_var($wpdb->prepare(
        "SELECT t.term_id FROM {$wpdb->terms} t
         JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'nav_menu' AND t.slug = %s",
        'main-menu-' . $lang
    ));
    if ($tid) {
        $twin = wp_get_nav_menu_object((int) $tid);
        if ($twin) {
            $args['menu'] = $twin;
        }
    }
    return $args;
}, 5);

add_filter('wp_nav_menu_items', function ($items, $args) {
    // only the site's main menu (Elementor nav-menu widget renders by slug)
    $menu = $args->menu ?? null;
    $slug = is_object($menu) ? $menu->slug : $menu;
    // matches main-menu AND its per-language twins (main-menu-en, ...)
    if (!is_string($slug) || strpos($slug, 'main-menu') !== 0) {
        return $items;
    }
    $langs = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
    if (empty($langs)) {
        return $items;
    }
    foreach ($langs as $lang) {
        if (!empty($lang['active'])) {
            continue;
        }
        $label = strtoupper($lang['language_code']);
        $items .= sprintf(
            '<li class="menu-item menu-item-type-custom menu-item-object-custom hfs-lang-switch"><a href="%s" title="%s">%s</a></li>',
            esc_url($lang['url']),
            esc_attr($lang['native_name']),
            esc_html($label)
        );
    }
    return $items;
}, 10, 2);
