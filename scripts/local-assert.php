<?php
/**
 * Correctness assertions for the local sandbox.
 *
 * WHY THIS EXISTS, AND WHY IT IS SEPARATE FROM local-smoke.sh:
 *
 * While validating the fix for the 2026-07-13 outage I deliberately reintroduced
 * the recursion bug to prove the re-entrancy guard worked. It did — the page
 * rendered in 116ms instead of hanging forever. But then the smoke test, which
 * only checks status codes and response times, reported "ALL TEMPLATES PASS —
 * safe to consider deploying" **while the bug was still in the file**.
 *
 * That is the whole lesson of the incident restated in miniature. A test that
 * only asks "did the page load?" will happily bless broken code. The guard turns
 * a server-killing hang into a quietly wrong page — which is a good trade, but it
 * means liveness checks alone can no longer detect the bug.
 *
 * So this file asserts what the page must actually CONTAIN. It is the test that
 * would have caught the original bug on its own, without a guard, without a
 * server, and without an outage.
 *
 * Run: php scripts/local-assert.php
 */

declare( strict_types = 1 );

$base = 'http://localhost:8080';
$fail = 0;
$pass = 0;

/**
 * Assert a condition, print the result.
 *
 * @param bool   $cond Whether the assertion holds.
 * @param string $what What was being asserted.
 * @param string $note Extra detail printed on failure.
 * @return bool
 */
function check( bool $cond, string $what, string $note = '' ): bool {
	global $fail, $pass;

	if ( $cond ) {
		++$pass;
		echo "  ✓ {$what}\n";

		return true;
	}

	++$fail;
	echo "  ✗ {$what}";
	echo '' !== $note ? "  — {$note}\n" : "\n";

	return false;
}

/**
 * Fetch a URL, timing it.
 *
 * @param string $url The URL.
 * @return array{body:string, ms:int, code:int}
 */
function get( string $url ): array {
	$start = microtime( true );

	$ctx = stream_context_create(
		array( 'http' => array( 'timeout' => 25, 'ignore_errors' => true ) )
	);

	$body = (string) @file_get_contents( $url, false, $ctx );
	$ms   = (int) round( ( microtime( true ) - $start ) * 1000 );

	$code = 0;

	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $h, $m ) ) {
			$code = (int) $m[1];
		}
	}

	return array( 'body' => $body, 'ms' => $ms, 'code' => $code );
}

echo "\n── The canary article (the page that took down the VPS)\n";

$r    = get( $base . '/the-thousand-yard-question/' );
$html = $r['body'];

check( 200 === $r['code'], "returns HTTP 200", "got {$r['code']}" );
check( $r['ms'] < 3000, "renders in under 3s", "took {$r['ms']}ms — a hang is the signature of the recursion" );
check( ! preg_match( '/fatal error|allowed memory size|maximum execution/i', $html ), 'no PHP fatal in the output' );

echo "\n── The table of contents — the block that recursed\n";

/*
 * THE ASSERTION THAT ACTUALLY CATCHES THE BUG.
 *
 * The canary post has exactly three headings in its RAW content:
 *   h2  What the Wind Actually Does
 *   h2  Why the Rifle Is Rarely the Problem
 *   h3  A Word on Coriolis
 *
 * The FAQ block ALSO renders <h3> elements — but only after do_blocks() expands
 * it. Those headings are NOT in the raw post content.
 *
 * So the count is a precise tripwire:
 *   3 items  → the TOC read the raw content. Correct.
 *   6 items  → the TOC rendered the content to read it. THE BUG IS BACK.
 *              (It would hang, except the re-entrancy guard stops it — which is
 *              exactly why a timing check alone is not enough to detect this.)
 */
preg_match_all( '#<li class="is-h([23])"><a href="\#([^"]+)">([^<]*)</a></li>#', $html, $toc, PREG_SET_ORDER );

$count = count( $toc );

check(
	3 === $count,
	"lists exactly 3 headings (the ones in the raw content)",
	"found {$count}. If this is 6, the TOC is rendering the post to read it — the outage bug is back."
);

$titles = array_map( static fn( array $m ): string => $m[3], $toc );

check(
	in_array( 'What the Wind Actually Does', $titles, true ),
	'includes the first h2'
);

check(
	in_array( 'A Word on Coriolis', $titles, true ),
	'includes the nested h3'
);

check(
	! in_array( 'What is mirage?', $titles, true ),
	'does NOT include FAQ questions',
	'FAQ headings only exist after rendering — their presence proves the TOC rendered the content'
);

echo "\n── Anchors: does every TOC link actually land somewhere?\n";

foreach ( $toc as $item ) {
	$anchor = $item[2];
	check(
		str_contains( $html, 'id="' . $anchor . '"' ),
		"anchor #{$anchor} exists in the body"
	);
}

echo "\n── Every editorial block renders\n";

$blocks = array(
	'wp-block-digest-short-answer'     => 'Short Answer',
	'wp-block-digest-takeaways__item'  => 'Key Takeaways',
	'wp-block-digest-toc__list'        => 'Table of Contents',
	'wp-block-digest-faq__item'        => 'FAQ',
	'wp-block-digest-sources__list'    => 'Sources',
	'wp-block-digest-disclosure-table' => 'Disclosure Table',
	'digest-byline-block'              => 'Byline',
	'digest-author-bio'                => 'Author Bio',
	'digest-related'                   => 'Related',
);

foreach ( $blocks as $class => $label ) {
	check( str_contains( $html, $class ), "{$label} rendered" );
}

echo "\n── The disclosure table must never leak an unmarked affiliate link\n";

if ( preg_match( '#<a[^>]*wp-block-digest-disclosure-table__partner[^>]*>#', $html, $m ) ) {
	check(
		str_contains( $m[0], 'rel="sponsored nofollow noopener"' ),
		'partner links carry rel="sponsored nofollow noopener"',
		$m[0]
	);
} else {
	check( true, 'no partner links to check' );
}

check(
	str_contains( $html, 'wp-block-digest-disclosure-table__note' ),
	'a disclosure line is always printed'
);

echo "\n── Structured data\n";

/*
 * FAQPage must ALWAYS be emitted by the theme, whether or not an SEO plugin is
 * present.
 *
 * This nearly went wrong on the live sites: the FAQ schema used the same "is an
 * SEO plugin active?" check as the article schema, so with Rank Math installed
 * the theme suppressed its FAQPage — and Rank Math, which has no idea this
 * theme's FAQ block exists, never emitted one either. The FAQ, which is the
 * single piece of content most likely to win a rich result, carried no structured
 * data at all. Nobody was in charge.
 *
 * The article graph SHOULD defer to an SEO plugin (two NewsArticle nodes is worse
 * than one). The FAQ graph should not, because nothing else can produce it.
 */
check(
	str_contains( $html, '"@type":"FAQPage"' ) || str_contains( $html, '"@type": "FAQPage"' ),
	'FAQ emits FAQPage JSON-LD',
	'the FAQ block is the only thing that knows these questions exist — it must always emit'
);

check(
	str_contains( $html, 'NewsArticle' ),
	'article emits NewsArticle JSON-LD'
);

echo "\n── The front page\n";

$fp = get( $base . '/' );

check( 200 === $fp['code'], 'front page returns 200' );
check( str_contains( $fp['body'], 'digest-lead' ), 'the three-column lead grid is present' );
check( str_contains( $fp['body'], 'digest-nameplate' ), 'the nameplate is present' );
check( str_contains( $fp['body'], 'digest-folio' ), 'the folio rule is present' );
check( str_contains( $fp['body'], 'digest-section' ), 'the section grid is present' );

echo "\n── The lead story well — real prose, not a teaser\n";

/*
 * digest/lead-body also reads post content, so it is subject to exactly the same
 * recursion hazard as the table of contents. These assertions pin its behaviour:
 * it must lift real paragraphs out of the RAW content, and it must not lift the
 * pull-quote (whose text is also inside a <p>, and which would otherwise appear
 * in the lead well as an unattributed third paragraph).
 */
if ( preg_match( '#<div[^>]*digest-columns[^>]*>(.*?)</div>#is', $fp['body'], $lead ) ) {
	preg_match_all( '#<p[^>]*>(.*?)</p>#is', $lead[1], $paras );

	$n = count( $paras[0] );

	check( $n >= 3, "the lead well holds real paragraphs (found {$n})" );

	$joined = implode( ' ', $paras[1] );

	check(
		! str_contains( $joined, 'nowhere to hide' ),
		'the pull-quote is NOT lifted into the lead well',
		'a <p> inside a <blockquote> is not body copy'
	);

	check(
		str_contains( $lead[1], 'digest-dropcap' ),
		'the drop cap falls on the first paragraph'
	);

	check(
		str_contains( $lead[1], 'digest-lead__more' ),
		'the jump line links on to the full article'
	);
} else {
	check( false, 'the lead well rendered', 'no .digest-columns found on the front page' );
}

echo "\n── Customizer settings actually reach the page\n";

/*
 * THE ASSERTION THAT CATCHES A SILENTLY-WRONG BRAND.
 *
 * During the slug rename, the setting IDs were rewritten from digest_* to
 * shadow_digest_* along with everything else — but the values already in the
 * database were still stored under the old keys. Every lookup missed, and the
 * theme fell back to its DEFAULTS.
 *
 * The defaults are the Marksman's ones. So Cannabis Digest went live serving
 * "The Weekly Dispatch" and "the week in marksmanship" to its readers, under a
 * green masthead. Nothing errored. Every page returned 200 in under a second.
 * Every block rendered. Every previous assertion passed.
 *
 * The only thing that caught it was a human looking at the rendered page.
 *
 * So: assert that a Customizer value the sandbox SETS actually appears in the
 * HTML. If the settings plumbing ever breaks again, this fails loudly instead of
 * dressing one publication in another's clothes.
 */

// scripts/local-seed.php sets these. If the plumbing works, they must appear.
$expected = array(
	'The Weekly Dispatch'                              => 'the newsletter name from the Customizer',
	'The Journal of Record for the American Marksman'  => 'the strapline from the Customizer',
	'Steady Hands, Straight Talk'                      => 'the motto from the Customizer',
	'New York'                                         => 'the city from the Customizer',
);

foreach ( $expected as $needle => $what ) {
	check(
		str_contains( $fp['body'], $needle ),
		"{$what} reaches the page",
		"'{$needle}' is not in the HTML — the theme is serving its DEFAULTS, not this site's settings"
	);
}

/*
 * Entities are decoded exactly once.
 *
 * A live post on marksmansdigest.com has this as its title, in the database, as
 * literal characters: `CNBC&apos;s &quot;Best States&quot; …`. The auto-publisher
 * writes HTML entities into the title as text.
 *
 * The theme then ran esc_html() over a title get_the_title() had ALREADY escaped,
 * which turned `&apos;` into `&amp;apos;` — and the browser printed the six
 * characters `&apos;` in the middle of the headline. It shipped because every
 * seeded title had either a real apostrophe or no punctuation, so nothing in this
 * file could see it. local-seed-front.php now seeds one that can.
 *
 * The tell is `&amp;` followed by an entity name: that is an entity that has been
 * escaped twice, and it can only ever be a bug.
 */
echo "\n── Titles with HTML entities are not double-escaped\n";

$double = '/&amp;(apos|quot|amp|lt|gt|#\d+);/';

/*
 * Scoped to the text the THEME renders, deliberately.
 *
 * A whole-page grep also catches the featured image's alt="", and that one is not
 * ours to fix: core's post-featured-image block builds alt from the attachment's
 * alt meta and, when there is none, falls back to the RAW post title — entities
 * included — then escapes it for the attribute. The theme never touches that alt
 * (its only 'alt' is the author avatar). Asserting on it here would be asserting
 * on WordPress core, and the assertion would be unfixable from this repo.
 *
 * The real fix for that one is upstream, where the entities are being written into
 * the title in the first place. See the note at the end of this file.
 */
$body_text = preg_replace( '/<img[^>]*>/', '', $fp['body'] );

check(
	1 !== preg_match( $double, (string) $body_text ),
	'no theme-rendered text is double-escaped',
	'found `&amp;apos;` (or similar) outside an <img> — an entity escaped twice, which prints as literal text. See shadow_digest_plain_text().'
);

check(
	! str_contains( (string) $body_text, '&amp;apos;' ) && ! str_contains( (string) $body_text, '&amp;quot;' ),
	'headlines print real punctuation, not entity names',
	'a headline is rendering `&apos;` or `&quot;` as visible text'
);

/*
 * The JSON-LD is the one that actually costs something. A JSON string is not HTML
 * — nothing downstream ever decodes it — so an entity here is indexed by Google
 * verbatim, and the headline in the search result reads `CNBC&apos;s`.
 */
$ld = $r['body'];

check(
	1 !== preg_match( '/"headline":"[^"]*&(amp|apos|quot|#\d+);/', $ld ),
	'the JSON-LD headline carries no HTML entities',
	'schema.org headline contains an entity — Google will index it literally'
);

check(
	1 !== preg_match( '/"name":"[^"]*&(amp|apos|quot|#\d+);/', $ld ),
	'no JSON-LD name carries an HTML entity',
	'a schema.org name (organisation, breadcrumb, FAQ question) contains an entity'
);

echo "\n── Podcast feed + on-article player\n";

$feed = get( $base . '/feed/podcast/' );
check( 200 === $feed['code'], 'podcast feed returns HTTP 200', "got {$feed['code']}" );
check( str_contains( $feed['body'], '<rss' ), 'podcast feed is RSS XML' );
check( str_contains( $feed['body'], 'xmlns:itunes=' ), 'podcast feed declares itunes namespace' );
check( str_contains( $feed['body'], '<enclosure ' ), 'podcast feed has at least one enclosure' );
check( str_contains( $feed['body'], 'type="audio/mpeg"' ), 'enclosure is audio/mpeg' );
check( str_contains( $feed['body'], 'episode-121-10.mp3' ), 'enclosure points at the seeded episode audio' );
check( str_contains( $feed['body'], '<itunes:owner>' ), 'feed includes itunes:owner' );
check( str_contains( $feed['body'], 'inquiries@marksmansdigest.com' ), 'owner email is present (Apple requirement)' );

check( str_contains( $html, 'digest-podcast__player' ), 'canary article renders the audio player' );
check( str_contains( $html, 'episode-121-10.mp3' ), 'player src is the seeded podcast_audio_url' );
check( str_contains( $html, 'rel="alternate" type="application/rss+xml"' ) && str_contains( $html, '/feed/podcast/' ), 'article <head> advertises the podcast feed' );

// Public directory (Podcast Index) — footer whenever theme mod is set (local-seed).
$home = get( $base . '/' );
check( 200 === $home['code'], 'home returns HTTP 200 for podcast directory assert', "got {$home['code']}" );
check( str_contains( $home['body'], 'digest-footer__podcast-link' ), 'footer has podcast directory link' );
check(
	(bool) preg_match(
		'#class="digest-footer__podcast-link"[^>]*href="https://podcastindex\.org/podcast/7981845"[^>]*target="_blank"#',
		$home['body']
	) || (bool) preg_match(
		'#href="https://podcastindex\.org/podcast/7981845"[^>]*class="digest-footer__podcast-link"[^>]*target="_blank"#',
		$home['body']
	) || (
		str_contains( $home['body'], 'podcastindex.org/podcast/7981845' )
		&& str_contains( $home['body'], 'digest-footer__podcast-link' )
		&& str_contains( $home['body'], 'target="_blank"' )
	),
	'footer podcast link is Podcast Index + new tab'
);
check( str_contains( $home['body'], 'noopener' ), 'external podcast link has noopener' );

echo "\n";
echo str_repeat( '─', 70 ) . "\n";

if ( 0 === $fail ) {
	echo "✓ {$pass} assertions passed. The theme is correct, not merely alive.\n\n";
	exit( 0 );
}

echo "✗ {$fail} FAILED, {$pass} passed. DO NOT DEPLOY.\n\n";
exit( 1 );
