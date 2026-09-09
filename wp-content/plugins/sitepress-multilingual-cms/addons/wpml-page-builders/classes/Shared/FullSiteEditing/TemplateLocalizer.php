<?php

namespace WPML\PB\FullSiteEditing;

/**
 * Builds language-specific FSE template slugs from a source template slug
 * (e.g. category-test → category-ejemplo for the target language which is need for WordPress to find the correct template).
 */
class TemplateLocalizer {

	/** @var string[] */
	const TEMPLATE_POST_TYPES = [ 'wp_template', 'wp_template_part' ];
	/** @var string[] */
	const JOB_TYPES = [ 'post_wp_template', 'post_wp_template_part' ];

	/**
	 * @param string $postType
	 * @return bool
	 */
	public static function isTemplate( $postType ) {
		return in_array( $postType, self::TEMPLATE_POST_TYPES, true );
	}

	/**
	 * @param string $jobType
	 * @return bool
	 */
	public static function isJobType( $jobType ) {
		return in_array( $jobType, self::JOB_TYPES, true );
	}

	/**
	 * Returns the localized template slug for the given language, or the source slug when no translation applies.
	 *
	 * @param string      $sourceTemplateSlug Original template post_name (e.g. category-test, page-42).
	 * @param string|null $languageCode       Target language code.
	 * @return string Localized slug.
	 */
	public static function getLocalizedTemplateSlug( $sourceTemplateSlug, $languageCode ) {
		if ( ! $languageCode || strpos( $sourceTemplateSlug, '-' ) === false ) {
			return $sourceTemplateSlug;
		}

		if ( preg_match( '/^(category|tag)-(.+)$/', $sourceTemplateSlug, $m ) ) {
			$taxonomy   = 'tag' === $m[1] ? 'post_tag' : 'category';
			$translated = self::translatedTermSlug( $taxonomy, $m[2], $languageCode );
			return $translated ? $m[1] . '-' . $translated : $sourceTemplateSlug;
		}

		$taxonomyMatch = self::matchTaxonomyTemplateSlug( $sourceTemplateSlug );
		if ( $taxonomyMatch ) {
			list( $taxonomy, $termSlug ) = $taxonomyMatch;
			$translated                  = self::translatedTermSlug( $taxonomy, $termSlug, $languageCode );
			return $translated ? 'taxonomy-' . $taxonomy . '-' . $translated : $sourceTemplateSlug;
		}

		if ( preg_match( '/^page-(.+)$/', $sourceTemplateSlug, $m ) ) {
			$translated = self::translatedPostSlug( 'page', $m[1], $languageCode );
			return $translated ? 'page-' . $translated : $sourceTemplateSlug;
		}

		$singleMatch = self::matchSingleTemplateSlug( $sourceTemplateSlug );
		if ( $singleMatch ) {
			list( $postType, $postSlug, $prefixSegment ) = $singleMatch;
			$translated = self::translatedPostSlug( $postType, $postSlug, $languageCode ); // phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			return $translated ? 'single-' . $prefixSegment . '-' . $translated : $sourceTemplateSlug;
		}

		return $sourceTemplateSlug;
	}

	/**
	 * Parses a taxonomy template slug (e.g. taxonomy-my-tax-term-slug) into taxonomy and term slug.
	 * Supports taxonomy names and rewrite slugs that contain hyphens by matching against registered taxonomies
	 * (longest prefix first).
	 *
	 * @param string $sourceTemplateSlug Slug such as taxonomy-{taxonomy}-{term-slug}.
	 * @return array|null [ $taxonomy, $termSlug ] or null if no match.
	 */
	private static function matchTaxonomyTemplateSlug( $sourceTemplateSlug ) {
		$prefix = 'taxonomy-';
		if ( ! self::slugMatchesPrefix( $sourceTemplateSlug, $prefix ) ) {
			return null;
		}

		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		if ( ! $taxonomies ) {
			return null;
		}

		$match = self::matchTemplateSlugWithObjects( $sourceTemplateSlug, $prefix, $taxonomies );
		if ( ! $match ) {
			return null;
		}

		return [ $match[0], $match[1] ];
	}

	/**
	 * Parses a single template slug (e.g. single-book-my-story) into post type, post slug and the
	 * prefix segment used in the slug (either the post type name or its rewrite slug).
	 *
	 * @param string $sourceTemplateSlug Slug such as single-{post-type-or-rewrite}-{post-slug}.
	 * @return array|null [ $postType, $postSlug, $prefixSegment ] or null if no match.
	 */
	private static function matchSingleTemplateSlug( $sourceTemplateSlug ) {
		$prefix = 'single-';
		if ( ! self::slugMatchesPrefix( $sourceTemplateSlug, $prefix ) ) {
			return null;
		}

		$postTypes = get_post_types( [ 'public' => true ], 'objects' );
		if ( ! $postTypes ) {
			return null;
		}

		return self::matchTemplateSlugWithObjects( $sourceTemplateSlug, $prefix, $postTypes );
	}

	/**
	 * Checks that the slug starts with the given prefix and has at least one hyphen after it.
	 *
	 * @param string $sourceTemplateSlug Full template slug.
	 * @param string $prefix             Prefix such as 'taxonomy-' or 'single-'.
	 * @return bool
	 */
	private static function slugMatchesPrefix( $sourceTemplateSlug, $prefix ) {
		if ( strpos( $sourceTemplateSlug, $prefix ) !== 0 ) {
			return false;
		}
		$afterPrefix = substr( $sourceTemplateSlug, strlen( $prefix ) );

		return '' !== $afterPrefix && strpos( $afterPrefix, '-' ) !== false;
	}

	/**
	 * Generic helper to match template slugs of the form:
	 *   {prefix}{name-or-rewrite}-{slug}
	 * against a collection of objects keyed by name and possibly defining a rewrite slug.
	 *
	 * @param string $sourceTemplateSlug Full template slug.
	 * @param string $prefix             Prefix such as 'taxonomy-' or 'single-'.
	 * @param array  $objects            Objects keyed by name (taxonomy or post type).
	 *
	 * @return array|null [ $name, $slug, $prefixSegment ] or null if no match.
	 */
	private static function matchTemplateSlugWithObjects( $sourceTemplateSlug, $prefix, array $objects ) {
		if ( ! self::slugMatchesPrefix( $sourceTemplateSlug, $prefix ) ) {
			return null;
		}

		$prefixes = [];

		foreach ( $objects as $name => $object ) {
			$segments = [ $name ];

			if ( isset( $object->rewrite['slug'] ) && $object->rewrite['slug'] ) {
				$segments[] = $object->rewrite['slug'];
			}

			$segments = array_unique( $segments );

			foreach ( $segments as $segment ) {
				$fullPrefix = $prefix . $segment . '-';
				$prefixes[] = [
					'name'          => $name,
					'prefix'        => $fullPrefix,
					'prefixSegment' => $segment,
				];
			}
		}

		usort(
			$prefixes,
			function ( $a, $b ) {
				return strlen( $b['prefix'] ) - strlen( $a['prefix'] );
			}
		);

		foreach ( $prefixes as $entry ) {
			if ( 0 !== strpos( $sourceTemplateSlug, $entry['prefix'] ) ) {
				continue;
			}

			$slug = substr( $sourceTemplateSlug, strlen( $entry['prefix'] ) );
			if ( '' !== $slug ) {
				return [ $entry['name'], $slug, $entry['prefixSegment'] ];
			}
		}

		return null;
	}

	/**
	 * Resolves the translated term slug for a taxonomy and slug-or-id in the given language.
	 *
	 * @param string     $taxonomy     Taxonomy name (e.g. category, post_tag).
	 * @param string|int $slugOrId     Term slug or term_id.
	 * @param string     $languageCode Target language code.
	 * @return string|null Translated slug or null if not found.
	 */
	private static function translatedTermSlug( $taxonomy, $slugOrId, $languageCode ) {
		if ( ctype_digit( (string) $slugOrId ) ) {
			$sourceId = (int) $slugOrId;
		} else {
			$term     = get_term_by( 'slug', $slugOrId, $taxonomy );
			$sourceId = $term ? (int) $term->term_id : null;
		}
		if ( ! $sourceId ) {
			return null;
		}
		$translatedId = apply_filters( 'wpml_object_id', $sourceId, $taxonomy, false, $languageCode );
		if ( ! $translatedId ) {
			return null;
		}
		$slug = get_term_field( 'slug', (int) $translatedId, $taxonomy );
		return ( $slug && ! is_wp_error( $slug ) ) ? $slug : null;
	}

	/**
	 * Resolves the translated post slug for a post type and slug-or-id in the given language.
	 *
	 * @param string     $postType     Post type (e.g. post, page).
	 * @param string|int $slugOrId     Post slug or post ID.
	 * @param string     $languageCode Target language code.
	 * @return string|null Translated slug or null if not found.
	 */
	private static function translatedPostSlug( $postType, $slugOrId, $languageCode ) {
		if ( ctype_digit( (string) $slugOrId ) ) {
			$sourceId = (int) $slugOrId;
		} else {
			$posts    = get_posts(
				[
					'name'           => $slugOrId,
					'post_type'      => $postType,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			);
			$sourceId = isset( $posts[0] ) ? (int) $posts[0] : null;
		}
		if ( ! $sourceId ) {
			return null;
		}
		$translatedId = apply_filters( 'wpml_object_id', $sourceId, $postType, false, $languageCode );
		if ( ! $translatedId ) {
			return null;
		}
		return get_post_field( 'post_name', (int) $translatedId ) ?: null;
	}
}
