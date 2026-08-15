<?php
/**
 * Theme supports, asset enqueueing, and editor wiring.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Declare what the theme supports.
 *
 * A block theme gets most of this for free, but the ones declared here are
 * either not implied by theme.json (title tag, HTML5 markup, post thumbnails)
 * or are opt-in behaviours the Theme Directory expects to see declared.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_setup(): void {
	/*
	 * Translations. Broadside ships a .pot in languages/ and honours any .mo a
	 * translator drops in, or a translation installed from translate.wordpress.org.
	 */
	load_theme_textdomain( 'broadside', SHADOW_DIGEST_PATH . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	/*
	 * The masthead is a wordmark, not a logo — but a publisher may still want to
	 * swap the blackletter type for a real logotype. A wide, short crop suits a
	 * newspaper nameplate.
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'               => 120,
			'width'                => 600,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => true,
		)
	);

	/*
	 * The editor loads the same stylesheet the front end does, so the drop cap,
	 * the column rules and the paper texture all render inside the editor canvas.
	 * What an editor sees is what a reader gets.
	 */
	add_editor_style( 'assets/css/digest.css' );
}
add_action( 'after_setup_theme', 'shadow_digest_setup' );

/**
 * Register the two navigation menus the templates use.
 *
 * Block themes normally use the navigation block, which stores its own menu.
 * Broadside registers classic locations as well so a publisher migrating an
 * existing site can point their current menus at the masthead without rebuilding
 * them in the site editor.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_menus(): void {
	register_nav_menus(
		array(
			'sections' => __( 'Section navigation (masthead)', 'broadside' ),
			'utility'  => __( 'Utility bar (subscribe, sign in)', 'broadside' ),
		)
	);
}
add_action( 'init', 'shadow_digest_register_menus' );

/**
 * Enqueue the front-end stylesheet and any conditional scripts.
 *
 * Broadside deliberately ships exactly one stylesheet and no front-end JavaScript
 * framework. Scripts are small, plain, build-step-free, and each is loaded only on
 * the requests that actually need it — see the newsletter form enhancement below.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_enqueue_assets(): void {
	// style.css is not enqueued here. WordPress reads its header comment block
	// (Theme Name, Version, …) directly via get_file_data() to register the
	// theme, and the WPTR requires only that the file exist with a valid header —
	// nothing requires it be loaded as a front-end stylesheet. It carries no CSS
	// rules of its own (see the doc comment in style.css), so enqueueing it was
	// a render-blocking HTTP request that bought nothing.

	// The real stylesheet: everything theme.json cannot express.
	wp_enqueue_style(
		'broadside',
		SHADOW_DIGEST_URL . 'assets/css/digest.css',
		array(),
		SHADOW_DIGEST_VERSION
	);

	// Per-site branding, injected as custom properties. See inc/customizer.php.
	wp_add_inline_style( 'broadside', shadow_digest_custom_properties() );

	/*
	 * The newsletter form's progressive enhancement. Loaded only when
	 * shadow_digest_newsletter() will actually render a <form> — same two settings
	 * it checks, so the asset never ships on a request that has nothing to enhance.
	 */
	if (
		shadow_digest_get( 'shadow_digest_newsletter_enable' )
		&& '' !== (string) shadow_digest_get( 'shadow_digest_newsletter_action' )
	) {
		wp_enqueue_script(
			'broadside-newsletter',
			SHADOW_DIGEST_URL . 'assets/js/newsletter.js',
			array(),
			SHADOW_DIGEST_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'shadow_digest_enqueue_assets' );

/**
 * Push the same custom properties into the block editor.
 *
 * Without this the editor would render the accent colour as the theme's default
 * oxblood even on a site the publisher has set to green — the editor would lie
 * about what the post will look like.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_enqueue_editor_assets(): void {
	wp_register_style( 'digest-editor-vars', false, array(), SHADOW_DIGEST_VERSION );
	wp_enqueue_style( 'digest-editor-vars' );
	wp_add_inline_style( 'digest-editor-vars', shadow_digest_custom_properties() );
}
add_action( 'enqueue_block_assets', 'shadow_digest_enqueue_editor_assets' );

/**
 * Remove WordPress's default block-library duotone and global-styles SVG dump.
 *
 * Broadside defines no duotones, so the filter definitions WordPress prints in the
 * footer are dead weight on every page load.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_trim_head(): void {
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'in_admin_header', 'wp_global_styles_render_svg_filters' );
}
add_action( 'init', 'shadow_digest_trim_head' );

/**
 * Give the body a class describing the current section, so the stylesheet can
 * key off it without inline styles.
 *
 * @since 1.0.0
 * @param string[] $classes Existing body classes.
 * @return string[] Filtered body classes.
 */
function shadow_digest_body_classes( array $classes ): array {
	if ( is_singular() && ! is_front_page() ) {
		$classes[] = 'digest-reading';
	}

	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'digest-no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'shadow_digest_body_classes' );

/**
 * Register the sidebar used by the front page's left rail ("Along the Firing
 * Line" / "Along the Grow Line").
 *
 * The rail is a widget area rather than a hard-coded query so a publisher can
 * put anything there — a query loop of briefs, a promo, a weather box — without
 * editing a template.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_sidebars(): void {
	register_sidebar(
		array(
			'name'          => __( 'Front page rail', 'broadside' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'The narrow left-hand column on the front page, beside the lead story. Traditionally short news briefs.', 'broadside' ),
			'before_widget' => '<div id="%1$s" class="digest-rail__widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="digest-rail__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'shadow_digest_register_sidebars' );

/**
 * Declare the catalogue plate to WooCommerce.
 *
 * OPTIONAL: this is a no-op on a site with no shop. Broadside depends on no plugin
 * and must run on stock WordPress — declaring support for a plugin that is not
 * there costs nothing and changes nothing.
 *
 * WHY THIS IS NOT JUST CSS. WooCommerce's default catalogue thumbnail is a 300px
 * HARD SQUARE CROP (woocommerce_thumbnail_cropping defaults to 1:1). The
 * stylesheet then crops those thumbnails again to the 4:5 plate the grid is ruled
 * for — so a wide product photograph is cropped twice, first to a square by the
 * server and then to a portrait by the browser, and the second crop can only
 * choose from what the first one left. A panoramic shot came out as a picture of
 * the middle of itself with the subject cut off both sides.
 *
 * Cropping twice is not a styling problem and cannot be fixed in the stylesheet.
 * So the theme states the shape it wants ONCE, here, and WooCommerce generates
 * that file. The CSS in §13 then only has to cover the images Woo has NOT
 * regenerated — the ones uploaded before the theme was activated.
 *
 * (After changing these, a shop with existing products needs its thumbnails
 * regenerated: `wp wc product regenerate_images`, or Tools -> Regenerate.)
 *
 * @since 1.0.11
 * @return void
 */
function shadow_digest_woocommerce_setup(): void {
	add_theme_support( 'woocommerce' );

	// The gallery lightbox and zoom are WooCommerce's own, and are what a reader
	// expects from a shop. Declaring them costs one line and nothing else.
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	/*
	 * The catalogue plate: 4:5, the proportion of a printed catalogue engraving.
	 * `cropping => custom` with a 4:5 ratio, NOT the 1:1 default — see above.
	 */
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 480,
			'single_image_width'    => 960,
			'product_grid'          => array(
				'default_rows'    => 3,
				'default_columns' => 3,
			),
		)
	);
}
add_action( 'after_setup_theme', 'shadow_digest_woocommerce_setup' );

/**
 * Crop the catalogue thumbnail to the plate the grid is ruled for.
 *
 * WooCommerce stores the catalogue crop as an option, not as theme support, and
 * offers `woocommerce_get_image_size_<size>` to override it in code. Broadside sets
 * it here so the shape lives in the theme (where the grid that depends on it
 * lives) rather than in a database row a future admin can silently change.
 *
 * @since 1.0.11
 * @param array $size The image size WooCommerce is about to use.
 * @return array The size, cropped to 4:5.
 */
function shadow_digest_woocommerce_thumbnail_size( array $size ): array {
	$size['width']  = 480;
	$size['height'] = 600;
	$size['crop']   = 1;

	return $size;
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'shadow_digest_woocommerce_thumbnail_size' );
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', 'shadow_digest_woocommerce_thumbnail_size' );

/**
 * Keep WooCommerce's Customer Account block out of the masthead.
 *
 * WHY: on activation, WooCommerce auto-injects a `woocommerce/customer-account`
 * block into a block theme's header — at runtime, through its own template
 * registry, and NOT by writing to the database (there is no wp_template_part row
 * to edit; that was checked before writing this). On Broadside it surfaced as a
 * stray, unstyled person-icon floating below the section navigation, outside the
 * masthead's grid and belonging to nothing.
 *
 * Broadside already renders account and cart links, deliberately placed and styled
 * to match, in the utility bar — see shadow_digest_shop_links(). Two account
 * links in one header, one of them uninvited and misplaced, is worse than either
 * alone.
 *
 * This drops the block wherever it renders, which is the honest thing to write:
 * WooCommerce only ever auto-inserts it into the header, and Broadside's own header
 * is the only place it has ever appeared. Should an editor one day want the block
 * on a page deliberately, this is the one line to revisit.
 *
 * Harmless with no WooCommerce installed: the block name simply never appears.
 *
 * @since 1.0.10
 *
 * @param string $block_content The block's rendered HTML.
 * @param array  $block         The parsed block.
 * @return string Empty for WooCommerce's account block; the content untouched otherwise.
 */
function shadow_digest_drop_woo_account_block( string $block_content, array $block ): string {
	if ( 'woocommerce/customer-account' === ( $block['blockName'] ?? '' ) ) {
		return '';
	}

	return $block_content;
}
add_filter( 'render_block', 'shadow_digest_drop_woo_account_block', 10, 2 );

/**
 * Tell WordPress that the front page's lead photograph is the LCP element.
 *
 * WordPress decides on its own which image to fetch eagerly: it walks the page,
 * and the first "content" image it meets gets fetchpriority="high" and is exempt
 * from lazy-loading. Everything after it is lazy and low. On an article page that
 * heuristic lands on the right image, because single.html puts the featured image
 * at the top level of the template.
 *
 * The front page's lead photograph is NOT at the top level. It sits inside a
 * group (.digest-lead__hero) inside a query loop's post-template — and core reads
 * images inside a loop as list thumbnails, the kind that legitimately belong below
 * the fold. So it did the exact opposite of what the page needs:
 *
 *     loading="lazy" fetchpriority="low"
 *
 * on the single largest visible element of the site's most important page. Nothing
 * on the front page was marked high priority at all. The browser would not even
 * begin fetching the lead photo until layout had run, and then queued it behind
 * everything else — a Largest Contentful Paint regression, introduced by the very
 * change (1.0.12) that put the photograph there.
 *
 * This is not fixed by rewriting the <img> markup. Core exposes the decision as a
 * filter, so the theme states the fact it uniquely knows — "on the front page, the
 * first featured image IS the hero" — and lets core apply it.
 *
 * Strictly the FIRST such image: the lead story is the only one treated this way.
 * The briefs rail and the secondary story below it stay lazy, which is correct —
 * they are genuinely further down the page.
 *
 * The filter passes FOUR arguments — ( $loading_attrs, $tag_name, $attr, $context ).
 * Registering it for three silently hands the $attr array to the $context
 * parameter, which is a fatal on every front-page request. Verified against
 * wp-includes/media.php rather than assumed.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $loading_attrs The loading-optimization attributes core chose.
 * @param string               $tag_name      The tag being filtered ('img', 'iframe').
 * @param array<string, mixed> $attr          The element's own attributes.
 * @param string               $context       Where the tag came from, e.g. 'the_post_thumbnail'.
 * @return array<string, mixed> The attributes, with the lead photograph promoted.
 */
function shadow_digest_prioritize_lead_image( array $loading_attrs, string $tag_name, array $attr, string $context ): array {
	static $promoted = false;

	if ( 'img' !== $tag_name || $promoted || is_admin() || ! is_front_page() ) {
		return $loading_attrs;
	}

	// A post's featured image — not the custom logo, not an avatar, not content.
	if ( 'the_post_thumbnail' !== $context ) {
		return $loading_attrs;
	}

	// The FIRST one only: that is the lead. The briefs rail and the secondary
	// story below it stay lazy, which is correct — they really are further down.
	$promoted = true;

	$loading_attrs['fetchpriority'] = 'high';
	unset( $loading_attrs['loading'] );

	return $loading_attrs;
}
add_filter( 'wp_get_loading_optimization_attributes', 'shadow_digest_prioritize_lead_image', 10, 4 );

/**
 * Steer the front page's aside picks (query loop id 12) toward posts readers
 * are actually engaging with, instead of always being the 6th-9th newest.
 *
 * Ranks by shadow_digest_posts_by_views() over the last 30 days, excluding
 * the five posts the rail and lead story already show (offsets 0-4) so the
 * aside never repeats them. Needs at least 3 qualifying posts with recorded
 * views to activate; a new or low-traffic site — where "trending" is not yet
 * a meaningful signal — falls through to the template's own newest-first
 * query untouched.
 *
 * @since 1.3.7
 *
 * @param array<string, mixed> $query Args WP_Query will receive.
 * @param WP_Block             $block The Query block, including its attributes.
 * @return array<string, mixed> The (possibly rewritten) query args.
 */
function shadow_digest_aside_query_vars( array $query, WP_Block $block ): array {
	$query_id = $block->context['queryId'] ?? null;

	if ( 12 !== $query_id ) {
		return $query;
	}

	$already_shown = get_posts(
		array(
			'numberposts'         => 5,
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);

	$trending = array_diff( shadow_digest_posts_by_views( 12, 30 ), $already_shown );
	$trending = array_slice( array_values( $trending ), 0, 3 );

	if ( count( $trending ) < 3 ) {
		return $query;
	}

	$query['post__in']            = $trending;
	$query['orderby']             = 'post__in';
	$query['offset']              = 0;
	$query['post_type']           = 'post';
	$query['post_status']         = 'publish';
	$query['ignore_sticky_posts'] = true;

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'shadow_digest_aside_query_vars', 10, 2 );
