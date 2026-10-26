<?php
/**
 * The masthead furniture, as dynamic blocks.
 *
 * The utility bar, the nameplate ears, the folio rule and the newsletter box all
 * print values that live in the Customizer — the founding year as Roman
 * numerals, today's date in the site's locale, the strapline, the newsletter's
 * name. Static block markup cannot do that, so each is a dynamic block whose
 * render callback reads the settings.
 *
 * The upshot for a publisher: they drag "Folio rule" into their header once, and
 * from then on it prints the right volume, the right date and the right motto on
 * every page, in every language, without ever being edited again.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Author masthead title (e.g. “Chief Correspondent”).
 *
 * Prefers the plugin-prefixed usermeta key; falls back to the legacy
 * `shadow_digest_role` key so existing Broadside installs keep their titles.
 *
 * @since 1.3.3
 * @param int $user_id User ID.
 * @return string Role label, or empty string.
 */
function broadside_blocks_author_role( int $user_id ): string {
	$role = (string) get_the_author_meta( 'broadside_blocks_role', $user_id );
	if ( '' !== $role ) {
		return $role;
	}

	return (string) get_the_author_meta( 'shadow_digest_role', $user_id );
}

/**
 * Register the masthead blocks.
 *
 * These have no editor script of their own — they are configured entirely from
 * the Customizer, so the editor renders them via a ServerSideRender preview
 * declared in each block.json's `example`. Nothing to type, nothing to get wrong.
 *
 * @since 1.0.0
 * @return void
 */
function broadside_blocks_register_masthead_blocks(): void {
	$blocks = array(
		'utility-bar' => 'broadside_blocks_render_utility_bar',
		'nameplate'   => 'broadside_blocks_render_nameplate',
		'folio'       => 'broadside_blocks_render_folio',
		'colophon'    => 'broadside_blocks_render_colophon',
		'lead-promo'  => 'broadside_blocks_render_lead_promo',
		'newsletter'  => 'broadside_blocks_render_newsletter_block',
		'byline'      => 'broadside_blocks_render_byline',
		'author-bio'  => 'broadside_blocks_render_author_bio',
		'standards'   => 'broadside_blocks_render_standards',
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
				 * Guarded, like every Broadside block — see broadside_blocks_guard().
				 *
				 * These callbacks take no arguments: they are configured entirely
				 * from the Customizer and the current post, not from block
				 * attributes. So the wrapper accepts WordPress's three arguments
				 * and deliberately passes none of them on.
				 */
				'render_callback' => static function () use ( $name, $callback ): string {
					return broadside_blocks_guard(
						$name,
						static fn(): string => (string) call_user_func( $callback )
					);
				},
			)
		);
	}
}
add_action( 'init', 'broadside_blocks_register_masthead_blocks' );

/**
 * Render the utility bar.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_utility_bar(): string {
	ob_start();
	shadow_digest_utility_bar();
	$html = (string) ob_get_clean();

	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes( array( 'class' => 'digest-utility-wrap' ) ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shadow_digest_utility_bar() escapes everything it prints.
	);
}

/**
 * Render the nameplate and its two ears.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_nameplate(): string {
	if ( function_exists( 'shadow_digest_ear_left_content' ) ) {
		$left       = shadow_digest_ear_left_content();
		$left_title = (string) ( $left['title'] ?? '' );
		$left_body  = (string) ( $left['body'] ?? '' );
	} else {
		$left_title = (string) shadow_digest_get( 'shadow_digest_ear_left_title' );
		$left_body  = (string) shadow_digest_get( 'shadow_digest_ear_left_body' );
	}
	$right_title = (string) shadow_digest_get( 'shadow_digest_ear_right_title' );
	$right_body  = (string) shadow_digest_get( 'shadow_digest_ear_right_body' );

	ob_start();
	?>
	<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'digest-masthead' ) ) ); ?>>

		<div class="digest-ear digest-ear--left">
			<?php if ( '' !== $left_title ) : ?>
				<p class="digest-ear__title"><?php echo esc_html( $left_title ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $left_body ) : ?>
				<p class="digest-ear__body"><?php echo esc_html( $left_body ); ?></p>
			<?php endif; ?>
		</div>

		<?php shadow_digest_nameplate(); ?>

		<div class="digest-ear digest-ear--right">
			<?php if ( '' !== $right_title ) : ?>
				<p class="digest-ear__title"><?php echo esc_html( $right_title ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $right_body ) : ?>
				<p class="digest-ear__body"><?php echo esc_html( $right_body ); ?></p>
			<?php endif; ?>
		</div>

	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render the folio rule.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_folio(): string {
	ob_start();
	shadow_digest_folio();
	$html = (string) ob_get_clean();

	// shadow_digest_folio() prints its own .digest-folio wrapper, so the block wrapper
	// goes around it rather than on it.
	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes( array( 'class' => 'digest-folio-wrap' ) ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shadow_digest_folio() escapes everything it prints.
	);
}

/**
 * Render the footer colophon.
 *
 * The founding year, the domain and the copyright year are all derived rather
 * than typed, which means there is no stale "© 2019" waiting to embarrass the
 * publication on New Year's Day.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_colophon(): string {
	$founded = shadow_digest_founded();
	$year    = (int) wp_date( 'Y' );
	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	ob_start();
	?>
	<footer <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'digest-footer' ) ) ); ?>>

		<p class="digest-footer__wordmark">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?>
			</a>
		</p>

		<span class="digest-footer__meta">
			<?php
			echo esc_html( $host );

			if ( $founded ) {
				printf(
					' · %s',
					sprintf(
						/* translators: %d: the year the publication was founded. */
						esc_html__( 'Founded %d', 'broadside-blocks' ),
						(int) $founded
					)
				);
			}
			?>
		</span>

		<span class="digest-footer__copyright">
			<?php
			printf(
				/* translators: 1: the current year in Roman numerals, 2: the current year in digits. */
				esc_html__( '© %1$s · All Rights Reserved', 'broadside-blocks' ),
				'<abbr title="' . esc_attr( (string) $year ) . '">' . esc_html( shadow_digest_roman( $year ) ) . '</abbr>'
			);
			?>
		</span>

	</footer>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render the optional homepage lead-column promo.
 *
 * The front-page template references this block. Without the registration the
 * site editor reports the block as unsupported.
 *
 * @since 1.4.4
 * @return string The rendered HTML.
 */
function broadside_blocks_render_lead_promo(): string {
	if ( ! function_exists( 'shadow_digest_lead_ad' ) ) {
		return '';
	}

	ob_start();
	shadow_digest_lead_ad();
	$html = trim( (string) ob_get_clean() );

	if ( '' === $html ) {
		return '';
	}

	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes( array( 'class' => 'digest-lead-ad-wrap' ) ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shadow_digest_lead_ad() escapes everything it prints.
	);
}

/**
 * Render the newsletter box.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_newsletter_block(): string {
	ob_start();
	shadow_digest_newsletter();
	$html = (string) ob_get_clean();

	if ( '' === trim( $html ) ) {
		return '';
	}

	return $html;
}

/**
 * Render an article byline: author, avatar, date, reading time.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_byline(): string {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$author_id = (int) $post->post_author;
	$name      = (string) get_the_author_meta( 'display_name', $author_id );
	$role      = broadside_blocks_author_role( $author_id );
	$url       = (string) get_author_posts_url( $author_id );

	ob_start();
	?>
	<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'digest-byline-block' ) ) ); ?>>

		<div class="digest-byline-block__avatar">
			<?php shadow_digest_avatar( $author_id, 104, $name ); ?>
		</div>

		<div class="digest-byline-block__who">
			<span>
				<?php
				printf(
					/* translators: %s: the author's name, linked to their archive. */
					esc_html__( 'By %s', 'broadside-blocks' ),
					'<a href="' . esc_url( $url ) . '" rel="author">' . esc_html( $name ) . '</a>'
				);
				?>
				<?php if ( '' !== $role ) : ?>
					<?php echo esc_html( ', ' . $role ); ?>
				<?php endif; ?>
			</span>
		</div>

		<div class="digest-byline-block__when">
			<div>
				<?php
				printf(
					/* translators: %s: the publication date. */
					esc_html__( 'Published %s', 'broadside-blocks' ),
					'<time datetime="' . esc_attr( (string) get_the_date( DATE_W3C, $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>'
				);
				?>
			</div>

			<?php if ( shadow_digest_get( 'shadow_digest_reading_time' ) ) : ?>
				<div>
					<?php
					$minutes = shadow_digest_reading_minutes( $post );

					printf(
						/* translators: %d: the estimated reading time in minutes. */
						esc_html( _n( '%d-minute read', '%d-minute read', $minutes, 'broadside-blocks' ) ),
						(int) $minutes
					);
					?>
				</div>
			<?php endif; ?>
		</div>

	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render the author bio printed at the foot of an article.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_author_bio(): string {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$author_id = (int) $post->post_author;
	$bio       = (string) get_the_author_meta( 'description', $author_id );

	// No bio, no box. An empty "About the author" panel is worse than none.
	if ( '' === trim( $bio ) ) {
		return '';
	}

	$name = (string) get_the_author_meta( 'display_name', $author_id );
	$url  = (string) get_author_posts_url( $author_id );

	ob_start();
	?>
	<section <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'digest-author-bio' ) ) ); ?> id="author">

		<div class="digest-author-bio__avatar">
			<?php shadow_digest_avatar( $author_id, 144, $name ); ?>
		</div>

		<div>
			<p class="digest-author-bio__label"><?php esc_html_e( 'About the Author', 'broadside-blocks' ); ?></p>

			<p class="digest-author-bio__name">
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
			</p>

			<p class="digest-author-bio__text"><?php echo esc_html( $bio ); ?></p>
		</div>

	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render the editorial-standards note.
 *
 * @since 1.0.0
 * @return string The rendered HTML.
 */
function broadside_blocks_render_standards(): string {
	ob_start();
	shadow_digest_standards_note();

	return (string) ob_get_clean();
}

/**
 * Add a "Role" field to the user profile, so a byline can read "Chief
 * Correspondent" rather than just a name.
 *
 * @since 1.0.0
 * @param WP_User $user The user being edited.
 * @return void
 */
function broadside_blocks_user_role_field( WP_User $user ): void {
	?>
	<h2><?php esc_html_e( 'Broadside', 'broadside-blocks' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="broadside_blocks_role"><?php esc_html_e( 'Masthead title', 'broadside-blocks' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					name="broadside_blocks_role"
					id="broadside_blocks_role"
					value="<?php echo esc_attr( broadside_blocks_author_role( (int) $user->ID ) ); ?>"
					class="regular-text"
				/>
				<p class="description">
					<?php esc_html_e( 'Printed after the name in the byline — “Chief Correspondent”, “Science Editor”, “Contributing Writer”.', 'broadside-blocks' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'broadside_blocks_user_role_field' );
add_action( 'edit_user_profile', 'broadside_blocks_user_role_field' );

/**
 * Save the masthead title field.
 *
 * @since 1.0.0
 * @param int $user_id The user being saved.
 * @return void
 */
function broadside_blocks_save_user_role_field( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// WordPress verifies the update-user nonce before this hook fires, but the
	// Theme Directory expects to see the check made explicitly at the point of
	// use rather than assumed.
	if (
		! isset( $_POST['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id )
	) {
		return;
	}

	if ( ! isset( $_POST['broadside_blocks_role'] ) ) {
		return;
	}

	$role = sanitize_text_field( wp_unslash( $_POST['broadside_blocks_role'] ) );

	update_user_meta( $user_id, 'broadside_blocks_role', $role );

	// Drop the legacy key once the prefixed value is written.
	if ( '' !== $role ) {
		delete_user_meta( $user_id, 'shadow_digest_role' );
	}
}
add_action( 'personal_options_update', 'broadside_blocks_save_user_role_field' );
add_action( 'edit_user_profile_update', 'broadside_blocks_save_user_role_field' );
