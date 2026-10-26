<?php
/**
 * Dependency manifest for assets/js/blocks.js.
 *
 * WordPress reads this alongside the script named in each block.json and uses it
 * to enqueue the editor packages the script expects on `window.wp`. It is
 * normally emitted by @wordpress/scripts; Broadside has no build step, so it is
 * maintained by hand — which is only tenable because the dependency list is
 * short and stable.
 *
 * The version is the theme version, so an upgrade busts the browser cache.
 *
 * @package Broadside
 * @since   1.0.0
 */

return array(
	'dependencies' => array(
		'wp-api-fetch',
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-server-side-render',
	),
	'version'      => '1.4.4',
);
