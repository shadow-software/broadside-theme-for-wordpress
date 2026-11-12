<?php
/**
 * Rank Math integration for Digest news sites.
 *
 * Broadside defers article JSON-LD to Rank Math when RANK_MATH_VERSION is defined.
 * These hooks make that delegation actually match a journal of record: NewsArticle
 * (not generic Article), podcast VideoObject on the same URL as the player, and
 * canonical enforcement for legacy short permalinks.
 *
 * @package Broadside
 * @since   1.4.6
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Default Rank Math article schema type for editorial posts.
 *
 * @since 1.4.6
 * @param string $type      Current article type.
 * @param string $post_type Post type slug.
 * @return string
 */
function shadow_digest_rank_math_default_article_type( string $type, string $post_type ): string {
	if ( 'post' === $post_type ) {
		return 'NewsArticle';
	}

	return $type;
}
add_filter( 'rank_math/settings/snippet/article_type', 'shadow_digest_rank_math_default_article_type', 10, 2 );

/**
 * Whether a schema node type is Article (or an array containing it).
 *
 * @since 1.4.6
 * @param mixed $type @type value from JSON-LD.
 * @return bool
 */
function shadow_digest_schema_node_is_article( $type ): bool {
	if ( 'Article' === $type ) {
		return true;
	}

	return is_array( $type ) && in_array( 'Article', $type, true );
}

/**
 * Upgrade Article nodes in Rank Math's graph to NewsArticle and attach VideoObject when present.
 *
 * @since 1.4.6
 * @param array<string, mixed> $data   Rank Math JSON-LD payload.
 * @param mixed                $jsonld Rank Math JsonLD instance (unused).
 * @return array<string, mixed>
 */
function shadow_digest_rank_math_json_ld( array $data, $jsonld ): array {
	// Rank Math may run this filter before the global $post is set; fall back to the
	// queried object ID that the main query (or Rank Math) already resolved.
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$post = $queried;
		} else {
			$queried_id = (int) get_queried_object_id();
			if ( $queried_id > 0 ) {
				$post = get_post( $queried_id );
			}
		}
	}
	$permalink  = ( $post instanceof WP_Post ) ? (string) get_permalink( $post ) : '';
	$uses_graph = isset( $data['@graph'] ) && is_array( $data['@graph'] );
	$article_id = '';
	$targets    = $uses_graph ? $data['@graph'] : $data;

	if ( ! is_array( $targets ) || empty( $targets ) ) {
		return $data;
	}

	foreach ( $targets as $key => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		if ( shadow_digest_schema_node_is_article( $node['@type'] ?? '' ) ) {
			$targets[ $key ]['@type'] = 'NewsArticle';
			$article_id               = (string) ( $node['@id'] ?? ( '' !== $permalink ? $permalink . '#article' : '' ) );
		}
	}

	if ( $uses_graph ) {
		$data['@graph'] = $targets;
	} else {
		$data = $targets;
	}

	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $data;
	}

	if ( '' === $permalink ) {
		$permalink = (string) get_permalink( $post );
	}

	$video_url = (string) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_VIDEO_URL, true );
	if ( '' !== $video_url && wp_http_validate_url( $video_url ) ) {
		$video_id   = $permalink . '#episode-video';
		$video_node = array(
			'@type'       => 'VideoObject',
			'@id'         => $video_id,
			'name'        => shadow_digest_plain_text( get_the_title( $post ) ),
			'description' => shadow_digest_plain_text( get_the_excerpt( $post ) ),
			'uploadDate'  => (string) get_the_date( DATE_W3C, $post ),
			'contentUrl'  => esc_url_raw( $video_url ),
			'embedUrl'    => $permalink,
			'inLanguage'  => get_bloginfo( 'language' ),
		);

		if ( has_post_thumbnail( $post ) ) {
			$image = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post ), 'full' );
			if ( is_array( $image ) ) {
				$video_node['thumbnailUrl'] = $image[0];
			}
		}

		$duration = (string) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_DURATION, true );
		if ( '' !== $duration && preg_match( '/^\d+:\d{2}(:\d{2})?$/', $duration ) ) {
			$video_node['duration'] = shadow_digest_podcast_duration_to_iso8601( $duration );
		}

		if ( $uses_graph ) {
			$data['@graph'][] = $video_node;
			$link_targets     = &$data['@graph'];
		} else {
			$data['digest-episode-video'] = $video_node;
			$link_targets                 = &$data;
		}

		if ( '' !== $article_id ) {
			foreach ( $link_targets as $index => $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( ( $node['@id'] ?? '' ) === $article_id || 'NewsArticle' === ( $node['@type'] ?? '' ) ) {
					$link_targets[ $index ]['video'] = array( '@id' => $video_id );
					break;
				}
			}
		}
	}

	// Publisher identity (legalName / foundingDate) from theme Customizer — same
	// data the Digest Publisher Schema mu-plugin used to inject when the hook
	// lived in mu-plugins; kept here so one callback owns the Rank Math graph.
	if ( function_exists( 'shadow_digest_publisher_legal' ) && function_exists( 'shadow_digest_founded' ) ) {
		$legal   = shadow_digest_publisher_legal();
		$founded = shadow_digest_founded();
		if ( '' !== $legal || $founded ) {
			$targets = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : $data;
			foreach ( $targets as $key => $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				$type = $node['@type'] ?? '';
				if ( ! in_array( $type, array( 'Organization', 'NewsMediaOrganization', 'Publisher' ), true ) ) {
					continue;
				}
				if ( '' !== $legal ) {
					$targets[ $key ]['legalName'] = $legal;
				}
				if ( $founded ) {
					$targets[ $key ]['foundingDate'] = (string) $founded;
				}
			}
			if ( isset( $data['@graph'] ) ) {
				$data['@graph'] = $targets;
			}
		}
	}

	return $data;
}
add_filter( 'rank_math/json_ld', 'shadow_digest_rank_math_json_ld', 999, 2 );

/**
 * Convert podcast duration (mm:ss or hh:mm:ss) to ISO-8601 duration for VideoObject.
 *
 * @since 1.4.6
 * @param string $duration Human duration from post meta.
 * @return string ISO-8601 duration or empty string when unparsable.
 */
function shadow_digest_podcast_duration_to_iso8601( string $duration ): string {
	$parts = array_map( 'intval', explode( ':', $duration ) );
	$count = count( $parts );

	if ( 2 === $count ) {
		return sprintf( 'PT%dM%dS', $parts[0], $parts[1] );
	}

	if ( 3 === $count ) {
		return sprintf( 'PT%dH%dM%dS', $parts[0], $parts[1], $parts[2] );
	}

	return '';
}

/**
 * Redirect requests that hit a bare post slug to the dated canonical permalink.
 *
 * WordPress usually canonical-redirects when it knows the post, but rank-math +
 * migration history left some short URLs indexed separately; a hard 301 to
 * get_permalink() keeps one URL form in GSC.
 *
 * @since 1.4.6
 * @return void
 */
function shadow_digest_enforce_dated_permalink_redirect(): void {
	if ( is_admin() || ! is_singular( 'post' ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$canonical = (string) get_permalink( $post );
	$requested = shadow_digest_requested_url();

	if ( '' === $requested || '' === $canonical ) {
		return;
	}

	$canonical_path = (string) wp_parse_url( $canonical, PHP_URL_PATH );
	$requested_path = (string) wp_parse_url( $requested, PHP_URL_PATH );

	if ( '' === $canonical_path || '' === $requested_path ) {
		return;
	}

	if ( untrailingslashit( $canonical_path ) !== untrailingslashit( $requested_path ) ) {
		wp_safe_redirect( $canonical, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'shadow_digest_enforce_dated_permalink_redirect', 1 );

/**
 * The front-end URL the visitor requested (scheme + host + path).
 *
 * @since 1.4.6
 * @return string
 */
function shadow_digest_requested_url(): string {
	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return '';
	}

	$scheme = is_ssl() ? 'https' : 'http';
	$path   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path   = (string) wp_parse_url( $path, PHP_URL_PATH );

	return $scheme . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . $path;
}
