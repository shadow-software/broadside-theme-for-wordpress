<?php
/**
 * Plugin Name:       Broadside Blocks
 * Plugin URI:        https://github.com/shadow-software/broadside-blocks-for-wordpress
 * Description:       The editorial blocks and masthead furniture for the Broadside theme — a short-answer box, key takeaways, a self-building table of contents, an FAQ that emits FAQPage schema, a sources list, a disclosure table, and the nameplate, folio rule and bylines a broadsheet needs.
 * Version:           1.3.3
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Shadow Software LLC
 * Author URI:        https://shadowsoftware.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       broadside-blocks
 *
 * @package Broadside_Blocks
 */

/**
 * WHY THIS PLUGIN EXISTS
 *
 * These 17 blocks used to live in the Broadside theme. The WordPress.org theme
 * scanner rejected that, and it was right to:
 *
 *     REQUIRED: The theme uses the register_block_type() function in the file
 *     inc/blocks.php. register_block_type() is plugin-territory functionality
 *     and must not be used in themes. Use a plugin instead.
 *
 * The Theme Directory's requirements list "Custom blocks" as plugin territory
 * outright, and the test behind that rule is a good one:
 *
 *     Themes handle presentation. Plugins handle content.
 *     ANYTHING A USER LOSES WHEN THEY SWITCH THEMES IS PLUGIN TERRITORY.
 *
 * Six of these blocks fail that test on the merits, not on a technicality. A
 * Short Answer, an FAQ, a Sources list — those are CONTENT. They live in
 * post_content. If they shipped with the theme, then switching themes would
 * silently blank a reader's FAQ, and the author would have no way to get it back.
 * Putting them in a plugin is not compliance theatre; it is the correct place for
 * them, and the rule exists to protect the user from exactly that.
 *
 * The other eleven — the nameplate, the folio rule, the byline — are pure
 * presentation, and there is a real argument they belong in the theme. The rule
 * does not make that exception, and a rule enforced by a scanner does not
 * negotiate. They come too, so that the whole newspaper lives in one place rather
 * than being split across an invisible seam.
 *
 * WHAT THIS MEANS FOR THE THEME
 *
 * Broadside's own templates reference these blocks — parts/masthead.html asks for
 * broadside/nameplate, templates/single.html asks for broadside/byline. Without
 * this plugin the theme still renders: WordPress simply omits a block it cannot
 * find. You get the paper, the type and the grid, but no nameplate and no folio.
 * The theme declares `Requires Plugins: broadside-blocks` in style.css, so
 * WordPress 6.5+ offers to install this alongside it.
 *
 * THE DEPENDENCY RUNS ONE WAY
 *
 * This plugin calls ten helpers that live in the THEME (shadow_digest_get(),
 * shadow_digest_nameplate(), shadow_digest_avatar(), …). Nothing in the theme
 * calls into this plugin. That direction is deliberate and it is load-bearing:
 * the theme is useful without the plugin, and every call the plugin makes into
 * the theme is guarded by function_exists(), so activating this plugin under a
 * DIFFERENT theme degrades to empty output instead of a fatal error.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's own path. Replaces the theme's SHADOW_DIGEST_PATH, which is where
 * block.json used to be found and no longer is.
 */
define( 'BROADSIDE_BLOCKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BROADSIDE_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'BROADSIDE_BLOCKS_VERSION', '1.3.3' );

/**
 * Is the Broadside theme active?
 *
 * The blocks render the theme's Customizer settings and call the theme's template
 * tags. Under another theme those helpers do not exist, so every render callback
 * would fatal. Rather than fatal, the blocks return nothing.
 *
 * Checked by function, not by get_template(), because a CHILD theme of Broadside
 * is still Broadside and must still work.
 *
 * @since 1.2.0
 *
 * @return bool True when the theme's helpers are loaded.
 */
function broadside_blocks_theme_active(): bool {
	return function_exists( 'shadow_digest_get' );
}

/**
 * Tell the user why their newspaper has no nameplate.
 *
 * A block that renders nothing is indistinguishable from a block that is broken.
 * If this plugin is active under a theme that does not provide the helpers, say
 * so once, in the admin, rather than letting someone debug an empty masthead.
 *
 * @since 1.2.0
 *
 * @return void
 */
function broadside_blocks_admin_notice(): void {
	if ( broadside_blocks_theme_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__(
			'Broadside Blocks needs the Broadside theme. Its blocks render the theme’s masthead, folio and Customizer settings, so under another theme they render nothing at all. Activate Broadside, or deactivate this plugin.',
			'broadside-blocks'
		)
	);
}
add_action( 'admin_notices', 'broadside_blocks_admin_notice' );

/*
 * Translations: WordPress.org auto-loads the text domain for plugins hosted
 * there since 4.6. No load_plugin_textdomain() call — the directory review
 * flags it as unnecessary.
 */

/*
 * A NOTE ON shadow_digest_plain_text(), AND ON A FATAL I NEARLY SHIPPED.
 *
 * That helper lives in the THEME (inc/template-tags.php). Every block that calls
 * it does so from inside a render callback, and every render callback is already
 * behind broadside_blocks_theme_active() — so under another theme they return
 * before they can reach an undefined function. Safe.
 *
 * The exception is shadow_digest_print_faq_schema(), which runs on wp_footer and
 * therefore NOT through the render guard. The obvious fix — have the plugin define
 * its own copy behind `if ( ! function_exists() )` — is wrong, and wrong in a way
 * that is worth writing down, because it looks obviously right:
 *
 *     WordPress loads PLUGINS BEFORE THE THEME.
 *     wp-settings.php: plugins_loaded fires at line 622, the theme's
 *     functions.php is included at line 739.
 *
 * So function_exists() is ALWAYS false when a plugin asks. The plugin defines the
 * function, the theme then defines it again, and PHP fatals on the redeclare —
 * every page, HTTP 500, both live sites. I wrote exactly that, and the sandbox
 * returned 500 on the next request. That is what the sandbox is FOR.
 *
 * The right fix needs no second definition: the one unguarded caller checks for
 * the theme itself, exactly like every other caller does. One function, one file,
 * no duplicate to drift.
 */

require_once BROADSIDE_BLOCKS_PATH . 'inc-blocks.php';
require_once BROADSIDE_BLOCKS_PATH . 'inc-blocks-masthead.php';

