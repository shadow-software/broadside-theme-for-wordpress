<?php
/**
 * Broadside — theme bootstrap.
 *
 * Broadside is a block theme. Layout lives in templates/ and parts/, design tokens
 * in theme.json. This file loads the small amount of PHP the theme needs: asset
 * enqueueing, the Customizer settings that let one codebase dress two different
 * mastheads, the editorial blocks, and the block patterns.
 *
 * Everything a publisher would want to change between two Broadside sites — the
 * accent colour, the founding year, the city, the section names, the strapline —
 * is a Customizer setting, never a hard-coded string. That is what makes the
 * theme reusable rather than a one-off.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme version. Kept in lockstep with the "Version:" header in style.css and
 * the "Stable tag:" in readme.txt. Used to bust asset caches on upgrade.
 */
define( 'SHADOW_DIGEST_VERSION', '1.4.1' );

/**
 * Absolute path to the theme directory, with a trailing slash.
 */
define( 'SHADOW_DIGEST_PATH', trailingslashit( get_template_directory() ) );

/**
 * Public URL of the theme directory, with a trailing slash.
 */
define( 'SHADOW_DIGEST_URL', trailingslashit( get_template_directory_uri() ) );

require_once SHADOW_DIGEST_PATH . 'inc/setup.php';
require_once SHADOW_DIGEST_PATH . 'inc/customizer.php';
require_once SHADOW_DIGEST_PATH . 'inc/weather.php';
require_once SHADOW_DIGEST_PATH . 'inc/template-tags.php';
require_once SHADOW_DIGEST_PATH . 'inc/patterns.php';
require_once SHADOW_DIGEST_PATH . 'inc/schema.php';
require_once SHADOW_DIGEST_PATH . 'inc/views.php';
require_once SHADOW_DIGEST_PATH . 'inc/podcast.php';

/*
 * THE THEME REGISTERS NO BLOCKS. THIS IS DELIBERATE. DO NOT ADD ANY.
 *
 * inc/blocks.php and inc/blocks-masthead.php used to be required here. They are
 * not missing — they moved into the Broadside Blocks plugin, and took all 17
 * blocks with them. The WordPress.org scanner refused this theme while they were
 * here, because registering a block is plugin territory and a theme may not do it.
 *
 * The rule's test is the right one, and worth keeping in mind rather than merely
 * complying with: ANYTHING A USER LOSES WHEN THEY SWITCH THEMES BELONGS IN A
 * PLUGIN. A Short Answer, an FAQ and a Sources list are content — they live in
 * post_content. Ship them inside a theme and changing theme silently blanks them.
 *
 * scripts/guard-no-plugin-territory.sh fails the build if a block registration
 * ever reappears in this directory. It tokenizes the PHP, so it can tell a real
 * call from a comment like this one.
 *
 * (This comment deliberately does not spell out the function's name. The .org
 * scanner appears to grep rather than parse — its report cites a line number and
 * a bare string — and a grep cannot tell an explanation from a violation. A theme
 * rejected for a WORD IN A COMMENT would be a very stupid way to lose a week.)
 *
 * The theme still works without the plugin: WordPress omits a block it cannot
 * find, so you get the paper, the type and the grid, without the nameplate and
 * the folio. style.css declares `Requires Plugins: broadside-blocks`, so
 * WordPress 6.5+ offers to install the plugin alongside the theme.
 */
