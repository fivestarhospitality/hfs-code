<?php
/**
 * Plugin Name: HFS — language switcher as last Main Menu item
 * Description: Appends the WPML language links (RO/EN) as the last item of
 * the "Main Menu", styled like the other menu entries. Shows the OTHER
 * language(s) only; falls back to the language home when the current page
 * has no translation. Oct 9, 2026: links show a flag + the language code
 * ("EN" / "RO", menu typography; native name as tooltip), and the same link is
 * available inside pages via the [hfs_lang_switch] shortcode (rendered only
 * when the page actually has a translation).
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
        $items .= sprintf(
            '<li class="menu-item menu-item-type-custom menu-item-object-custom hfs-lang-switch">%s</li>',
            hfs_lang_link($lang, 'elementor-item')
        );
    }
    return $items;
}, 10, 2);

/**
 * Flag + code link ("🇬🇧 EN" / "🇷🇴 RO"), owner request Oct 9, 2026. Flags are inline SVG so the switcher needs no media files.
 */
function hfs_lang_flag_svg($code)
{
    static $n = 0;
    $n++;
    if ('ro' === $code) {
        return '<svg viewBox="0 0 3 2" aria-hidden="true" focusable="false"><rect width="1" height="2" fill="#002B7F"/><rect x="1" width="1" height="2" fill="#FCD116"/><rect x="2" width="1" height="2" fill="#CE1126"/></svg>';
    }
    if ('en' === $code) {
        $c = "hfs-uk-c$n";
        $t = "hfs-uk-t$n";
        return '<svg viewBox="0 0 60 30" aria-hidden="true" focusable="false">'
            . '<clipPath id="' . $c . '"><path d="M0,0v30h60V0z"/></clipPath>'
            . '<clipPath id="' . $t . '"><path d="M30,15h30v15zv15H0zH0V0zV0h30z"/></clipPath>'
            . '<g clip-path="url(#' . $c . ')">'
            . '<path d="M0,0v30h60V0z" fill="#012169"/>'
            . '<path d="M0,0L60,30M60,0L0,30" stroke="#fff" stroke-width="6"/>'
            . '<path d="M0,0L60,30M60,0L0,30" clip-path="url(#' . $t . ')" stroke="#C8102E" stroke-width="4"/>'
            . '<path d="M30,0v30M0,15h60" stroke="#fff" stroke-width="10"/>'
            . '<path d="M30,0v30M0,15h60" stroke="#C8102E" stroke-width="6"/>'
            . '</g></svg>';
    }
    return '';
}

function hfs_lang_link(array $lang, $extra_class = '')
{
    // menu passes 'elementor-item' so the nav-menu typography (size, hover) applies
    return sprintf(
        '<a class="hfs-lang-link%s" href="%s" hreflang="%s" lang="%s" title="%s"><span class="hfs-flag">%s</span><span class="hfs-lang-name">%s</span></a>',
        $extra_class ? ' ' . esc_attr($extra_class) : '',
        esc_url($lang['url']),
        esc_attr($lang['language_code']),
        esc_attr($lang['language_code']),
        esc_attr($lang['native_name']),
        hfs_lang_flag_svg($lang['language_code']),
        esc_html(strtoupper($lang['language_code']))
    );
}

/**
 * [hfs_lang_switch] — the same flag + name link for use inside a page
 * (Elementor Shortcode widget, e.g. right under the hero). Unlike the menu
 * item it renders NOTHING when the current page has no translation, so
 * single-language properties never send readers to the other language home.
 */
add_shortcode('hfs_lang_switch', function () {
    $langs = apply_filters('wpml_active_languages', null, ['skip_missing' => 1]);
    if (empty($langs)) {
        return '';
    }
    $out = '';
    foreach ($langs as $lang) {
        if (empty($lang['active'])) {
            $out .= hfs_lang_link($lang);
        }
    }
    return $out ? '<div class="hfs-lang-inline">' . $out . '</div>' : '';
});

add_action('wp_head', function () {
    ?>
<style id="hfs-lang-switch-css">
.hfs-lang-link{display:inline-flex;align-items:center;gap:.5em;white-space:nowrap}
.hfs-lang-link .hfs-flag{display:inline-flex;line-height:0}
.hfs-lang-link .hfs-flag svg{height:.85em;width:auto;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.12)}
.hfs-lang-inline{text-align:center;margin:6px 0}
.hfs-lang-inline .hfs-lang-link{font-family:Montserrat,sans-serif;font-size:17px;font-weight:500;text-transform:uppercase;letter-spacing:4px;color:#093782;text-decoration:none}
.hfs-lang-inline .hfs-lang-link:hover{color:#F9BD27}
</style>
    <?php
});
