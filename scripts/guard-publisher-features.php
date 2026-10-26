<?php
/**
 * Publisher-facing controls must stay switchable.
 *
 * Futuristika's editor showed Broadside's own blocks as unsupported, duotone
 * was switched off, and the only font choice was the nameplate's two bundled
 * faces. These checks fail the build if that lock comes back. They do not boot
 * WordPress — the sandbox assertions cover the rendered page.
 *
 * Run: php scripts/guard-publisher-features.php
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
$fail = 0;

/**
 * @param bool   $ok   Whether the check passed.
 * @param string $what What was checked.
 */
function feature_check( bool $ok, string $what ): void {
	global $fail;

	if ( $ok ) {
		echo "  ✓ {$what}\n";
		return;
	}

	++$fail;
	fwrite( STDERR, "  ✗ {$what}\n" );
}

echo "── duotone stays available\n";

$theme = json_decode( (string) file_get_contents( $root . '/broadside/theme.json' ), true );
$color = is_array( $theme ) ? ( $theme['settings']['color'] ?? array() ) : array();

feature_check( true === ( $color['customDuotone'] ?? null ), 'theme.json allows custom duotone' );

$slugs = array();

foreach ( $color['duotone'] ?? array() as $preset ) {
	if ( is_array( $preset ) && isset( $preset['slug'] ) ) {
		$slugs[] = (string) $preset['slug'];
	}
}

feature_check( in_array( 'ink-paper', $slugs, true ), 'ink-on-paper duotone preset exists' );
feature_check( in_array( 'accent-paper', $slugs, true ), 'accent-on-paper duotone preset exists' );

$setup = (string) file_get_contents( $root . '/broadside/inc/setup.php' );
$tokens = token_get_all( $setup );
$strips_filters = false;

foreach ( $tokens as $i => $token ) {
	if ( ! is_array( $token ) || T_STRING !== $token[0] || 'remove_action' !== $token[1] ) {
		continue;
	}

	$window = '';

	for ( $j = $i; $j < min( count( $tokens ), $i + 12 ); $j++ ) {
		$window .= is_array( $tokens[ $j ] ) ? $tokens[ $j ][1] : $tokens[ $j ];
	}

	if ( str_contains( $window, 'wp_global_styles_render_svg_filters' ) ) {
		$strips_filters = true;
	}
}

feature_check( ! $strips_filters, 'setup.php does not unhook the duotone SVG filters' );

echo "\n── font aliases reject anything that is not a preset variable\n";

require $root . '/scripts/stub-wp.php';
require $root . '/broadside/inc/customizer.php';

$cases = array(
	array( 'body', 'var(--wp--preset--font-family--futura)', '--wp--preset--font-family--body:var(--wp--preset--font-family--futura);' ),
	array( 'display', 'var(--wp--preset--font-family--display)', '' ),
	array( 'body', 'url(https://evil.example/x)', '' ),
	array( 'body', 'expression(alert(1))', '' ),
	array( 'body', '"Futura", sans-serif; color:red', '' ),
	array( 'body', 'var(--wp--preset--font-family--bad name)', '' ),
	array( 'Body', 'var(--wp--preset--font-family--futura)', '' ),
);

foreach ( $cases as $case ) {
	$got = shadow_digest_font_preset_alias( $case[0], $case[1] );
	feature_check(
		$got === $case[2],
		"alias({$case[0]}, {$case[1]}) → " . ( '' === $case[2] ? 'rejected' : 'aliased' )
	);
}

feature_check( 'masthead' === shadow_digest_sanitize_wordmark_font( 'masthead' ), 'nameplate accepts masthead' );
feature_check( 'display' === shadow_digest_sanitize_wordmark_font( 'display' ), 'nameplate accepts display' );
feature_check( 'body' === shadow_digest_sanitize_wordmark_font( 'body' ), 'nameplate accepts body' );
feature_check( 'masthead' === shadow_digest_sanitize_wordmark_font( '</style>' ), 'nameplate rejects markup' );
feature_check( 'masthead' === shadow_digest_sanitize_wordmark_font( 'not-a-family' ), 'nameplate rejects an unknown slug' );

$choices = shadow_digest_font_family_choices();
feature_check( isset( $choices['masthead'], $choices['display'], $choices['body'] ), 'nameplate lists the three bundled roles' );

echo "\n── a language template is a front-end concern\n";

feature_check(
	str_contains( $setup, 'function shadow_digest_polylang_template' ),
	'Polylang template swap exists'
);
feature_check(
	str_contains( $setup, 'is_admin()' ) && str_contains( $setup, 'REST_REQUEST' ),
	'the swap does not run in the admin or the REST editor'
);
feature_check(
	str_contains( $setup, "--' . \$lang" ) || str_contains( $setup, ". '--' ." ),
	'front-page--tr (and the hyphen form) are the language slugs'
);

$views = (string) file_get_contents( $root . '/broadside/inc/views.php' );
feature_check(
	str_contains( $views, 'function shadow_digest_language_query_args' )
	&& str_contains( $views, "['suppress_filters'] = false" ),
	'view rankings do not suppress Polylang'
);

echo "\n── the vendored plugin matches the editor contract\n";

$plugin = $root . '/broadside-blocks';
$js     = (string) file_get_contents( $plugin . '/assets/js/blocks.js' );
$grid   = json_decode( (string) file_get_contents( $plugin . '/blocks/section-grid/block.json' ), true );

preg_match_all( "/(?:register|phpBlock)\\(\\s*'([^']+)'/", $js, $js_names );
$json_names = array();

foreach ( glob( $plugin . '/blocks/*/block.json' ) ?: array() as $file ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	feature_check( ! isset( $data['editorScript'] ), ( $data['name'] ?? $file ) . ' has no per-block editorScript' );

	if ( is_array( $data ) && isset( $data['name'] ) ) {
		$json_names[] = (string) $data['name'];
	}
}

sort( $json_names );
$registered = $js_names[1];
sort( $registered );

feature_check( $json_names === $registered, 'vendored blocks.js registers every vendored block' );
feature_check( is_file( $plugin . '/blocks/lead-promo/block.json' ), 'vendored plugin ships lead-promo' );
feature_check(
	is_array( $grid ) && ( $grid['attributes']['categories']['type'] ?? '' ) === 'array',
	'vendored section grid has a category list'
);
feature_check(
	str_contains( (string) file_get_contents( $plugin . '/broadside-blocks.php' ), 'broadside_blocks_enqueue_editor_script' ),
	'vendored plugin enqueues the editor script once'
);
$masthead = (string) file_get_contents( $plugin . '/inc-blocks-masthead.php' );
feature_check( str_contains( $masthead, 'digest-footer__podcast-link' ), 'vendored colophon prints the podcast directory link' );
feature_check( str_contains( $masthead, 'digest-footer__youtube-link' ), 'vendored colophon prints the YouTube link' );

echo "\n";

if ( 0 === $fail ) {
	echo "✓ publisher controls hold.\n";
	exit( 0 );
}

fwrite( STDERR, "✗ {$fail} publisher-control checks failed.\n" );
exit( 1 );
