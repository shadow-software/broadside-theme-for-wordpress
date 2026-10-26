<?php
/**
 * Post-view counters and view-ranked category helpers.
 *
 * Each singular article view increments `_digest_views` on that post. The
 * section grid ("Inside This Week's Edition") and any other reader-facing
 * category list then ranks categories by the SUM of those views across their
 * published posts — so the paper steers itself toward what people actually
 * read, while n8n keeps filing into whatever categories the RSS beat produces.
 *
 * @package Broadside
 * @since   1.3.4
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Let Polylang filter a secondary query.
 *
 * get_posts() suppresses filters unless told not to, which drops the language
 * clause. Category terms are already per-language under Polylang; this keeps
 * the post list in that language too.
 *
 * @since 1.4.4
 * @param array<string, mixed> $args Query arguments.
 * @return array<string, mixed>
 */
function shadow_digest_language_query_args( array $args ): array {
	$args['suppress_filters'] = false;

	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();

		if ( is_string( $lang ) && '' !== $lang ) {
			$args['lang'] = $lang;
		}
	}

	return $args;
}

/**
 * Meta key for the per-post view counter.
 */
const SHADOW_DIGEST_VIEWS_META = '_digest_views';

/**
 * Increment the view counter once per request on a published singular post.
 *
 * Skips logged-in editors/admins, previews, feeds, REST, and bots with an empty
 * or obvious crawler UA — we want reader signal, not self-traffic or scrapers.
 *
 * @since 1.3.4
 */
function shadow_digest_track_post_view(): void {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( ! is_singular( 'post' ) || is_preview() ) {
		return;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	if ( $post_id < 1 || 'publish' !== get_post_status( $post_id ) ) {
		return;
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview/i', $ua ) ) {
		return;
	}

	$current = (int) get_post_meta( $post_id, SHADOW_DIGEST_VIEWS_META, true );
	update_post_meta( $post_id, SHADOW_DIGEST_VIEWS_META, $current + 1 );
}
add_action( 'template_redirect', 'shadow_digest_track_post_view', 20 );

/**
 * Categories ranked by total post views (then by post count), hide-empty.
 *
 * @since 1.3.4
 * @param int $limit Max categories to return.
 * @return array<int, WP_Term>
 */
function shadow_digest_categories_by_views( int $limit = 6 ): array {
	$limit = max( 1, min( 24, $limit ) );

	$categories = get_categories(
		shadow_digest_language_query_args(
			array(
				'hide_empty' => true,
				'number'     => 100,
			)
		)
	);

	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return array();
	}

	$scored = array();

	foreach ( $categories as $category ) {
		$posts = get_posts(
			shadow_digest_language_query_args(
				array(
					'category'            => (int) $category->term_id,
					'numberposts'         => -1,
					'post_status'         => 'publish',
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			)
		);

		$views = 0;
		foreach ( $posts as $post_id ) {
			$views += (int) get_post_meta( (int) $post_id, SHADOW_DIGEST_VIEWS_META, true );
		}

		$scored[] = array(
			'term'  => $category,
			'views' => $views,
			'count' => count( $posts ),
		);
	}

	usort(
		$scored,
		static function ( array $a, array $b ): int {
			if ( $a['views'] !== $b['views'] ) {
				return $b['views'] <=> $a['views'];
			}
			if ( $a['count'] !== $b['count'] ) {
				return $b['count'] <=> $a['count'];
			}
			return strcasecmp( $a['term']->name, $b['term']->name );
		}
	);

	$out = array();
	foreach ( array_slice( $scored, 0, $limit ) as $row ) {
		$out[] = $row['term'];
	}

	return $out;
}

/**
 * Post IDs ranked by view count, most-viewed first, restricted to posts
 * published in the last $window_days.
 *
 * Bounded to a recent window on purpose: an all-time ranking lets one old
 * viral post camp a "what readers are into right now" slot forever. This
 * is a genuinely different question from shadow_digest_categories_by_views()
 * (all-time, for the section grid), so it is a separate query rather than a
 * shared helper with a window parameter bolted on.
 *
 * @since 1.3.7
 * @param int $limit       Max post IDs to return.
 * @param int $window_days How many days back to consider.
 * @return array<int, int> Post IDs, most-viewed first.
 */
function shadow_digest_posts_by_views( int $limit = 3, int $window_days = 30 ): array {
	$limit       = max( 1, min( 24, $limit ) );
	$window_days = max( 1, $window_days );

	// Fetch by date first, THEN sort by view count in PHP rather than an
	// `orderby => meta_value_num` SQL join: that join is an INNER JOIN on
	// postmeta, which silently drops every post that has never had
	// _digest_views written at all — i.e. every post with zero views so
	// far, which is exactly a brand-new article that deserves a chance to
	// surface, not exclusion.
	$posts = get_posts(
		shadow_digest_language_query_args(
			array(
				'numberposts'         => 100,
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'date_query'          => array(
					array( 'after' => $window_days . ' days ago' ),
				),
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		)
	);

	$views = array();
	foreach ( $posts as $post_id ) {
		$views[ (int) $post_id ] = (int) get_post_meta( (int) $post_id, SHADOW_DIGEST_VIEWS_META, true );
	}

	arsort( $views );

	return array_map( 'absint', array_slice( array_keys( $views ), 0, $limit ) );
}
