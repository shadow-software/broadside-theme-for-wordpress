<?php
/**
 * Podcast RSS feed + on-article audio/video player.
 *
 * Episodes are ordinary posts that carry `podcast_audio_url` (and optional
 * `podcast_media_id` / `podcast_bytes` / `podcast_duration`) post meta — written
 * by the n8n Cannabis Digest and Marksman Digest podcast generators. Phase 2
 * (issue #26) also writes video companions:
 * `podcast_video_url` / `podcast_video_media_id` / `podcast_video_bytes` /
 * `podcast_youtube_url`.
 *
 * This file:
 *
 *   1. Registers those meta keys for the REST API (so n8n can write them).
 *   2. Serves an iTunes-/Spotify-/Google-compatible feed at /feed/podcast/.
 *   3. Prepends a native <audio> player (and optional <video>) on single posts.
 *
 * No do_blocks() / apply_filters( 'the_content' ) recursion — the player HTML is
 * a static string prepended to $content. See docs/INCIDENT-2026-07-13-vps-outage.md.
 *
 * @package Broadside
 * @since   1.4.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Meta keys the podcast generator writes onto published posts.
 */
const SHADOW_DIGEST_PODCAST_META_URL      = 'podcast_audio_url';
const SHADOW_DIGEST_PODCAST_META_MEDIA_ID = 'podcast_media_id';
const SHADOW_DIGEST_PODCAST_META_BYTES    = 'podcast_bytes';
const SHADOW_DIGEST_PODCAST_META_DURATION = 'podcast_duration';

/** Phase 2 — captioned episode video (WP media; optional YouTube URL later). */
const SHADOW_DIGEST_PODCAST_META_VIDEO_URL      = 'podcast_video_url';
const SHADOW_DIGEST_PODCAST_META_VIDEO_MEDIA_ID = 'podcast_video_media_id';
const SHADOW_DIGEST_PODCAST_META_VIDEO_BYTES    = 'podcast_video_bytes';
const SHADOW_DIGEST_PODCAST_META_YOUTUBE_URL    = 'podcast_youtube_url';

/**
 * Register podcast post meta for REST writes from n8n.
 *
 * @since 1.4.0
 * @return void
 */
function shadow_digest_register_podcast_meta(): void {
	$keys = array(
		SHADOW_DIGEST_PODCAST_META_URL            => 'string',
		SHADOW_DIGEST_PODCAST_META_MEDIA_ID       => 'integer',
		SHADOW_DIGEST_PODCAST_META_BYTES          => 'integer',
		SHADOW_DIGEST_PODCAST_META_DURATION       => 'string',
		SHADOW_DIGEST_PODCAST_META_VIDEO_URL      => 'string',
		SHADOW_DIGEST_PODCAST_META_VIDEO_MEDIA_ID => 'integer',
		SHADOW_DIGEST_PODCAST_META_VIDEO_BYTES    => 'integer',
		SHADOW_DIGEST_PODCAST_META_YOUTUBE_URL    => 'string',
	);

	$url_keys = array(
		SHADOW_DIGEST_PODCAST_META_URL,
		SHADOW_DIGEST_PODCAST_META_VIDEO_URL,
		SHADOW_DIGEST_PODCAST_META_YOUTUBE_URL,
	);

	foreach ( $keys as $key => $type ) {
		register_post_meta(
			'post',
			$key,
			array(
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => static function ( $value ) use ( $key, $type, $url_keys ) {
					if ( 'integer' === $type ) {
						return (int) $value;
					}
					if ( in_array( $key, $url_keys, true ) ) {
						return esc_url_raw( (string) $value );
					}
					return sanitize_text_field( (string) $value );
				},
			)
		);
	}
}
add_action( 'init', 'shadow_digest_register_podcast_meta' );

/**
 * Register the /feed/podcast/ endpoint.
 *
 * @since 1.4.0
 * @return void
 */
function shadow_digest_register_podcast_feed(): void {
	if ( ! shadow_digest_get( 'shadow_digest_podcast_enable' ) ) {
		return;
	}
	add_feed( 'podcast', 'shadow_digest_render_podcast_feed' );
}
add_action( 'init', 'shadow_digest_register_podcast_feed' );

/**
 * Advertise the podcast feed in <head>.
 *
 * @since 1.4.0
 * @return void
 */
function shadow_digest_podcast_feed_link(): void {
	if ( ! shadow_digest_get( 'shadow_digest_podcast_enable' ) ) {
		return;
	}
	$title = (string) shadow_digest_get( 'shadow_digest_podcast_title' );
	if ( '' === $title ) {
		$title = get_bloginfo( 'name' );
	}
	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s" />' . "\n",
		esc_attr( $title ),
		esc_url( home_url( '/feed/podcast/' ) )
	);
}
add_action( 'wp_head', 'shadow_digest_podcast_feed_link', 3 );

/**
 * Query posts that have a podcast audio URL.
 *
 * @since 1.4.0
 * @param int $limit Max episodes.
 * @return WP_Post[]
 */
function shadow_digest_podcast_episodes( int $limit = 100 ): array {
	$q = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => SHADOW_DIGEST_PODCAST_META_URL,
					'compare' => '!=',
					'value'   => '',
				),
			),
		)
	);

	return $q->posts;
}

/**
 * Resolve show artwork URL (Customizer → custom logo → site icon).
 *
 * @since 1.4.0
 * @return string
 */
function shadow_digest_podcast_artwork_url(): string {
	$custom = (string) shadow_digest_get( 'shadow_digest_podcast_image' );
	if ( $custom ) {
		return $custom;
	}
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	$icon = get_site_icon_url( 1400 );
	return is_string( $icon ) ? $icon : '';
}

/**
 * Format seconds as H:MM:SS / M:SS for itunes:duration.
 *
 * @since 1.4.0
 * @param int $seconds Duration in seconds.
 * @return string
 */
function shadow_digest_podcast_format_duration( int $seconds ): string {
	$seconds = max( 0, $seconds );
	$h       = intdiv( $seconds, 3600 );
	$m       = intdiv( $seconds % 3600, 60 );
	$s       = $seconds % 60;
	if ( $h > 0 ) {
		return sprintf( '%d:%02d:%02d', $h, $m, $s );
	}
	return sprintf( '%d:%02d', $m, $s );
}

/**
 * Escape text for RSS/XML text nodes.
 *
 * @since 1.4.0
 * @param string $text Raw text.
 * @return string
 */
function shadow_digest_podcast_xml_text( string $text ): string {
	return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Render the podcast RSS 2.0 + iTunes feed.
 *
 * @since 1.4.0
 * @return void
 */
function shadow_digest_render_podcast_feed(): void {
	header( 'Content-Type: application/rss+xml; charset=UTF-8', true );

	$title       = (string) shadow_digest_get( 'shadow_digest_podcast_title' );
	$summary     = (string) shadow_digest_get( 'shadow_digest_podcast_summary' );
	$author      = (string) shadow_digest_get( 'shadow_digest_podcast_author' );
	$owner_name  = (string) shadow_digest_get( 'shadow_digest_podcast_owner_name' );
	$owner_email = (string) shadow_digest_get( 'shadow_digest_podcast_owner_email' );
	$category    = (string) shadow_digest_get( 'shadow_digest_podcast_category' );
	$explicit    = shadow_digest_get( 'shadow_digest_podcast_explicit' ) ? 'true' : 'false';
	$artwork     = shadow_digest_podcast_artwork_url();
	$site_url    = home_url( '/' );
	$feed_url    = home_url( '/feed/podcast/' );
	$blog_name   = get_bloginfo( 'name' );

	if ( '' === $title ) {
		$title = $blog_name;
	}
	if ( '' === $author ) {
		$author = $blog_name;
	}
	if ( '' === $owner_name ) {
		$owner_name = $author;
	}
	if ( '' === $summary ) {
		$summary = (string) get_bloginfo( 'description' );
	}
	if ( '' === $category ) {
		$category = 'News';
	}

	$episodes = shadow_digest_podcast_episodes( 200 );
	$build    = gmdate( 'D, d M Y H:i:s +0000' );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	?>
<rss version="2.0"
	xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo shadow_digest_podcast_xml_text( $title ); ?></title>
	<link><?php echo esc_url( $site_url ); ?></link>
	<atom:link href="<?php echo esc_url( $feed_url ); ?>" rel="self" type="application/rss+xml" />
	<description><?php echo shadow_digest_podcast_xml_text( $summary ); ?></description>
	<language><?php echo shadow_digest_podcast_xml_text( str_replace( '_', '-', get_bloginfo( 'language' ) ) ); ?></language>
	<lastBuildDate><?php echo shadow_digest_podcast_xml_text( $build ); ?></lastBuildDate>
	<itunes:author><?php echo shadow_digest_podcast_xml_text( $author ); ?></itunes:author>
	<itunes:summary><?php echo shadow_digest_podcast_xml_text( $summary ); ?></itunes:summary>
	<itunes:explicit><?php echo shadow_digest_podcast_xml_text( $explicit ); ?></itunes:explicit>
	<itunes:owner>
		<itunes:name><?php echo shadow_digest_podcast_xml_text( $owner_name ); ?></itunes:name>
<?php if ( $owner_email ) : ?>
		<itunes:email><?php echo shadow_digest_podcast_xml_text( $owner_email ); ?></itunes:email>
<?php endif; ?>
	</itunes:owner>
<?php if ( $artwork ) : ?>
	<itunes:image href="<?php echo esc_url( $artwork ); ?>" />
	<image>
		<url><?php echo esc_url( $artwork ); ?></url>
		<title><?php echo shadow_digest_podcast_xml_text( $title ); ?></title>
		<link><?php echo esc_url( $site_url ); ?></link>
	</image>
<?php endif; ?>
	<itunes:category text="<?php echo shadow_digest_podcast_xml_text( $category ); ?>" />
<?php
	foreach ( $episodes as $post ) {
		$audio = (string) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_URL, true );
		if ( '' === $audio ) {
			continue;
		}
		$bytes = (int) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_BYTES, true );
		if ( $bytes <= 0 ) {
			$media_id = (int) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_MEDIA_ID, true );
			if ( $media_id ) {
				$path = get_attached_file( $media_id );
				if ( is_string( $path ) && is_readable( $path ) ) {
					$bytes = (int) filesize( $path );
				}
			}
		}
		// Apple requires length; fall back to 1 rather than omit the attribute.
		if ( $bytes <= 0 ) {
			$bytes = 1;
		}

		$duration_raw = (string) get_post_meta( $post->ID, SHADOW_DIGEST_PODCAST_META_DURATION, true );
		$duration     = '';
		if ( '' !== $duration_raw ) {
			$duration = ctype_digit( $duration_raw )
				? shadow_digest_podcast_format_duration( (int) $duration_raw )
				: $duration_raw;
		}

		$item_title = get_the_title( $post );
		$item_link  = get_permalink( $post );
		$guid       = $item_link ? $item_link : (string) $post->ID;
		$pub        = get_post_time( 'D, d M Y H:i:s O', true, $post );
		$excerpt    = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 55 );

		?>
	<item>
		<title><?php echo shadow_digest_podcast_xml_text( $item_title ); ?></title>
		<link><?php echo esc_url( (string) $item_link ); ?></link>
		<guid isPermaLink="<?php echo $item_link ? 'true' : 'false'; ?>"><?php echo shadow_digest_podcast_xml_text( $guid ); ?></guid>
		<pubDate><?php echo shadow_digest_podcast_xml_text( (string) $pub ); ?></pubDate>
		<description><?php echo shadow_digest_podcast_xml_text( wp_strip_all_tags( $excerpt ) ); ?></description>
		<enclosure url="<?php echo esc_url( $audio ); ?>" length="<?php echo (int) $bytes; ?>" type="audio/mpeg" />
		<itunes:author><?php echo shadow_digest_podcast_xml_text( $author ); ?></itunes:author>
		<itunes:summary><?php echo shadow_digest_podcast_xml_text( wp_strip_all_tags( $excerpt ) ); ?></itunes:summary>
		<itunes:explicit><?php echo shadow_digest_podcast_xml_text( $explicit ); ?></itunes:explicit>
<?php if ( $duration ) : ?>
		<itunes:duration><?php echo shadow_digest_podcast_xml_text( $duration ); ?></itunes:duration>
<?php endif; ?>
<?php if ( $artwork ) : ?>
		<itunes:image href="<?php echo esc_url( $artwork ); ?>" />
<?php endif; ?>
	</item>
<?php
	}
	?>
</channel>
</rss>
	<?php
	exit;
}

/**
 * Prepend a native audio (and optional video) player on single posts that have
 * podcast media meta.
 *
 * @since 1.4.0
 * @param string $content Post content HTML.
 * @return string
 */
function shadow_digest_podcast_player_content( string $content ): string {
	if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! shadow_digest_get( 'shadow_digest_podcast_enable' ) ) {
		return $content;
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return $content;
	}

	$audio = (string) get_post_meta( $post_id, SHADOW_DIGEST_PODCAST_META_URL, true );
	if ( '' !== $audio && ! wp_http_validate_url( $audio ) ) {
		$audio = '';
	}

	$video = (string) get_post_meta( $post_id, SHADOW_DIGEST_PODCAST_META_VIDEO_URL, true );
	if ( '' !== $video && ! wp_http_validate_url( $video ) ) {
		$video = '';
	}

	// Prefer hosted MP4; optional YouTube is a text link only (no oEmbed — keep
	// the content filter free of network side-effects).
	$youtube = (string) get_post_meta( $post_id, SHADOW_DIGEST_PODCAST_META_YOUTUBE_URL, true );
	if ( '' !== $youtube && ! wp_http_validate_url( $youtube ) ) {
		$youtube = '';
	}

	if ( '' === $audio && '' === $video ) {
		return $content;
	}

	// Idempotent: do not double-inject if content already carries our player.
	if ( str_contains( $content, 'digest-podcast__player' ) || str_contains( $content, 'digest-podcast__video' ) ) {
		return $content;
	}

	$label = (string) shadow_digest_get( 'shadow_digest_podcast_player_label' );
	if ( '' === $label ) {
		$label = __( 'Listen', 'broadside' );
	}
	// When video is present, surface both modalities in the figure label.
	if ( '' !== $video && ! str_contains( strtolower( $label ), 'watch' ) ) {
		/* translators: %s: existing listen label (e.g. "Listen") */
		$label = sprintf( __( '%s · Watch', 'broadside' ), $label );
	}

	$player  = '<figure class="digest-podcast">';
	$player .= '<figcaption class="digest-podcast__label">' . esc_html( $label ) . '</figcaption>';

	if ( '' !== $video ) {
		$player .= '<video class="digest-podcast__video" controls playsinline preload="metadata" src="' . esc_url( $video ) . '">';
		$player .= esc_html__( 'Your browser does not support the video element.', 'broadside' );
		$player .= '</video>';
	}

	if ( '' !== $audio ) {
		$player .= '<audio class="digest-podcast__player" controls preload="metadata" src="' . esc_url( $audio ) . '">';
		$player .= esc_html__( 'Your browser does not support the audio element.', 'broadside' );
		$player .= '</audio>';
	}

	if ( '' !== $youtube ) {
		$player .= '<p class="digest-podcast__youtube"><a class="digest-podcast__youtube-link" href="' . esc_url( $youtube ) . '" target="_blank" rel="noopener noreferrer">';
		$player .= esc_html__( 'Watch on YouTube', 'broadside' );
		$player .= '</a></p>';
	}

	$player .= '</figure>';

	return $player . $content;
}
add_filter( 'the_content', 'shadow_digest_podcast_player_content', 8 );
