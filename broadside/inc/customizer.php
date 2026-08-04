<?php
/**
 * Customizer settings — the theme's per-site branding surface.
 *
 * This file is the reason Broadside can dress two different publications from one
 * codebase. Everything that differs between, say, a cannabis trade journal and a
 * shooting-sports journal — the accent colour, the founding year, the city of
 * record, the strapline, the newsletter's name, the cover price — is registered
 * here as a Customizer setting with a sensible default.
 *
 * No brand name, no colour and no section list is hard-coded anywhere else in
 * the theme. If you find yourself wanting to add one, add a setting here instead.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The theme's settings and their defaults, in one place.
 *
 * Keyed by setting id. Each entry declares the default value, the sanitiser, the
 * control type, the section it belongs to, its label and its help text. The
 * registration function below walks this array, so adding a setting is a
 * one-line change rather than four.
 *
 * @since 1.0.0
 * @return array<string, array<string, mixed>> The setting definitions.
 */
function shadow_digest_settings(): array {
	$settings = array(

		/*
		 * ----------------------------------------------------------------
		 * Identity — who the publication is.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_strapline'               => array(
			'default'   => __( 'The Journal of Record', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Strapline', 'broadside' ),
			'help'      => __( 'Sits in the centre of the utility bar, above the masthead. Example: “The Journal of Record for the American Marksman”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_publisher_legal'         => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Legal publisher', 'broadside' ),
			'help'      => __( 'The legal entity behind the publication — printed in the footer and emitted as schema.org legalName when no SEO plugin overrides it.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_founded'                 => array(
			'default'   => '2019',
			'sanitize'  => 'shadow_digest_sanitize_year',
			'type'      => 'number',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Year founded', 'broadside' ),
			'help'      => __( 'A four-digit year. The theme renders it as Roman numerals in the utility bar (“Est. MCMXXVI”) and in plain digits in the footer.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_city'                    => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'City of record', 'broadside' ),
			'help'      => __( 'Printed beside the founding year: “Est. MCMXXVI · New York”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_motto'                   => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Motto', 'broadside' ),
			'help'      => __( 'Right-hand side of the folio rule, beneath the masthead.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_cover_price'             => array(
			'default'   => '$4.00',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Cover price', 'broadside' ),
			'help'      => __( 'Printed at the end of the folio rule. Leave empty to omit it.', 'broadside' ),
			'transport' => 'postMessage',
		),

		/*
		 * ----------------------------------------------------------------
		 * Masthead — the two ears either side of the nameplate.
		 * ----------------------------------------------------------------
		 */

		/*
		 * The masthead device: an engraved ornament printed BEHIND the nameplate.
		 *
		 * A real broadsheet does not put a photograph behind its nameplate; it puts
		 * an engraving — a device, cut on steel, printed in the same ink as the type.
		 * So this is deliberately not the article page's photographic backdrop. It is
		 * line-work, it is monochrome, and it is tinted with the theme's own ink
		 * colour rather than carrying a colour of its own.
		 *
		 * Ship it as a PNG whose INK IS ITS ALPHA — transparent paper, opaque line —
		 * and the same file then works on cream, on a dark style variation, and on
		 * either publication, taking its colour from the theme rather than fighting it.
		 *
		 * Empty by default. A theme that shipped a flag would be a theme with a
		 * nationality, and this one dresses two publications that have nothing in
		 * common. See the DRY rule in CLAUDE.md.
		 */
		'shadow_digest_masthead_device'         => array(
			'default'   => '',
			'sanitize'  => 'esc_url_raw',
			'type'      => 'image',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Masthead device', 'broadside' ),
			'help'      => __( 'An engraved ornament printed faintly behind the nameplate — a furled flag, a botanical plate, a coat of arms. Use a PNG with a transparent background and dark line-work: the theme tints it with the ink colour and fades it into the paper. Leave empty for no device.', 'broadside' ),
			'transport' => 'refresh',
		),

		'shadow_digest_masthead_device_opacity' => array(
			'default'   => 22,
			'sanitize'  => 'absint',
			'type'      => 'number',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Masthead device — strength (%)', 'broadside' ),
			'help'      => __( 'How strongly the device prints. The default is deliberately faint: it is a watermark behind the nameplate, not a picture. Above about 35% it starts to compete with the type.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_weather_enable'          => array(
			'default'  => false,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_masthead',
			'label'    => __( 'Live weather in the left ear', 'broadside' ),
			'help'     => __( 'When on, the left ear shows a live forecast for the city of record (Open-Meteo, cached about an hour). Falls back to the static heading and text below if the fetch fails.', 'broadside' ),
		),

		'shadow_digest_weather_lat'             => array(
			'default'   => '',
			'sanitize'  => 'shadow_digest_sanitize_coord',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Weather latitude', 'broadside' ),
			'help'      => __( 'Optional. With longitude, skips geocoding — useful when the city of record is a region (e.g. Montana → Helena).', 'broadside' ),
		),

		'shadow_digest_weather_lon'             => array(
			'default'   => '',
			'sanitize'  => 'shadow_digest_sanitize_coord',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Weather longitude', 'broadside' ),
			'help'      => __( 'Optional. Pair with latitude.', 'broadside' ),
		),

		'shadow_digest_weather_units'           => array(
			'default'  => 'auto',
			'sanitize' => 'shadow_digest_sanitize_weather_units',
			'type'     => 'select',
			'section'  => 'shadow_digest_masthead',
			'label'    => __( 'Weather units', 'broadside' ),
			'help'     => __( 'Auto picks °F/mph west of ~30°W and °C/km/h elsewhere.', 'broadside' ),
			'choices'  => array(
				'auto'     => __( 'Auto', 'broadside' ),
				'metric'   => __( 'Metric (°C, km/h)', 'broadside' ),
				'imperial' => __( 'Imperial (°F, mph)', 'broadside' ),
			),
		),

		'shadow_digest_ear_left_title'          => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Left ear — heading', 'broadside' ),
			'help'      => __( 'Fallback when live weather is off or unreachable. Traditionally a weather or conditions report.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_left_body'           => array(
			'default'   => '',
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Left ear — text', 'broadside' ),
			'help'      => __( 'Fallback copy. Two short lines read best. Line breaks are preserved.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_right_title'         => array(
			'default'   => __( 'Est. 2019', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Right ear — heading', 'broadside' ),
			'help'      => __( 'The small block to the right of the nameplate. Traditionally the volume or anniversary.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_right_body'          => array(
			'default'   => __( "Founded in 2019 to serve\nreaders with independent reporting.", 'broadside' ),
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Right ear — text', 'broadside' ),
			'help'      => __( 'Two short lines read best. Line breaks are preserved.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_volume'                  => array(
			'default'   => __( 'Vol. VII', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Volume', 'broadside' ),
			'help'      => __( 'Left-hand side of the folio rule. Paired with the issue number.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_issue'                   => array(
			'default'   => __( 'No. 28', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Issue number', 'broadside' ),
			'help'      => __( 'Printed after the volume: “Vol. C · No. 28”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_wordmark_font'           => array(
			'default'  => 'masthead',
			'sanitize' => 'shadow_digest_sanitize_wordmark_font',
			'type'     => 'select',
			'section'  => 'shadow_digest_masthead',
			'label'    => __( 'Nameplate typeface', 'broadside' ),
			'help'     => __( 'Blackletter is the traditional newspaper nameplate. Choose the display serif for a cleaner, more modern masthead — or upload a custom logo under Site Identity to replace the type entirely.', 'broadside' ),
			'choices'  => array(
				'masthead' => __( 'Blackletter (UnifrakturMaguntia)', 'broadside' ),
				'display'  => __( 'Display serif (Libre Caslon)', 'broadside' ),
			),
		),

		/*
		 * ----------------------------------------------------------------
		 * Colour — the one setting that most changes the theme's character.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_accent'                  => array(
			'default'   => '#6b1f1f',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Accent', 'broadside' ),
			'help'      => __( 'Rules, kickers, the drop cap, links and the subscribe button. Pick something dark enough to pass contrast on the paper background — the theme warns you below if it does not.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_accent_soft'             => array(
			'default'   => '#c99a5b',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Accent, soft', 'broadside' ),
			'help'      => __( 'Used only on the dark newsletter panel, where the main accent would be too dark to read. Pick a lighter tint of the accent.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_kicker'                  => array(
			'default'   => '#8a5a2a',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Kicker', 'broadside' ),
			'help'      => __( 'The small uppercase category labels above headlines — “Legislation”, “History”, “Profile”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper'                   => array(
			'default'   => '#f4efe4',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Paper', 'broadside' ),
			'help'      => __( 'The colour of the printed sheet itself.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper_shade'             => array(
			'default'   => '#e7dfce',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Desk', 'broadside' ),
			'help'      => __( 'The darker surface the sheet sits on, visible around the page edges.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper_tint'              => array(
			'default'   => '#eef0e6',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Tinted panel', 'broadside' ),
			'help'      => __( 'Background of the short-answer box and the author bio. A barely-there tint of the accent reads best.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_texture'                 => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_colour',
			'label'    => __( 'Halftone paper texture', 'broadside' ),
			'help'     => __( 'A faint dot screen over the sheet, like newsprint under a loupe. Costs nothing — it is a CSS gradient, not an image.', 'broadside' ),
		),

		/*
		 * ----------------------------------------------------------------
		 * Newsletter — presentation only. See inc/template-tags.php.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_newsletter_enable'       => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Show the newsletter box', 'broadside' ),
			'help'     => __( 'The dark panel on the front page and beneath each article.', 'broadside' ),
		),

		'shadow_digest_newsletter_name'         => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter name', 'broadside' ),
			'help'      => __( 'Set in the display serif, large. Example: “The Weekly Dispatch”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_eyebrow'      => array(
			'default'   => __( 'The Flagship Newsletter · Every Thursday', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter eyebrow', 'broadside' ),
			'help'      => __( 'The small line above the newsletter name.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_blurb'        => array(
			'default'   => '',
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter description', 'broadside' ),
			'help'      => __( 'One or two sentences. Set in italic beside the signup field.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_note'         => array(
			'default'   => __( 'Free weekly. Unsubscribe anytime.', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Fine print', 'broadside' ),
			'help'      => __( 'Sits under the signup field. A good place for your unsubscribe promise.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_count'        => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Subscriber count', 'broadside' ),
			'help'      => __( 'Shown as “Join 48,000 readers”. Leave empty to hide the line — an honest empty is better than an invented number.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_action'       => array(
			'default'  => '',
			'sanitize' => 'esc_url_raw',
			'type'     => 'url',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Form endpoint', 'broadside' ),
			'help'     => __( 'Where the signup form posts. Broadside stores no subscribers and sends no mail — it renders the form and hands the address to whatever service you name here (a webhook, an automation, a mailing-list provider). Leave empty and the form is not rendered at all.', 'broadside' ),
		),

		'shadow_digest_newsletter_field'        => array(
			'default'  => 'email',
			'sanitize' => 'shadow_digest_sanitize_field_name',
			'type'     => 'text',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Email field name', 'broadside' ),
			'help'     => __( 'The name attribute of the email input. Whatever your endpoint expects — most want “email”.', 'broadside' ),
		),

		/*
		 * ----------------------------------------------------------------
		 * Podcast — optional narrated-audio companion to each article.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_podcast_enable'          => array(
			'default'  => false,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_podcast',
			'label'    => __( 'Enable podcast feed + player', 'broadside' ),
			'help'     => __( 'Serves /feed/podcast/ for Apple, Spotify and Google, and shows an audio player on posts that have a podcast_audio_url.', 'broadside' ),
		),

		'shadow_digest_podcast_title'           => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'Show title', 'broadside' ),
			'help'      => __( 'Leave empty to use the site name.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_podcast_summary'         => array(
			'default'   => '',
			'sanitize'  => 'sanitize_textarea_field',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'Show summary', 'broadside' ),
			'help'      => __( 'One or two sentences for the directory listing. Leave empty to use the site tagline.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_podcast_author'          => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'Show author', 'broadside' ),
			'help'      => __( 'itunes:author. Leave empty to use the site name.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_podcast_owner_name'      => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'Owner name', 'broadside' ),
			'help'      => __( 'Shown only to directories (itunes:owner). Defaults to the show author.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_podcast_owner_email'     => array(
			'default'  => '',
			'sanitize' => 'sanitize_email',
			'type'     => 'email',
			'section'  => 'shadow_digest_podcast',
			'label'    => __( 'Owner email', 'broadside' ),
			'help'     => __( 'Required by Apple Podcasts for the show owner contact. Not shown publicly on the site.', 'broadside' ),
		),

		'shadow_digest_podcast_category'        => array(
			'default'   => 'News',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'iTunes category', 'broadside' ),
			'help'      => __( 'Top-level Apple Podcasts category, e.g. “News” or “Sports”.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_podcast_image'           => array(
			'default'  => '',
			'sanitize' => 'esc_url_raw',
			'type'     => 'url',
			'section'  => 'shadow_digest_podcast',
			'label'    => __( 'Show artwork URL', 'broadside' ),
			'help'     => __( 'Square image, ideally 1400×1400 or larger. Falls back to the custom logo, then the site icon.', 'broadside' ),
		),

		'shadow_digest_podcast_explicit'        => array(
			'default'  => false,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_podcast',
			'label'    => __( 'Mark show as explicit', 'broadside' ),
		),

		'shadow_digest_podcast_player_label'    => array(
			'default'   => __( 'Listen', 'broadside' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_podcast',
			'label'     => __( 'Player label', 'broadside' ),
			'help'      => __( 'Caption above the on-article audio player.', 'broadside' ),
			'transport' => 'postMessage',
		),

		/*
		 * ----------------------------------------------------------------
		 * Article furniture.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_dropcap'                 => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_article',
			'label'    => __( 'Drop cap on the opening paragraph', 'broadside' ),
			'help'     => __( 'Applied automatically to the first paragraph of every article.', 'broadside' ),
		),

		'shadow_digest_reading_time'            => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_article',
			'label'    => __( 'Show reading time', 'broadside' ),
			'help'     => __( 'Estimated from the word count at 220 words a minute.', 'broadside' ),
		),

		'shadow_digest_lead_ad_image'           => array(
			'default'   => '',
			'sanitize'  => 'esc_url_raw',
			'type'      => 'image',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Homepage lead-column promo image', 'broadside' ),
			'help'      => __( 'Optional. When set, prints at the top of the front-page right column above the aside story. Leave empty to omit.', 'broadside' ),
			'transport' => 'refresh',
		),

		'shadow_digest_lead_ad_image_2'         => array(
			'default'   => '',
			'sanitize'  => 'esc_url_raw',
			'type'      => 'image',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Homepage lead-column promo image — second creative (optional)', 'broadside' ),
			'help'      => __( 'Optional. When set, the promo crossfades between the two images. Leave empty for a single static image.', 'broadside' ),
			'transport' => 'refresh',
		),

		'shadow_digest_lead_ad_link'            => array(
			'default'   => '',
			'sanitize'  => 'esc_url_raw',
			'type'      => 'url',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Homepage lead-column promo link', 'broadside' ),
			'help'      => __( 'Destination URL for the promo image. Opens in a new tab.', 'broadside' ),
			'transport' => 'refresh',
		),

		'shadow_digest_lead_ad_alt'             => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Homepage lead-column promo alt text', 'broadside' ),
			'help'      => __( 'Describe the promo for screen readers.', 'broadside' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_standards'               => array(
			'default'   => '',
			'sanitize'  => 'wp_kses_post',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_article',
			'label'     => __( 'Editorial standards note', 'broadside' ),
			'help'      => __( 'Printed in small italics at the foot of every article, above the related stories. A good place for your fact-checking policy, an age warning, or a link to your ethics page. Basic links and emphasis are allowed. Leave empty to omit it.', 'broadside' ),
			'transport' => 'postMessage',
		),
	);

	/**
	 * Filters the theme's Customizer setting definitions.
	 *
	 * A child theme can add, remove or re-default any setting without touching
	 * this file.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $settings The setting definitions.
	 */
	return apply_filters( 'shadow_digest_settings', $settings );
}

/**
 * Read one Broadside setting, falling back to its declared default.
 *
 * Always use this rather than get_theme_mod() directly: it guarantees the
 * default in shadow_digest_settings() is the single source of truth, so a default can
 * never drift between the Customizer and the templates that read it.
 *
 * @since 1.0.0
 * @param string $key The setting id, without the shadow_digest_ prefix being optional — pass the full id.
 * @return mixed The stored value, or the declared default.
 */
function shadow_digest_get( string $key ) {
	$settings = shadow_digest_settings();

	if ( ! isset( $settings[ $key ] ) ) {
		return null;
	}

	return get_theme_mod( $key, $settings[ $key ]['default'] );
}

/**
 * Register every setting and control declared in shadow_digest_settings().
 *
 * @since 1.0.0
 * @param WP_Customize_Manager $wp_customize The Customizer manager.
 * @return void
 */
function shadow_digest_customize_register( WP_Customize_Manager $wp_customize ): void {

	$panel = 'shadow_digest_panel';

	$wp_customize->add_panel(
		$panel,
		array(
			'title'       => __( 'Broadside', 'broadside' ),
			'description' => __( 'Everything that makes this publication yours. Broadside hard-codes no brand: the name, the colours, the founding year and the section list all live here, so the same theme can dress two entirely different journals.', 'broadside' ),
			'priority'    => 25,
		)
	);

	$sections = array(
		'shadow_digest_identity'   => array(
			'title'       => __( 'Identity', 'broadside' ),
			'description' => __( 'Who the publication is, and what it prints on its folio rule.', 'broadside' ),
		),
		'shadow_digest_masthead'   => array(
			'title'       => __( 'Masthead', 'broadside' ),
			'description' => __( 'The nameplate and the two “ears” either side of it.', 'broadside' ),
		),
		'shadow_digest_colour'     => array(
			'title'       => __( 'Colour & paper', 'broadside' ),
			'description' => __( 'The accent colour does most of the work. Everything else is the paper it is printed on.', 'broadside' ),
		),
		'shadow_digest_newsletter' => array(
			'title'       => __( 'Newsletter', 'broadside' ),
			'description' => __( 'Broadside renders the signup form. It does not store subscribers or send mail — point the form at your own service.', 'broadside' ),
		),
		'shadow_digest_podcast'    => array(
			'title'       => __( 'Podcast', 'broadside' ),
			'description' => __( 'Optional narrated-audio companion. Posts with a podcast_audio_url appear in /feed/podcast/ and get an on-article player.', 'broadside' ),
		),
		'shadow_digest_article'    => array(
			'title'       => __( 'Article furniture', 'broadside' ),
			'description' => __( 'The details of the long-read layout.', 'broadside' ),
		),
	);

	foreach ( $sections as $id => $section ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'       => $section['title'],
				'description' => $section['description'],
				'panel'       => $panel,
			)
		);
	}

	foreach ( shadow_digest_settings() as $id => $args ) {

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $args['default'],
				'sanitize_callback' => $args['sanitize'],
				'transport'         => $args['transport'] ?? 'refresh',
			)
		);

		$control = array(
			'label'       => $args['label'],
			'description' => $args['help'] ?? '',
			'section'     => $args['section'],
			'settings'    => $id,
			'type'        => $args['type'],
		);

		if ( isset( $args['choices'] ) ) {
			$control['choices'] = $args['choices'];
		}

		// The colour picker needs a dedicated control class; everything else is
		// served by the default one.
		if ( 'color' === $args['type'] ) {
			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					$id,
					array(
						'label'       => $args['label'],
						'description' => $args['help'] ?? '',
						'section'     => $args['section'],
						'settings'    => $id,
					)
				)
			);
			continue;
		}

		// An image, stored as a URL. WP_Customize_Image_Control gives the media
		// library, which is the only sane way to choose a picture — a URL field
		// would mean uploading somewhere else first and pasting a path.
		if ( 'image' === $args['type'] ) {
			$wp_customize->add_control(
				new WP_Customize_Image_Control(
					$wp_customize,
					$id,
					array(
						'label'       => $args['label'],
						'description' => $args['help'] ?? '',
						'section'     => $args['section'],
						'settings'    => $id,
					)
				)
			);
			continue;
		}

		if ( 'number' === $args['type'] ) {
			$control['input_attrs'] = array(
				'min'  => 1000,
				'max'  => (int) gmdate( 'Y' ),
				'step' => 1,
			);
		}

		$wp_customize->add_control( $id, $control );
	}

	// Live-preview the text settings without a full page reload. Selective
	// refresh keeps the Customizer honest and fast.
	if ( isset( $wp_customize->selective_refresh ) ) {
		$partials = array(
			'shadow_digest_strapline'        => '.digest-utility__strapline',
			'shadow_digest_motto'            => '.digest-folio__motto',
			'shadow_digest_volume'           => '.digest-folio__volume',
			'shadow_digest_newsletter_name'  => '.digest-newsletter__name',
			'shadow_digest_newsletter_blurb' => '.digest-newsletter__blurb',
		);

		foreach ( $partials as $setting => $selector ) {
			$wp_customize->selective_refresh->add_partial(
				$setting,
				array(
					'selector'        => $selector,
					'render_callback' => static function () use ( $setting ) {
						return esc_html( (string) shadow_digest_get( $setting ) );
					},
				)
			);
		}
	}
}
add_action( 'customize_register', 'shadow_digest_customize_register' );

/**
 * Compile the Customizer colour settings into CSS custom properties.
 *
 * These override the theme.json presets of the same name, which is what lets a
 * publisher recolour the entire theme — including the blocks, which reference
 * the presets — from four colour pickers.
 *
 * @since 1.0.0
 * @return string A :root rule, ready to be inlined.
 */
function shadow_digest_custom_properties(): string {
	$accent      = (string) shadow_digest_get( 'shadow_digest_accent' );
	$accent_soft = (string) shadow_digest_get( 'shadow_digest_accent_soft' );
	$kicker      = (string) shadow_digest_get( 'shadow_digest_kicker' );
	$paper       = (string) shadow_digest_get( 'shadow_digest_paper' );
	$shade       = (string) shadow_digest_get( 'shadow_digest_paper_shade' );
	$tint        = (string) shadow_digest_get( 'shadow_digest_paper_tint' );

	$wordmark = 'display' === shadow_digest_get( 'shadow_digest_wordmark_font' )
		? 'var(--wp--preset--font-family--display)'
		: 'var(--wp--preset--font-family--masthead)';

	$texture = shadow_digest_get( 'shadow_digest_texture' )
		? 'radial-gradient(rgba(28,26,23,.014) 1px, transparent 1px)'
		: 'none';

	$css = ':root{';

	// Overriding the theme.json presets means every block, pattern and template
	// part that already references them picks the new colour up for free.
	$css .= '--wp--preset--color--accent:' . $accent . ';';
	$css .= '--wp--preset--color--accent-soft:' . $accent_soft . ';';
	$css .= '--wp--preset--color--kicker:' . $kicker . ';';
	$css .= '--wp--preset--color--paper:' . $paper . ';';
	$css .= '--wp--preset--color--paper-shade:' . $shade . ';';
	$css .= '--wp--preset--color--paper-tint:' . $tint . ';';

	$css .= '--digest-wordmark-font:' . $wordmark . ';';
	$css .= '--digest-texture:' . $texture . ';';

	/*
	 * The masthead device. `none` when unset, so the CSS rule that draws it costs
	 * nothing and matches nothing on a site that has not chosen one.
	 *
	 * esc_url() on the way out as well as esc_url_raw() on the way in: this string
	 * is being interpolated into a CSS url(), which is a different context from the
	 * database, and one escape does not cover the other.
	 */
	$device  = (string) shadow_digest_get( 'shadow_digest_masthead_device' );
	$opacity = (int) shadow_digest_get( 'shadow_digest_masthead_device_opacity' );
	$opacity = max( 0, min( 100, $opacity ) );

	$css .= '--digest-masthead-device:' . ( '' !== $device ? "url('" . esc_url( $device ) . "')" : 'none' ) . ';';
	$css .= '--digest-masthead-device-opacity:' . ( $opacity / 100 ) . ';';

	/*
	 * The ::before that draws the device paints a rectangle of ink and then masks
	 * the device out of it. `mask-image: none` does NOT mean "mask everything away"
	 * — it means "no mask", so with no device that rectangle painted in full and
	 * put a grey band across the masthead. Both live sites shipped that band.
	 *
	 * The mask cannot express "draw nothing", so the INK is what gets gated: it is
	 * only ever set when there is genuinely a device to carve out of it. With no
	 * device the custom property is absent, the CSS falls back to `transparent`,
	 * and nothing is painted at all.
	 */
	if ( '' !== $device ) {
		$css .= '--digest-masthead-ink:var(--wp--preset--color--ink);';
	}

	$css .= '}';

	/**
	 * Filters the inline custom properties.
	 *
	 * @since 1.0.0
	 * @param string $css The compiled :root rule.
	 */
	return apply_filters( 'shadow_digest_custom_properties', $css );
}

/*
 * --------------------------------------------------------------------------
 * Sanitisers.
 *
 * Every setting above names one of these. The Theme Directory requires that no
 * Customizer value reach the database unsanitised, and these are the callbacks
 * that guarantee it.
 * --------------------------------------------------------------------------
 */

/**
 * Sanitise a checkbox to a real boolean.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return bool
 */
function shadow_digest_sanitize_checkbox( $value ): bool {
	return (bool) $value;
}

/**
 * Sanitise a four-digit year, clamped to something a publication could plausibly
 * claim.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string A four-digit year, or the empty string.
 */
function shadow_digest_sanitize_year( $value ): string {
	$year = absint( $value );

	if ( $year < 1000 || $year > (int) gmdate( 'Y' ) ) {
		return '';
	}

	return (string) $year;
}

/**
 * Sanitise a multi-line text field, keeping the line breaks but nothing else.
 *
 * sanitize_text_field() strips newlines, which would collapse the masthead ears
 * onto one line. sanitize_textarea_field() keeps them.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string
 */
function shadow_digest_sanitize_multiline( $value ): string {
	return sanitize_textarea_field( (string) $value );
}

/**
 * Sanitise the nameplate typeface choice against the list the control offers.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string Either 'masthead' or 'display'.
 */
function shadow_digest_sanitize_wordmark_font( $value ): string {
	return in_array( $value, array( 'masthead', 'display' ), true ) ? (string) $value : 'masthead';
}

/**
 * Sanitise an HTML form field name.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string A safe name attribute, defaulting to 'email'.
 */
function shadow_digest_sanitize_field_name( $value ): string {
	$name = preg_replace( '/[^A-Za-z0-9_\-\[\]]/', '', (string) $value );

	return ( is_string( $name ) && '' !== $name ) ? $name : 'email';
}
