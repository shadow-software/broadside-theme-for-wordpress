=== Broadside ===
Contributors: shadowsoftware
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: news, blog, one-column, two-columns, three-columns, custom-colors, custom-logo, custom-menu, block-patterns, block-styles, editor-style, featured-images, full-site-editing, rtl-language-support, style-variations, template-editing, translation-ready, wide-blocks

A broadsheet block theme for news publications: blackletter masthead, folio rule, three-column lead grid, and the furniture a long read needs.

== Description ==

Broadside dresses a WordPress site as a broadsheet newspaper.

The front page is a three-column lead grid: a rail of briefs, a lead story set in
two justified columns with a drop cap, and a photo with the day's secondary story
beside it. Above them sits a blackletter nameplate flanked by two "ears", a folio
rule carrying the volume, the date and the motto, and a section navigation. Below
them, an index of every section and its latest headlines, built from your
categories.

Articles get the furniture a reported feature actually needs, as blocks:

* **Short Answer** — a direct, quotable answer at the top, for the reader in a
  hurry and the search engine that wants to cite you.
* **Key Takeaways** — the three or four things a reader should leave with.
* **Table of Contents** — builds itself from your headings. Rename a heading and
  the contents follow; there is nothing to keep in sync by hand.
* **FAQ** — questions and answers that also emit FAQPage structured data.
* **Sources & References** — the receipts.
* **Disclosure Table** — a comparison table whose partner links are always marked
  `rel="sponsored nofollow"` and which always prints a disclosure line.
* **Byline**, **Author Bio** and **Editorial Standards** — the trust signals a
  journal of record is judged on.

= One theme, two publications =

Broadside hard-codes no brand. The accent colour, the founding year, the city of
record, the strapline, the motto, the cover price, the volume, the newsletter's
name and the masthead "ears" are all Customizer settings. Two sites running
Broadside can look like two entirely different journals — one a green cannabis trade
weekly, the other an oxblood shooting-sports monthly — from the same code.

= What Broadside does not do =

Broadside renders a newsletter signup form and hands the address to whatever
endpoint you name in the Customizer. It **stores no subscribers and sends no
mail**. That is deliberate: your subscriber list is yours, and a theme that held
it would lose it the day you switched themes. Point the form at your own
automation, mailing-list provider, or webhook.

Broadside also emits NewsArticle, Organization, BreadcrumbList and FAQPage
structured data — but only when no SEO plugin is active. If Yoast, Rank Math,
SEOPress or All in One SEO is running, they own the schema graph and Broadside gets
out of the way, because two competing graphs on one page is worse than none.

= Accessibility =

Broadside ships sensible defaults: a working skip link, a visible focus style on
every interactive element, correct heading order (the nameplate steps down from
`h1` to `p` on any page where an article headline is the real `h1`), form labels
on every input, `prefers-reduced-motion` honoured, and colour contrast that
passes AA on the default palette. The Customizer will let you pick an accent that
fails contrast — it is your publication — but the defaults do not.

This theme does **not** claim the WordPress.org `accessibility-ready` tag. That
tag triggers a separate, longer review queue.

== Installation ==

1. In your WordPress admin, go to Appearance → Themes → Add New.
2. Search for "Broadside", then click Install and Activate.
3. Go to Appearance → Customize → Broadside and set your accent colour, founding
   year, city, strapline and motto.
4. Go to Appearance → Editor to adjust the templates, or start writing — the
   theme works out of the box.

To reproduce the front page, you need a handful of published posts: the most
recent becomes the lead story, the next four fill the briefs rail, and the sixth
becomes the secondary story beside the photo.

== Frequently Asked Questions ==

= Does Broadside require a page builder? =

No. Broadside is a block theme built on core WordPress. It requires no plugin at
all, and it deliberately does not depend on Elementor, Divi, or any other page
builder — your layouts stay in your theme's files, under version control, rather
than in your database.

= Where do subscribers go when someone signs up? =

Wherever you send them. Broadside renders the form and posts it to the endpoint you
set in Customizer → Broadside → Newsletter. If you set no endpoint, no form is
rendered — Broadside will not show a form that silently throws addresses away.

= The table of contents is empty. =

It builds itself from the `h2` and `h3` headings in the post. A post with no
headings has no contents to list. Add a heading and it appears.

= Can I use my own logo instead of the blackletter nameplate? =

Yes. Set a custom logo under Customizer → Site Identity and it replaces the
type entirely. You can also switch the nameplate to a cleaner display serif under
Customizer → Broadside → Masthead.

= Why does the drop cap look wrong in my language? =

The drop cap uses `::first-letter`, which behaves differently across scripts. Turn
it off under Customizer → Broadside → Article furniture if it does not suit your
language.

== Copyright ==

Broadside, Copyright 2026 Shadow Software LLC.
Broadside is distributed under the terms of the GNU GPL v2 or later.

Broadside bundles no third-party code. It bundles four typefaces, all under the
SIL Open Font License 1.1, which is GPL-compatible. The copyright lines below are
transcribed from each font's own embedded metadata, not from a distributor's page.

UnifrakturMaguntia (assets/fonts/unifraktur-maguntia-400.woff2)
Copyright (c) 2010, j. 'mach' wust, with Reserved Font Name UnifrakturMaguntia.
Copyright (c) 2009, Peter Wiegel.
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/UnifrakturMaguntia
License: https://scripts.sil.org/OFL

Libre Caslon Display (assets/fonts/libre-caslon-display-400.woff2)
Copyright 2012, The Libre Caslon Display Authors
(https://github.com/impallari/Libre-Caslon-Display).
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/Libre+Caslon+Display
License: https://scripts.sil.org/OFL

Source Serif 4 (assets/fonts/source-serif-4-normal.woff2, source-serif-4-italic.woff2)
Copyright (c) 2014-2021, Adobe Systems Incorporated (https://adobe.com/), with
Reserved Font Name 'Source'.
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/Source+Serif+4
License: https://scripts.sil.org/OFL

The full text of the SIL Open Font License 1.1 ships with this theme at
assets/fonts/LICENSE-OFL.txt. The full text of the GNU GPL v2 ships at LICENSE.

The screenshot (screenshot.png) is a rendering of the theme itself, created by
Shadow Software LLC and licensed GPLv2 or later. It contains no third-party
imagery, no stock photography and no logos.

Broadside bundles no images, no icons, no SVG artwork and no JavaScript libraries.
Everything else in the theme zip is original code by Shadow Software LLC, licensed
GPLv2 or later.

== Upgrade Notice ==

= 1.3.3 =
Fixes a grey band printed across the masthead on any site that has not chosen a
masthead device. The band was the device's own ink, painted with nothing masked
out of it. If you do use a device, it is unchanged.

= 1.3.2 =
The newsletter form now submits without a page reload when JavaScript is
available, showing an inline confirmation instead. Plain form submission still
works exactly as before if JavaScript is off — nothing to configure.

= 1.3.1 =
Housekeeping. Broadside Blocks now has its own repository and its own release zips,
and the two are back on the same version number. Nothing on your site changes.

= 1.3.0 =
New: a masthead device — an engraved ornament printed faintly behind the nameplate.
Set one under Customizer → Broadside → Masthead. Nothing changes if you don't.

= 1.2.1 =
Fixes headlines printing `&apos;` and `&quot;` as visible text, and the same
entities leaking into the structured data that search engines read.

= 1.2.0 =
The blocks now live in a companion plugin, Broadside Blocks, which WordPress will
offer to install alongside the theme. Without it you get the paper, the type and
the grid, but no nameplate, folio or editorial blocks.

= 1.1.1 =
The theme is now called Broadside. It was called Digest, and a different author
already has a theme by that name in the Theme Directory. The text domain moves with
it, so a site running the old build should reapply its Customizer settings.

= 1.1.0 =
The front page's lead photograph is no longer lazy-loaded. If you run Broadside on
a site that is measured on Core Web Vitals, this release is worth taking.

== Changelog ==

= 1.4.1 =
* The left masthead ear can now show a live local forecast for the publication's
  city of record (Open-Meteo, no API key), cached for about an hour. Optional
  latitude/longitude overrides skip geocoding when the city of record is a
  region. Falls back to the static ear copy when disabled or unreachable.
* Theme Directory review: drop the `accessibility-ready` tag (it queues a
  separate a11y review), remove Theme URI (GitHub is not a valid Theme URI;
  re-add only after the theme is live on wordpress.org/themes/), and keep only
  Customizer settings the runtime still reads.

= 1.3.7 =
* The front page's right-hand column now holds up to three stories instead of
  one, and — once a site has at least three qualifying posts — the picks are
  the most-viewed articles from the last 30 days rather than always the same
  chronological slot. Falls back to the previous newest-first behaviour on a
  new or low-traffic site. Uses the view counter already tracked for the
  section grid; no new tracking, no external service.

= 1.3.6 =
* The homepage lead-column promo can now optionally crossfade between two
  creatives. Setting a second promo image via the Customizer stacks it behind
  the first and fades between them with a pure-CSS animation — no layout
  shift, no JavaScript, and it respects `prefers-reduced-motion`. A single
  image still renders statically as before.

= 1.3.3 =
* Fix: a solid grey band was printed across the masthead on every site with no
  masthead device chosen — which is to say, by default. The device is drawn by
  masking a rectangle of ink; with no device the mask was set to `none`, and in
  CSS `mask-image: none` means "no mask" rather than "mask everything", so the
  bare ink rectangle was painted at full size. The ink is now only laid down when
  there is actually a device to carve out of it.

= 1.3.2 =
* The newsletter form is now progressively enhanced: with JavaScript available it
  submits via `fetch` and shows an inline "check your inbox" or error message in
  place, instead of reloading the page. With JavaScript unavailable, or if the
  request fails to even start, the form's real `action`/`method` attributes mean
  a plain submit-and-reload still works — nothing about how the endpoint receives
  the address has changed.
* Corrected a leftover "Pressroom" name in the newsletter block's description.

= 1.3.1 =
* Broadside Blocks now lives in its own repository, with its own CI and its own
  release zips. The theme and the plugin ship together, so they are back on the same
  version number — a reader should never have to work out which pairs with which.
* Nothing on your site changes. This is repository housekeeping.

= 1.3.0 =
* **New: the masthead device.** An engraved ornament — a furled flag, a botanical
  plate, a coat of arms — printed faintly behind the nameplate, the way a real
  broadsheet prints one. Set it under Customizer → Broadside → Masthead, with a
  strength control; leave it empty and the masthead is exactly as it was.
* This is deliberately NOT the article page's photographic backdrop, though both
  are "an image behind text". A photograph has to be beaten into submission to sit
  behind a headline — desaturated, flattened, faded. An engraving is already quiet:
  it is ink. So the device is not drawn as a picture at all. Supply a PNG whose
  line-work is its transparency, and the theme uses it as a MASK on its own ink
  colour — which means one file prints correctly on cream paper, on a dark style
  variation, and in whatever colour a given publication's ink happens to be.
* It removes itself where it would do harm: under `prefers-contrast: more`, and on
  phones, where a masthead is barely wider than the nameplate and an ornament
  designed to sit at the flanks has no flanks left to sit in.
* No device ships with the theme. A theme that shipped a flag would be a theme with
  a nationality, and this one dresses two publications that have nothing in common.

= 1.2.1 =
* **Fixed: headlines printed `&apos;` and `&quot;` as visible text.** A title whose
  punctuation is stored as an HTML entity — which happens when the post is created
  by an automation rather than typed into the editor — was being escaped a second
  time by the theme, turning `&apos;` into `&amp;apos;`, which a browser renders as
  the literal characters. It showed on the front page's section index and in the
  related-posts rail beneath every article.
* **Fixed: the same entities were reaching search engines.** In JSON-LD it was not a
  display bug but a data bug: a JSON string is never HTML-decoded, so Google was
  being served `"headline":"CNBC&apos;s &quot;Best States&quot;…"` and would index
  exactly that. The article headline, the organisation name, the site tagline, the
  breadcrumb, the FAQ questions and the meta description were all affected.
* The theme now decodes a title or excerpt to plain text once, then escapes it for
  whichever context it is going into — HTML or JSON — instead of assuming WordPress
  has not already done it. New helper: `shadow_digest_plain_text()`.
* The sandbox now seeds a post whose title carries literal entities, and four new
  assertions check that nothing is escaped twice. Every seeded title before this
  had either real punctuation or none, so 36 green assertions had nothing to say
  about a bug that was live on a production site.

= 1.2.0 =
* **Renamed to Broadside, and the blocks moved to a companion plugin.** Two changes,
  both required by the WordPress.org Theme Directory, and both worth making anyway.
* The 17 blocks now live in **Broadside Blocks**, a separate plugin. The Theme
  Directory lists "Custom blocks" as plugin territory, and a theme may not register
  one. The rule's test is the right one:
  anything a user LOSES when they switch themes belongs in a plugin. A Short Answer,
  an FAQ and a Sources list are content — they live in your post, and shipping them
  inside a theme means changing theme silently blanks them. WordPress 6.5+ reads the
  theme's `Requires Plugins` header and offers to install the plugin for you. The
  theme still works without it: you get the paper, the type and the grid, and
  WordPress simply omits the blocks it cannot find.
* Fixed: **the blocks were never in their own editor category.** The theme registered
  a category with one slug while all 17 `block.json` files asked for another, so every
  block quietly landed in the editor's default bucket and the custom category sat
  empty. It had been that way since 1.0.0.
* The theme was previously called Pressroom, and before that Digest. Both names were
  already taken — Digest by another theme in the Directory, Pressroom by an
  established commercial news theme with 500+ installs. Broadside was verified free
  on the Theme Directory, on the Plugin Directory, and on the open web before it was
  chosen. A broadside is a sheet printed on one side: historically, a single-sheet
  news publication.

= 1.1.1 =
* **Renamed: Digest is now Broadside.** A theme called Digest already exists in the
  WordPress.org Theme Directory, by a different author. Sharing a name is not just
  a submission problem — WordPress matches theme updates by slug, so a site running
  this theme under a name someone else owns could be auto-updated to a stranger's
  theme. The slug, the text domain and the block namespace move with the name.
* The bundled fonts' copyright lines are now transcribed from each font's own
  embedded metadata rather than from a distributor's web page. Three were wrong:
  Libre Caslon Display is "Copyright 2012 The Libre Caslon Display Authors" (not
  2019, Impallari et al.); Source Serif 4 is 2014-2021 Adobe Systems Incorporated
  (not 2014-2023); and UnifrakturMaguntia had its two authors' years transposed.
  All four remain SIL OFL 1.1, which is GPL-compatible. Nothing about what the
  theme is allowed to ship has changed — only the accuracy of what it says about it.
* Added the Upgrade Notice section required of a Theme Directory readme, and cut
  the short description to 142 characters (the limit is 150; it was 168).

= 1.1.0 =
* **Fixed: the front page's lead photograph was lazy-loaded.** WordPress decides
  for itself which image to fetch eagerly — it takes the first "content" image on
  the page, marks it `fetchpriority="high"` and exempts it from lazy-loading. On
  an article page it picks correctly. On the front page it did the exact opposite:
  the lead photograph sits inside a group inside the query loop, which WordPress
  reads as a below-the-fold list thumbnail, so the single largest visible element
  of the site's most important page was served `loading="lazy"` and
  `fetchpriority="low"` — and nothing on the page was marked high priority at all.
  The browser would not begin fetching it until layout had run, then queued it
  behind everything else. Digest now tells WordPress the fact only the theme knows:
  on the front page, the first featured image IS the hero. The briefs rail and the
  secondary story stay lazy, because they genuinely are further down.
* The theme now builds its own WordPress.org submission package
  (`./scripts/package.sh`), and verifies the bytes that would actually be
  uploaded rather than the working tree they were copied from. This caught two dev
  dotfiles that were shipping inside every hand-made zip: they live in the theme
  directory, so `.distignore`'s root-anchored rules could never match them.
* The guard against the 2026-07-13 outage bug now tokenizes the PHP instead of
  grepping it, and is itself unit-tested (`./scripts/guard-selftest.sh`, 16 cases).
  The old grep could not tell a live `do_blocks()` call from a comment warning
  against one — it had been failing the build on every single run, flagging the
  comment that documents the rule. A build that is always red teaches people to
  stop reading it, which is worse than having no guard.

= 1.0.12 =
* The front page's lead story now carries its featured image as a BACKDROP behind
  the headline — the same treatment an article page gives it (1.0.10), so the two
  finally match. The lead previously had no image at all, which meant the biggest
  story on the paper was the only one with nothing to look at.
* Story cards respond to the cursor. Hovering a card warms its photograph and pulls
  its headline to the accent — quietly; a broadsheet does not lift, scale or bounce.
  This is confined to `@media (hover: hover)`, so a phone does not leave a card
  stuck in its hover state after a tap, which is what a plain :hover rule would do.
* The paper now reaches the bottom of the screen. The sheet was drawn exactly as
  tall as the content sitting on it, so a short page — My Account is a login card
  and a footer — stopped part-way down and left the rest of the viewport as bare
  desk, reading like a page that had failed to finish loading. It now fills the
  viewport (using `dvh`, so mobile browser chrome does not produce a scrollbar that
  scrolls to nothing) while a long page stays free to be longer.

= 1.0.11 =
* **Fixed: the cart and the checkout were rendering with no masthead at all.**
  WooCommerce ships its own block templates for Shop, Product, Cart and Checkout,
  and every one of them asks for a template part called `header`. Digest's is
  called `masthead`. WordPress found no `header`, so those four pages — the four a
  customer passes through on the way to paying — dropped the nameplate, the folio
  and the section navigation, and printed "Template part has been deleted or is
  unavailable: header" where the newspaper should have been. Digest now provides a
  `header` part, so a plugin that follows the WordPress convention finds what it
  expects.
* WooCommerce is now dressed in the newspaper's own clothes. Every page the plugin
  adds — the shop, a product, the cart, the checkout, My Account and its order
  history — is set in the theme's type, on the theme's paper, using the theme's
  rules and its oxblood accent. Digest treats WooCommerce's two eras (the React
  blocks of the shop and cart; the older PHP templates of My Account) as one shop,
  so the site does not change typeface when a reader logs in.
* The catalogue is a plate, not a card. Product photographs arrive in whatever
  shape the supplier sent, and WooCommerce prints them at their natural ratio, so a
  row of products ends at a row of different heights. Every plate is now cropped to
  4:5 and centred, and — because cropping an already-cropped thumbnail loses detail
  twice — the theme asks WooCommerce to GENERATE the 4:5 crop rather than crop its
  square one again.
* A product with no photograph no longer punches a grey hole in the page: the
  placeholder is tinted to the paper and faded back, so it reads as an absence
  rather than as an error.
* Buttons are declared once, in theme.json, as the theme's global button. Before,
  the theme had no opinion on what a button was, so WooCommerce's own slate-blue
  pill won by default — on every Add to Cart, on the login form, and at checkout.
* Entirely optional. With no WooCommerce installed none of this matches anything,
  and Digest remains a theme with no plugin dependency that runs on stock
  WordPress.
* New: `./scripts/shoot.sh` screenshots every page of the theme — including every
  shop page — at desktop and mobile, and builds a contact sheet putting the two
  side by side. The sandbox (`./scripts/local-wp.sh up`) now builds a WooCommerce
  shop as well as a newspaper, stocked with the awkward cases: a sale price, an
  out-of-stock item, a product with no image, a variable product, and a name long
  enough to wrap.

= 1.0.10 =
* The featured image is no longer a slab between the headline and the article. It
  is now a BACKDROP: bled to the edges of the sheet behind the eyebrow, headline,
  deck and byline, faded down into the paper and dissolved out on all four sides.
  The reader meets the headline first, with the photograph behind it, and the
  article body begins immediately underneath — there is nothing to scroll past.
  (1.0.9 clamped the slab's proportions and capped it at 88vh, which turned a
  1120px square into a 1120x792 letterbox: smaller, but still most of a screen of
  picture standing between the headline and the first word. Taming the slab was
  the wrong idea; the slab was the problem.)
* Verified: worst-case contrast behind the headline measures 9.77:1 against the
  darkest pixel of the backdrop — comfortably past WCAG AA. The treatment is
  removed entirely under prefers-contrast: more.
* WooCommerce account and cart links in the utility bar, in the cell that has
  always been empty on both sites. Entirely optional: renders nothing at all when
  WooCommerce is not installed, so the theme still works on stock WordPress.
* Fixed: the section grid ("Inside this week's edition") had no left border. The
  cells drew only their right and bottom edges, so the box closed on three sides
  by coincidence and the left was simply never drawn.
* Fixed: the masthead ears are now optically balanced. They were both bottom-
  aligned, but the left ear runs to three lines against the right's two, so the
  taller one started higher and read as though only one were centred.

= 1.0.9 =
* Tamed the featured image on single posts and pages. It previously had no styling
  at all, so its height was dictated entirely by whatever the editor uploaded: a
  tall portrait rendered 2,240px high on a 1440x900 desktop — 249% of the viewport,
  two and a half screens of picture before the first word of the article — while a
  panoramic crop came out as a 373px letterbox slit.
* The hero's proportions are now clamped into a band (no wider than 2.4:1, no taller
  than 4:3 on desktop / 4:5 on phones). An image whose shape is already reasonable
  is left completely untouched — an ordinary 16:9 photo passes through with no crop
  at all — and only a pathological one is overridden, cropped from the centre.
* A hero is now capped at 88vh, so the top of the article body is always within
  reach no matter how tall the source image.
* Undersized uploads are no longer blown up. A 320px-wide image was being stretched
  across the full 1120px sheet (a 3.5x upscale, visibly soft); it now sits centred
  at its true size, sharp.
* No layout shift: the aspect ratio is printed server-side, so the box reserves its
  final height before the image has loaded a single byte.

= 1.0.8 =
* Added a meta description to every page (singular posts/pages, category and tag
  archives, author archives, the front page) — stock WordPress prints none, which
  left every page's search-result snippet to chance.
* Added a canonical link to the front page and every category, tag and author
  archive (including their paginated pages). WordPress core's rel_canonical()
  only ever covers singular posts and pages; every other page type was silently
  uncanonicalised.
* Stopped enqueueing style.css as a front-end stylesheet. It carries only the
  theme header comment and no CSS rules; WordPress reads the header directly and
  never required the file be loaded in the browser, so this was a render-blocking
  HTTP request that improved nothing.

= 1.0.1 =
* **Fixed a fatal infinite recursion in the table-of-contents block.** It rendered
  the post content in order to find the headings, which re-rendered every block in
  that content — including itself — with no base case. Any article containing the
  block would hang the request forever. It now reads the raw post content, where
  the heading markup already lives, and renders nothing.
* Added a re-entrancy guard around every block render callback, so that no future
  bug of this shape can hang a request: a block that re-enters itself now returns
  empty instead of recursing.
* Author avatars no longer block page rendering on a Gravatar request. A site whose
  outbound HTTP is firewalled would hang on every avatar; Digest now prefers a
  locally-uploaded image, and falls back to a printed monogram.
* Replaced the related-posts query with a block that cannot recommend the article
  the reader is already on.
* Fixed related-post cards running their kicker, headline and summary together on
  one line.
* The newsletter panel no longer holds open an empty second column when no signup
  endpoint is configured.

= 1.0.0 =
* Initial release.
