<?php
/**
 * Plugin Name: HFS — hide properties tagged "ascuns"
 * Description: Any post tagged "ascuns" (hidden) disappears from ALL
 * front-end listings — category archives, the homepage/listing Loop Grids,
 * search, feeds — while staying reachable at its direct URL. Combine with
 * The SEO Framework's per-post noindex (which also drops it from the TSF
 * sitemap) for full "hidden but shareable by link" behaviour.
 * Owner procedure: migration/output/hide-a-property.md
 *
 * @since July 25, 2026 (hotelsforsale migration, owner-requested capability)
 */

defined('ABSPATH') || exit;

add_action('pre_get_posts', function ($query) {
    if (is_admin()) {
        return;
    }
    // Never touch singular views — a hidden property stays reachable by
    // direct link (that's the point of the "ascuns" option).
    if ($query->is_singular()) {
        return;
    }
    $term = get_term_by('slug', 'ascuns', 'post_tag');
    if (!$term) {
        return;
    }
    $not_in = $query->get('tag__not_in') ?: [];
    if (!in_array($term->term_id, (array) $not_in, true)) {
        $not_in[] = $term->term_id;
        $query->set('tag__not_in', $not_in);
    }
});
