# Broadside — a broadsheet block theme for WordPress

One theme, two live publications. `cannabisdigest.net` and `marksmansdigest.com`
run **byte-identical code**; everything that differs between them is Customizer
settings. That is the whole design goal — do not break it by hard-coding a brand.

---

## 🔴 READ THIS FIRST

**On 2026-07-13 this theme took down a shared production VPS for ~40 minutes,
including `americanguntrader.com`, a live marketplace with real users on it.**

Root cause: a block render callback called `do_blocks()` on the post content to
find its headings. That re-renders every block in the content — *including the
block that called it* — with no base case. Requests spun forever, hung every
PHP-FPM worker on the box, and starved it until it could not fork `sshd`. Full
account: **[`docs/INCIDENT-2026-07-13-vps-outage.md`](docs/INCIDENT-2026-07-13-vps-outage.md)**.
Read it before you change anything. It is not long and it is not padded.

The three rules it produced:

1. **Never render post content inside a render callback.** No `do_blocks()`, no
   `apply_filters( 'the_content', … )`, no `get_the_content()` that runs the
   block parser. Read the **raw** `post_content` — a `core/heading` block saves
   its `<h2>` markup verbatim, so the HTML is already there. `scripts/deploy.sh`
   and CI both refuse to ship a theme containing such a call.
2. **Nothing deploys that has not rendered locally first.** `./scripts/local-wp.sh up`
   builds a throwaway WordPress in seconds. The theme was originally deployed to
   two live sites having never been executed anywhere.
3. **A green test suite is evidence, not proof. Look at the page.** Three separate
   bugs this day passed every automated check and were caught only by a human
   reading the rendered HTML. The most recent: Cannabis Digest silently served
   "The Weekly Dispatch" and "the week in marksmanship" for an hour.

---

## Working on this

```bash
./scripts/local-wp.sh up          # throwaway WordPress on :8080, admin/admin
./scripts/local-smoke.sh          # every template renders, fast, no fatals
php scripts/local-assert.php      # the pages are CORRECT (36 assertions)
./scripts/deploy.sh <site>        # gated deploy to ONE site
./scripts/deploy.sh <site> --rollback
```

The sandbox runs with `max_execution_time=10` and `memory_limit=256M`, so a
runaway recursion dies in ten seconds with a stack trace instead of eating a
machine. That is deliberate.

`deploy.sh` will not deploy unless all five gates pass:

| Gate | Checks |
|---|---|
| 1 | PHP + JSON lint |
| 2 | **No live `do_blocks()`** — the outage bug |
| 3 | Local sandbox is running |
| 4 | Every template renders (liveness) |
| 5 | Every assertion passes (**correctness**) |

Gate 5 exists because gate 4 is not enough: the re-entrancy guard turns a
recursion into a *fast but wrong* page, so a liveness check happily blesses it.
This was proven — see §7a of the incident doc.

---

## Architecture

A **block theme**. Layout lives in `templates/` and `parts/`; design tokens in
`theme.json`; there is no `header.php`/`footer.php`.

```
functions.php            bootstrap; defines SHADOW_DIGEST_* constants
theme.json               all design tokens — colour, type scale, spacing
inc/
  setup.php              theme supports, asset enqueueing
  customizer.php         ★ every per-site setting lives here
  template-tags.php      masthead, folio, reading time, Roman numerals, avatars
  blocks.php             editorial blocks + the re-entrancy guard
  blocks-masthead.php    masthead furniture blocks
  patterns.php           pattern categories
  schema.php             JSON-LD — silent when an SEO plugin is active
blocks/*/block.json      17 blocks, all PHP-rendered (dynamic)
templates/               front-page, single, archive, search, 404, page
assets/css/digest.css    everything theme.json cannot express
assets/js/blocks.js      editor UI — no build step, no bundler, plain wp.* globals
assets/fonts/            4 bundled woff2 (SIL OFL) — no CDN calls
```

### Naming (mirrors `shadow-software-crypto-for-woocommerce`, which passed .org review)

| Thing | Value |
|---|---|
| Slug / directory / **text domain** | `broadside` |
| Function prefix | `shadow_digest_` |
| Constant prefix | `SHADOW_DIGEST_` |
| Block namespace | `shadow-digest/` |
| CSS classes | `.digest-*` (cosmetic — deliberately *not* renamed) |
| Display name | **Broadside** |

WordPress requires the text domain to equal the directory name. The function
prefix does not have to, and does not — 43-character function names are unusable.

### The DRY rule

**No brand name, colour, year, city or section list is hard-coded anywhere.**
Every one is a Customizer setting declared in `shadow_digest_settings()` and read
through `shadow_digest_get()`. Adding a setting is a one-line change to that
array. If you find yourself typing a brand string into a template, stop.

Per-site values live in the site repos as code, not just in the database:
`../cannabisdigest.net/scripts/configure-theme.php` and the equivalent for
Marksman's. Re-runnable; they are the source of truth.

---

## Gotchas that have already bitten

- **`get_avatar()` blocks on Gravatar.** On a box whose outbound HTTP is
  firewalled it hangs until the socket times out — two avatars on an article page
  is two hangs. Use `shadow_digest_avatar()`, which prefers a local upload and
  falls back to a printed monogram.
- **`wp-cli` on PHP 8.5 floods stderr with its own deprecation notices.** It
  drowns real output and makes a *successful* script look like a silent failure.
  This wasted twenty minutes during the incident. Filter with
  `grep -viE 'deprecated|Colors\.php|react/promise'`, or wrap the call in a
  `set_error_handler` that swallows notices from `php-cli-tools`.
- **Bump `SHADOW_DIGEST_VERSION` on every asset change**, in lockstep with
  `style.css` and `readme.txt`. It is the cache-buster; without it, CSS changes
  never reach returning visitors. CI enforces the three staying equal.
- **Block names are stored inside `post_content`** (`<!-- wp:shadow-digest/faq -->`).
  Renaming a block without migrating post content makes it render *nothing* —
  silently, with no error. See `scripts/migrate-slug.php`.
- **Theme mods are keyed by slug** (`theme_mods_<slug>`). A slug rename orphans
  them and the theme falls back to defaults — which are Marksman's. See
  `scripts/migrate-modkeys.php`.

---

## Deploying

Never `rsync` by hand. That is how the outage happened.

```bash
./scripts/deploy.sh cannabisdigest.net     # one site
# verify it, then:
./scripts/deploy.sh marksmansdigest.com
```

It uploads, activates, probes the front page **and an article page** (the class of
page that broke last time), and rolls back automatically if either is unhealthy.

Server: `93.95.229.147`, webroots at `/var/www/<domain>/public`. **It is a shared
box.** `americanguntrader.com`, `dabdash.com`, `shadowsoftware.com`,
`api.shadowsoftware.com` and `auralabs.asia` live on it. Work here is never
low-stakes.

---

## WordPress.org submission

Targeting the **Theme** Directory. `readme.txt` is the user-facing document;
`README.md` is not shipped (see `.distignore`).

Ready: GPL-2.0, bundled OFL fonts with attribution, `screenshot.png` at exactly
1200×900, `languages/*.pot`, `index.php`, no minified or third-party code, no
page-builder dependency, `phpcs.xml.dist` running `WPThemeReview`.

**Review house rules (enforced by `./scripts/package.sh`):**

1. **Never claim `accessibility-ready`.** That tag is a *separate* review
   queue and blocked a Theme Directory pass (2026-08). Keep skip-link/focus
   CSS; do not put the tag on the `Tags:` line.
2. **Theme URI optional.** If set, it must be the directory page
   (`https://wordpress.org/themes/broadside/`). GitHub / product pages are
   rejected. Omit until the theme is live, then set only that URL.
3. Every Customizer setting must be read by the runtime — no orphan controls.

The theme must work on **stock WordPress with no page builder**. It does not
depend on Elementor — Elementor is installed on both sites but unused, and a
theme that required it could not be submitted at all. Companion blocks are
declared via `Requires Plugins: broadside-blocks` (Plugin Dependencies API).
