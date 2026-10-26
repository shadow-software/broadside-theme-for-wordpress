<?php
/**
 * Just enough WordPress for scripts/guard-publisher-features.php to load
 * inc/customizer.php and call the pure helpers. Not a test double for rendering.
 *
 * @package Broadside
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook     Unused.
	 * @param callable $callback Unused.
	 */
	function add_action( $hook, $callback ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Unused.
	 */
	function __( $text, $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return (string) $text;
	}
}
