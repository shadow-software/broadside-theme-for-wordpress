<?php
/**
 * The editorial blocks.
 *
 * Broadside ships the furniture a long-read needs and core does not have: a
 * short-answer box, a key-takeaways list, a table of contents, an FAQ, a sources
 * list and a disclosure table.
 *
 * Every one of them is a dynamic block rendered in PHP. That is a deliberate
 * choice with three payoffs:
 *
 *   1. The FAQ can emit its own FAQPage JSON-LD, derived from the questions the
 *      editor actually typed, without the editor knowing what JSON-LD is.
 *   2. The table of contents can build itself from the post's headings at render
 *      time, so it never drifts out of date when someone edits a heading.
 *   3. If the theme is ever deactivated, the saved markup degrades to plain,
 *      semantic HTML rather than a wall of block-validation errors.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Re-entrancy guard for block render callbacks.
 *
 * On 2026-07-13 a bug in this file took down a shared production server for
 * forty minutes, including a live marketplace that had nothing to do with this
 * theme. The mechanism: a render callback rendered the post content to inspect
 * it, which re-rendered every block in that content — including itself — with no
 * base case. The host had no request timeout, so the workers never died; they
 * accumulated until the box could not fork sshd. See
 * docs/INCIDENT-2026-07-13-vps-outage.md.
 *
 * The specific bug is fixed (nothing in this theme renders content any more).
 * This guard exists so that the *class* of bug cannot have that outcome again:
 * if any Broadside block ever re-enters itself, it returns an empty string on the
 * second entry instead of recursing. The page loses a block. The server lives.
 *
 * Wrap every render callback in this. It costs one array lookup.
 *
 * @since 1.0.1
 * @param string   $name     The block name, used as the re-entrancy key.
 * @param callable $render   The real render callback.
 * @return string The rendered HTML, or '' if this block is already rendering.
 */
function shadow_digest_guard( string $name, callable $render ): string {
	static $rendering = array();

	/*
	 * The blocks live in a plugin; the helpers they render with live in the theme
	 * (shadow_digest_get(), shadow_digest_nameplate(), shadow_digest_avatar() and
	 * seven more). Under any OTHER theme those functions do not exist, and every
	 * one of these render callbacks would fatal on an undefined function — taking
	 * the whole site down, not just the block.
	 *
	 * A plugin that fatals when you switch themes is a bad plugin. So the check
	 * sits HERE, at the single chokepoint every block already passes through,
	 * rather than as nineteen function_exists() calls sprinkled across the call
	 * sites, where the twentieth would eventually be forgotten.
	 *
	 * With no Broadside, a block renders nothing. WordPress omits it. The page is
	 * whole. broadside_blocks_admin_notice() explains why in the admin.
	 */
	if ( ! broadside_blocks_theme_active() ) {
		return '';
	}

	if ( isset( $rendering[ $name ] ) ) {
		// We are already inside this block's render. Something is re-entrant.
		// Refuse to go deeper.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// A developer should hear about this loudly; a visitor should not.
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				esc_html( "Broadside: block {$name} re-entered itself; refusing to recurse. This is a bug." ),
				E_USER_WARNING
			);
		}

		return '';
	}

	$rendering[ $name ] = true;

	try {
		return (string) $render();
	} finally {
		// finally, not a plain unset: an exception inside the callback must not
		// leave the block permanently marked as "rendering" for the rest of the
		// request, or every later instance of it would silently vanish.
		unset( $rendering[ $name ] );
	}
}

/**
 * Register every block in blocks/.
 *
 * Each block is a directory with a block.json. WordPress reads the metadata,
 * wires up the editor script, and calls the render callback named below.
 *
 * Every callback is wrapped in shadow_digest_guard(), so no Broadside block can ever
 * recurse into itself — see the note on that function.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_blocks(): void {
	$blocks = array(
		'short-answer'     => 'shadow_digest_render_short_answer',
		'takeaways'        => 'shadow_digest_render_takeaways',
		'toc'              => 'shadow_digest_render_toc',
		'faq'              => 'shadow_digest_render_faq',
		'sources'          => 'shadow_digest_render_sources',
		'disclosure-table' => 'shadow_digest_render_disclosure_table',
		'section-grid'     => 'shadow_digest_render_section_grid',
		'related'          => 'shadow_digest_render_related',
		'lead-body'        => 'shadow_digest_render_lead_body',
	);

	foreach ( $blocks as $name => $callback ) {
		$dir = BROADSIDE_BLOCKS_PATH . 'blocks/' . $name;

		if ( ! file_exists( $dir . '/block.json' ) ) {
			continue;
		}

		register_block_type(
			$dir,
			array(

				/*
				 * Guarded, so no Broadside block can recurse into itself — see
				 * shadow_digest_guard() and docs/INCIDENT-2026-07-13-vps-outage.md.
				 *
				 * WordPress passes ( $attributes, $content, $block ); these
				 * callbacks declare only the first, so only the first is passed
				 * on. Handing a one-argument function three arguments is a fatal.
				 */
				'render_callback' => static function ( $attributes ) use ( $name, $callback ): string {
					return shadow_digest_guard(
						$name,
						static fn(): string => (string) call_user_func( $callback, (array) $attributes )
					);
				},
			)
		);
	}
}
add_action( 'init', 'shadow_digest_register_blocks' );

/**
 * Put the theme's blocks in their own editor category, so an editor can find
 * them without hunting through "Widgets".
 *
 * @since 1.0.0
 * @param array<int, array<string, mixed>> $categories The registered categories.
 * @return array<int, array<string, mixed>> The filtered categories.
 */
function shadow_digest_block_category( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'broadside',
			'title' => __( 'Broadside — editorial', 'broadside-blocks' ),
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'shadow_digest_block_category' );

/*
 * Render callbacks.
 *
 * Each one escapes on output. None of them trusts an attribute — attributes come
 * from post content, which an Author-level user can write, so they are treated
 * as untrusted input.
 */

/**
 * The short-answer box: a direct, quotable answer at the top of the article.
 *
 * This is the block that wins featured snippets and answer-engine citations. It
 * exists because a reader — and a machine — should be able to get the answer in
 * two sentences without reading two thousand words first.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_short_answer( array $attributes ): string {
	$body = isset( $attributes['answer'] ) ? (string) $attributes['answer'] : '';

	if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
		return '';
	}

	$label = isset( $attributes['label'] ) && '' !== $attributes['label']
		? (string) $attributes['label']
		: __( 'The Short Answer', 'broadside-blocks' );

	return sprintf(
		'<div %1$s><p class="wp-block-digest-short-answer__label">%2$s</p><p class="wp-block-digest-short-answer__body">%3$s</p></div>',
		get_block_wrapper_attributes(),
		esc_html( $label ),
		wp_kses_post( $body )
	);
}

/**
 * Key takeaways — the four things a reader should leave with.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_takeaways( array $attributes ): string {
	$items = isset( $attributes['items'] ) && is_array( $attributes['items'] )
		? $attributes['items']
		: array();

	$items = array_values(
		array_filter(
			array_map( 'strval', $items ),
			static fn( string $item ): bool => '' !== trim( wp_strip_all_tags( $item ) )
		)
	);

	if ( empty( $items ) ) {
		return '';
	}

	$label = isset( $attributes['label'] ) && '' !== $attributes['label']
		? (string) $attributes['label']
		: __( 'Key Takeaways', 'broadside-blocks' );

	$list = '';

	foreach ( $items as $item ) {
		$list .= sprintf(
			'<li class="wp-block-digest-takeaways__item"><span>%s</span></li>',
			wp_kses_post( $item )
		);
	}

	return sprintf(
		'<div %1$s><p class="wp-block-digest-takeaways__label">%2$s</p><ul class="wp-block-digest-takeaways__list">%3$s</ul></div>',
		get_block_wrapper_attributes(),
		esc_html( $label ),
		$list // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is passed through wp_kses_post() above.
	);
}

/**
 * The table of contents, built from the post's own headings.
 *
 * The editor adds the block and types nothing: the list is derived from the h2
 * and h3 elements in the rendered post content, and anchors are generated for
 * any heading that lacks one. A heading renamed after publication updates the
 * contents automatically, which a hand-maintained list never does.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_toc( array $attributes ): string {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$headings = shadow_digest_extract_headings( $post->post_content );

	if ( empty( $headings ) ) {
		return '';
	}

	$label = isset( $attributes['label'] ) && '' !== $attributes['label']
		? (string) $attributes['label']
		: __( 'In This Article', 'broadside-blocks' );

	$items = '';

	foreach ( $headings as $heading ) {
		$items .= sprintf(
			'<li class="is-h%1$d"><a href="#%2$s">%3$s</a></li>',
			(int) $heading['level'],
			esc_attr( $heading['anchor'] ),
			esc_html( $heading['text'] )
		);
	}

	return sprintf(
		'<nav %1$s aria-label="%2$s"><p class="wp-block-digest-toc__label">%3$s</p><ol class="wp-block-digest-toc__list">%4$s</ol></nav>',
		get_block_wrapper_attributes(),
		esc_attr__( 'Table of contents', 'broadside-blocks' ),
		esc_html( $label ),
		$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped above.
	);
}

/**
 * Pull the h2/h3 headings out of a post's content, with their anchors.
 *
 * This reads the RAW post content, deliberately. The obvious implementation —
 * render the content with do_blocks() and then scrape the headings out of the
 * result — recurses infinitely: rendering the content renders every block in it,
 * including the table-of-contents block that asked for the headings in the first
 * place, which renders the content again, and so on until the request times out.
 *
 * That is not a hypothetical. It is a 500 on every article page.
 *
 * Reading the raw content is also simply correct: a core/heading block saves its
 * <h2>/<h3> markup verbatim into post_content, so the headings are already there
 * in plain HTML. There is nothing to render.
 *
 * @since 1.0.0
 * @param string $content The raw post content.
 * @return array<int, array{level:int, text:string, anchor:string}> The headings, in document order.
 */
function shadow_digest_extract_headings( string $content ): array {
	if ( '' === trim( $content ) ) {
		return array();
	}

	$headings = array();
	$seen     = array();

	// A tolerant match: heading tags may carry any attributes, in any order.
	if ( ! preg_match_all( '#<h([23])\b([^>]*)>(.*?)</h\1>#is', $content, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $matches as $match ) {
		$level = (int) $match[1];
		$attrs = (string) $match[2];
		$text  = trim( wp_strip_all_tags( (string) $match[3] ) );

		if ( '' === $text ) {
			continue;
		}

		// Prefer the anchor the editor set; fall back to one derived from the text.
		$anchor = '';

		if ( preg_match( '#\bid=["\']([^"\']+)["\']#i', $attrs, $id_match ) ) {
			$anchor = (string) $id_match[1];
		}

		if ( '' === $anchor ) {
			$anchor = sanitize_title( $text );
		}

		// Two headings with the same text would otherwise produce the same anchor.
		$base = $anchor;
		$n    = 2;

		while ( isset( $seen[ $anchor ] ) ) {
			$anchor = $base . '-' . $n;
			++$n;
		}

		$seen[ $anchor ] = true;

		$headings[] = array(
			'level'  => $level,
			'text'   => $text,
			'anchor' => $anchor,
		);
	}

	return $headings;
}

/**
 * Ensure every h2/h3 in the post body carries the anchor the contents links to.
 *
 * The table of contents derives anchors from heading text. If the editor never
 * set an explicit anchor, the heading in the body has no id and the contents
 * link goes nowhere. This filter closes that gap by stamping the derived anchor
 * onto any heading that lacks one — using exactly the same derivation, so the
 * two always agree.
 *
 * @since 1.0.0
 * @param string $content The rendered post content.
 * @return string The content, with anchors guaranteed.
 */
function shadow_digest_add_heading_anchors( string $content ): string {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$seen = array();

	$result = preg_replace_callback(
		'#<h([23])\b([^>]*)>(.*?)</h\1>#is',
		// $matched, not $match: `match` is a reserved keyword as of PHP 8, and
		// WPCS flags it as a parameter name even where it is still legal.
		static function ( array $matched ) use ( &$seen ): string {
			$level = (string) $matched[1];
			$attrs = (string) $matched[2];
			$inner = (string) $matched[3];

			// Already anchored — leave it exactly as the editor wrote it.
			if ( preg_match( '#\bid=["\'][^"\']+["\']#i', $attrs ) ) {
				return $matched[0];
			}

			$text = trim( wp_strip_all_tags( $inner ) );

			if ( '' === $text ) {
				return $matched[0];
			}

			$anchor = sanitize_title( $text );
			$base   = $anchor;
			$n      = 2;

			while ( isset( $seen[ $anchor ] ) ) {
				$anchor = $base . '-' . $n;
				++$n;
			}

			$seen[ $anchor ] = true;

			return sprintf(
				'<h%1$s%2$s id="%3$s">%4$s</h%1$s>',
				$level,
				$attrs,
				esc_attr( $anchor ),
				$inner
			);
		},
		$content
	);

	return is_string( $result ) ? $result : $content;
}
add_filter( 'the_content', 'shadow_digest_add_heading_anchors', 8 );

/**
 * The FAQ, which also emits its own FAQPage structured data.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_faq( array $attributes ): string {
	$pairs = isset( $attributes['items'] ) && is_array( $attributes['items'] )
		? $attributes['items']
		: array();

	$questions = array();

	foreach ( $pairs as $pair ) {
		if ( ! is_array( $pair ) ) {
			continue;
		}

		$q = isset( $pair['question'] ) ? trim( wp_strip_all_tags( (string) $pair['question'] ) ) : '';
		$a = isset( $pair['answer'] ) ? (string) $pair['answer'] : '';

		if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
			continue;
		}

		$questions[] = array(
			'question' => $q,
			'answer'   => $a,
		);
	}

	if ( empty( $questions ) ) {
		return '';
	}

	$title = isset( $attributes['title'] ) && '' !== $attributes['title']
		? (string) $attributes['title']
		: __( 'Frequently Asked Questions', 'broadside-blocks' );

	// Hand the questions to the schema layer, which prints one FAQPage node in
	// the footer however many FAQ blocks the post contains.
	shadow_digest_collect_faq( $questions );

	$items = '';

	foreach ( $questions as $item ) {
		$items .= sprintf(
			'<div class="wp-block-digest-faq__item"><h3 class="wp-block-digest-faq__q">%1$s</h3><p class="wp-block-digest-faq__a">%2$s</p></div>',
			esc_html( $item['question'] ),
			wp_kses_post( $item['answer'] )
		);
	}

	return sprintf(
		'<section %1$s id="faq"><h2 class="wp-block-digest-faq__title">%2$s</h2><div class="wp-block-digest-faq__rule"></div>%3$s</section>',
		get_block_wrapper_attributes(),
		esc_html( $title ),
		$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped above.
	);
}

/**
 * Accumulate FAQ pairs rendered on this request, and print them as a single
 * FAQPage node.
 *
 * A page with two FAQ blocks must still emit exactly one FAQPage — two would be
 * invalid. Collecting them and printing once in the footer is the only way to
 * guarantee that, because a block does not know whether another block follows it.
 *
 * @since 1.0.0
 * @param array<int, array{question:string, answer:string}>|null $add Pairs to add, or null to read.
 * @return array<int, array{question:string, answer:string}> Everything collected so far.
 */
function shadow_digest_collect_faq( ?array $add = null ): array {
	static $collected = array();

	if ( null !== $add ) {
		$collected = array_merge( $collected, $add );
	}

	return $collected;
}

/**
 * Print the FAQPage JSON-LD for whatever FAQ blocks the page rendered.
 *
 * NOTE — this deliberately does NOT defer to an SEO plugin, unlike the article
 * schema in inc/schema.php. The two are different questions:
 *
 *   - The ARTICLE graph (NewsArticle, Organization, BreadcrumbList) is something
 *     every SEO plugin emits from post data. If Rank Math or Yoast is active,
 *     they own it and the theme must stay quiet, or the page carries two
 *     competing NewsArticle nodes and a search engine picks one at random.
 *
 *   - The FAQ graph is derived from THIS THEME'S OWN BLOCK. Rank Math has no idea
 *     the block exists and will never emit FAQPage for it.
 *
 * Deferring here was a bug: with Rank Math active, the theme suppressed its FAQ
 * schema and Rank Math never supplied one, so the FAQ — the single piece of
 * content most likely to win a rich result — emitted no structured data at all.
 * Nobody was in charge.
 *
 * There is no conflict to avoid: an SEO plugin only emits FAQPage from its own
 * FAQ block, which is a different block. If a publisher somehow ends up with
 * both, the shadow_digest_emit_faq_schema filter switches this off.
 *
 * @since 1.0.3
 * @return void
 */
function shadow_digest_print_faq_schema(): void {

	/**
	 * Filters whether the theme emits FAQPage structured data.
	 *
	 * Set to false only if something else on the site already emits FAQPage for
	 * these same questions — which, since the questions come from this theme's
	 * own block, is unlikely.
	 *
	 * @since 1.0.3
	 * @param bool $emit Whether to emit the FAQ schema.
	 */
	if ( ! apply_filters( 'shadow_digest_emit_faq_schema', true ) ) {
		return;
	}

	/*
	 * This runs on wp_footer, NOT through a render callback, so it is the one path
	 * in this plugin that shadow_digest_guard()'s theme check does not cover — and
	 * below it calls shadow_digest_plain_text(), which lives in the theme. Under any
	 * other theme that is an undefined function and a fatal in the page footer.
	 *
	 * Do not "fix" this by defining a copy of the helper in the plugin behind
	 * function_exists(): WordPress loads plugins BEFORE the theme, so the check is
	 * always false, the plugin defines it, the theme redeclares it, and every page
	 * on the site fatals. That was tried; the sandbox returned HTTP 500.
	 */
	if ( ! broadside_blocks_theme_active() ) {
		return;
	}

	$questions = shadow_digest_collect_faq();

	if ( empty( $questions ) ) {
		return;
	}

	$entities = array();

	foreach ( $questions as $item ) {
		// Decoded, not merely stripped. This is a JSON string, not HTML — nothing
		// downstream will ever decode an entity here, so `&amp;` would be indexed
		// by Google as the five literal characters. See shadow_digest_plain_text().
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => shadow_digest_plain_text( $item['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => shadow_digest_plain_text( $item['answer'] ),
			),
		);
	}

	// Default wp_json_encode() escapes solidus as \/ so a user-supplied
	// </script> sequence cannot break out of the script element. Do not pass
	// JSON_UNESCAPED_SLASHES here — that was the review finding.
	$json = wp_json_encode(
		array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		)
	);

	if ( false === $json ) {
		return;
	}

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() with default flags; JSON-LD must remain valid JSON.
}
add_action( 'wp_footer', 'shadow_digest_print_faq_schema' );

/**
 * The sources list — the receipts.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_sources( array $attributes ): string {
	$items = isset( $attributes['items'] ) && is_array( $attributes['items'] )
		? $attributes['items']
		: array();

	$items = array_values(
		array_filter(
			array_map( 'strval', $items ),
			static fn( string $item ): bool => '' !== trim( wp_strip_all_tags( $item ) )
		)
	);

	if ( empty( $items ) ) {
		return '';
	}

	$label = isset( $attributes['label'] ) && '' !== $attributes['label']
		? (string) $attributes['label']
		: __( 'Sources & References', 'broadside-blocks' );

	$list = '';

	foreach ( $items as $item ) {
		$list .= '<li>' . wp_kses_post( $item ) . '</li>';
	}

	return sprintf(
		'<section %1$s><p class="wp-block-digest-sources__label">%2$s</p><ol class="wp-block-digest-sources__list">%3$s</ol></section>',
		get_block_wrapper_attributes(),
		esc_html( $label ),
		$list // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is passed through wp_kses_post() above.
	);
}

/**
 * The lead story's opening paragraphs, set in two justified columns.
 *
 * A broadsheet front page does not print a summary of its lead story; it prints
 * the beginning of it, and the reader turns the page. core/post-excerpt cannot do
 * this — it returns a manual excerpt when one exists, and a stripped-to-plain-text
 * teaser when one does not.
 *
 * ---------------------------------------------------------------------------
 * READ THIS BEFORE EDITING. This function touches post content, which is the
 * exact shape of the bug that took down a production server on 2026-07-13.
 *
 *   DO NOT call do_blocks(), apply_filters( 'the_content', ... ), or anything
 *   else that renders the post. Rendering the post renders every block in it. If
 *   this block ever appears inside post content, that includes THIS BLOCK, and
 *   the request recurses until the worker dies.
 *
 * It reads the RAW post_content and extracts <p> elements with a regex. A
 * core/paragraph block saves its <p> markup verbatim, so the paragraphs are
 * already there as plain HTML. Nothing needs rendering. See
 * docs/INCIDENT-2026-07-13-vps-outage.md, and scripts/deploy.sh, which refuses to
 * deploy a theme containing a live do_blocks() call.
 * ---------------------------------------------------------------------------
 *
 * @since 1.0.1
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_lead_body( array $attributes ): string {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$wanted = isset( $attributes['paragraphs'] ) ? absint( $attributes['paragraphs'] ) : 4;
	$wanted = max( 1, min( 8, $wanted ) );

	// RAW content. Never rendered. See the warning above.
	$raw = (string) $post->post_content;

	/*
	 * Drop the blocks whose <p> elements are not body copy before matching.
	 *
	 * A pull-quote's paragraph is still a <p> in the saved markup, and lifting it
	 * into the lead well prints the quotation as though it were the third
	 * paragraph of the story — with no attribution and no quotation marks. The
	 * same is true of captions and the theme's own editorial furniture.
	 */
	$raw = (string) preg_replace(
		array(
			'#<blockquote\b.*?</blockquote>#is',
			'#<figure\b.*?</figure>#is',
			'#<figcaption\b.*?</figcaption>#is',
			// Broadside's own dynamic blocks save no inner HTML, but a future one
			// might; skip any comment-delimited digest block wholesale.
			'#<!--\s*wp:broadside/.*?/-->#is',
		),
		'',
		$raw
	);

	if ( ! preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $raw, $matches, PREG_SET_ORDER ) ) {
		// No paragraphs to lead with — fall back to the excerpt rather than
		// printing an empty column.
		$excerpt = get_the_excerpt( $post );

		if ( '' === trim( $excerpt ) ) {
			return '';
		}

		$matches = array( array( '', $excerpt ) );
	}

	$paragraphs = array();

	foreach ( $matches as $match ) {
		$text = trim( wp_strip_all_tags( (string) $match[1] ) );

		if ( '' === $text ) {
			continue;
		}

		$paragraphs[] = $text;

		if ( count( $paragraphs ) >= $wanted ) {
			break;
		}
	}

	if ( empty( $paragraphs ) ) {
		return '';
	}

	$html = '';

	foreach ( $paragraphs as $i => $text ) {
		// The drop cap falls on the first paragraph only, and only if the
		// publisher has left it switched on.
		$class = ( 0 === $i && shadow_digest_get( 'shadow_digest_dropcap' ) ) ? ' class="digest-dropcap"' : '';

		$html .= sprintf( '<p%1$s>%2$s</p>', $class, esc_html( $text ) );
	}

	$more = isset( $attributes['moreText'] ) && '' !== $attributes['moreText']
		? (string) $attributes['moreText']
		: __( 'Continue the full report →', 'broadside-blocks' );

	$html .= sprintf(
		'<a class="digest-lead__more" href="%1$s">%2$s</a>',
		esc_url( (string) get_permalink( $post ) ),
		esc_html( $more )
	);

	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes( array( 'class' => 'digest-columns' ) ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
}

/**
 * "Continue Reading" — three more stories, from the same section where possible.
 *
 * This is a plain get_posts() rather than a core query block on purpose. A
 * core/query inside a singular template inherits the main query unless told not
 * to, and a query that has not been told to exclude the current post will
 * cheerfully recommend the reader the article they are already reading. Owning
 * the query means owning the exclusion.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_related( array $attributes ): string {
	$current = get_post();

	if ( ! $current instanceof WP_Post ) {
		return '';
	}

	$count = isset( $attributes['count'] ) ? absint( $attributes['count'] ) : 3;
	$count = max( 1, min( 6, $count ) );

	$args = array(
		'numberposts'         => $count,
		'post_status'         => 'publish',
		'post__not_in'        => array( $current->ID ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	// Prefer stories from the same section — a reader who just finished a
	// ballistics piece is more likely to want another one than a legislation
	// round-up.
	$categories = get_the_category( $current->ID );

	if ( ! empty( $categories ) ) {
		$args['category__in'] = array( (int) $categories[0]->term_id );
	}

	$posts = get_posts( $args );

	// If the section is thin, fall back to the most recent stories overall rather
	// than printing a half-empty row.
	if ( count( $posts ) < $count ) {
		unset( $args['category__in'] );
		$posts = get_posts( $args );
	}

	if ( empty( $posts ) ) {
		return '';
	}

	$heading = isset( $attributes['heading'] ) && '' !== $attributes['heading']
		? (string) $attributes['heading']
		: __( 'Continue Reading', 'broadside-blocks' );

	$items = '';

	foreach ( $posts as $post ) {
		$terms  = get_the_category( $post->ID );
		$kicker = ! empty( $terms )
			? sprintf( '<span class="digest-kicker">%s</span>', esc_html( $terms[0]->name ) )
			: '';

		$thumb = '';
		if ( has_post_thumbnail( $post ) ) {
			$thumb = get_the_post_thumbnail(
				$post,
				'medium_large',
				array(
					'class'    => 'digest-snip__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
				)
			);
			if ( is_string( $thumb ) && '' !== $thumb ) {
				$thumb = '<span class="digest-snip__media" aria-hidden="true">' . $thumb . '</span>';
			} else {
				$thumb = '';
			}
		}

		$items .= sprintf(
			'<a class="digest-related__item digest-snip" href="%1$s">%2$s%3$s<span class="digest-related__head">%4$s</span><span class="digest-related__sum">%5$s</span></a>',
			esc_url( (string) get_permalink( $post ) ),
			$thumb, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- From get_the_post_thumbnail().
			$kicker, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html( shadow_digest_plain_text( get_the_title( $post ) ) ),
			esc_html( shadow_digest_plain_text( get_the_excerpt( $post ) ) )
		);
	}

	return sprintf(
		'<section %1$s><h2 class="digest-section-head">%2$s</h2><div class="digest-related__grid">%3$s</div></section>',
		get_block_wrapper_attributes( array( 'class' => 'digest-related' ) ),
		esc_html( $heading ),
		$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
}

/**
 * The section grid — "Inside This Week's Edition".
 *
 * One column per category, each listing that category's most recent headlines.
 * Categories are ranked by the SUM of `_digest_views` across their published
 * posts (falling back to post count when views are tied), so the grid steers
 * itself toward what readers actually open. Empty sections never appear; a
 * new n8n-filed category shows up the moment it has a post.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_section_grid( array $attributes ): string {
	$columns    = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 6;
	$per_column = isset( $attributes['perColumn'] ) ? absint( $attributes['perColumn'] ) : 3;
	$columns    = max( 1, min( 12, $columns ) );
	$per_column = max( 1, min( 10, $per_column ) );

	// Prefer the theme helper when present (view-ranked); fall back to count.
	if ( function_exists( 'shadow_digest_categories_by_views' ) ) {
		$categories = shadow_digest_categories_by_views( $columns );
	} else {
		$categories = get_categories(
			array(
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => $columns,
				'hide_empty' => true,
			)
		);
	}

	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return '';
	}

	$html = '';

	foreach ( $categories as $category ) {
		$posts = get_posts(
			array(
				'category'            => $category->term_id,
				'numberposts'         => $per_column,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		if ( empty( $posts ) ) {
			continue;
		}

		$links = '';

		foreach ( $posts as $post ) {
			$links .= sprintf(
				'<a class="digest-section__link" href="%1$s">%2$s</a>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( shadow_digest_plain_text( get_the_title( $post ) ) )
			);
		}

		$html .= sprintf(
			'<div class="digest-section"><h3 class="digest-section__name"><a href="%1$s">%2$s</a></h3>%3$s</div>',
			esc_url( (string) get_category_link( $category->term_id ) ),
			esc_html( $category->name ),
			$links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		);
	}

	if ( '' === $html ) {
		return '';
	}

	$heading = isset( $attributes['heading'] ) && '' !== $attributes['heading']
		? (string) $attributes['heading']
		: '';

	$head = '' !== $heading
		? sprintf( '<h2 class="digest-section-head">%s</h2>', esc_html( $heading ) )
		: '';

	return sprintf(
		'<div %1$s>%2$s<div class="digest-sections">%3$s</div></div>',
		get_block_wrapper_attributes(),
		$head, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
}

/**
 * The disclosure table — a comparison table whose outbound links are correctly
 * marked as sponsored.
 *
 * Broadside hard-codes rel="sponsored nofollow noopener" on every partner link and
 * refuses to render the table without a disclosure line. That is not a
 * limitation; it is the point. A publication that takes affiliate money and
 * hides it is not a journal of record, and Google's link-spam policy agrees.
 *
 * @since 1.0.0
 * @param array<string, mixed> $attributes The block attributes.
 * @return string The rendered HTML.
 */
function shadow_digest_render_disclosure_table( array $attributes ): string {
	$rows = isset( $attributes['rows'] ) && is_array( $attributes['rows'] )
		? $attributes['rows']
		: array();

	$clean = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';

		if ( '' === $label ) {
			continue;
		}

		$clean[] = array(
			'label'   => $label,
			'detail'  => isset( $row['detail'] ) ? (string) $row['detail'] : '',
			'partner' => isset( $row['partner'] ) ? trim( wp_strip_all_tags( (string) $row['partner'] ) ) : '',
			'url'     => isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '',
		);
	}

	if ( empty( $clean ) ) {
		return '';
	}

	$headings = array(
		isset( $attributes['columnOne'] ) && '' !== $attributes['columnOne']
			? (string) $attributes['columnOne']
			: __( 'What to check', 'broadside-blocks' ),
		isset( $attributes['columnTwo'] ) && '' !== $attributes['columnTwo']
			? (string) $attributes['columnTwo']
			: __( 'Why it matters', 'broadside-blocks' ),
		isset( $attributes['columnThree'] ) && '' !== $attributes['columnThree']
			? (string) $attributes['columnThree']
			: __( 'Partner', 'broadside-blocks' ),
	);

	$body = '';

	foreach ( $clean as $row ) {
		$partner = '';

		if ( '' !== $row['partner'] ) {
			$partner = '' !== $row['url']
				? sprintf(
					'<a class="wp-block-digest-disclosure-table__partner" href="%1$s" rel="sponsored nofollow noopener" target="_blank">%2$s <span aria-hidden="true">&rarr;</span><span class="screen-reader-text">%3$s</span></a>',
					esc_url( $row['url'] ),
					esc_html( $row['partner'] ),
					esc_html__( '(opens in a new tab, sponsored link)', 'broadside-blocks' )
				)
				: sprintf(
					'<span class="wp-block-digest-disclosure-table__partner">%s</span>',
					esc_html( $row['partner'] )
				);
		}

		$body .= sprintf(
			'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( $row['label'] ),
			wp_kses_post( $row['detail'] ),
			$partner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		);
	}

	// The disclosure is not optional. If the editor supplied none, Broadside prints
	// its own rather than render an undisclosed affiliate table.
	$note = isset( $attributes['disclosure'] ) && '' !== trim( wp_strip_all_tags( (string) $attributes['disclosure'] ) )
		? (string) $attributes['disclosure']
		: sprintf(
			/* translators: %s: the site name. */
			__( 'Affiliate disclosure: %s may earn a commission on purchases made through partner links. Commissions never influence which products we recommend; testing and editorial are handled independently of commercial partnerships.', 'broadside-blocks' ),
			get_bloginfo( 'name', 'display' )
		);

	return sprintf(
		'<div %1$s><table class="wp-block-digest-disclosure-table__table"><thead><tr><th scope="col">%2$s</th><th scope="col">%3$s</th><th scope="col">%4$s</th></tr></thead><tbody>%5$s</tbody></table><p class="wp-block-digest-disclosure-table__note">%6$s</p></div>',
		get_block_wrapper_attributes(),
		esc_html( $headings[0] ),
		esc_html( $headings[1] ),
		esc_html( $headings[2] ),
		$body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		wp_kses_post( $note )
	);
}
