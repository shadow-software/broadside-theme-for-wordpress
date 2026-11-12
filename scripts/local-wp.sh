#!/usr/bin/env bash
#
# Stand up a throwaway local WordPress to test the Digest theme.
#
# WHY THIS EXISTS: on 2026-07-13 this theme was deployed straight to a live
# shared VPS without ever having been run anywhere. It contained an infinite
# recursion, which hung every PHP-FPM worker on the box and took down seven
# sites — including a live marketplace that had nothing to do with this theme.
# See docs/INCIDENT-2026-07-13-vps-outage.md.
#
# Nothing in this theme goes near a server again until it has rendered every
# template here first.
#
# The sandbox is deliberately hostile to the bug that caused that outage:
#
#   * max_execution_time = 10, enforced by the CLI SAPI (unlike FPM, the CLI
#     server DOES honour it), so a runaway request dies in ten seconds instead
#     of forever.
#   * memory_limit = 256M, so a recursion exhausts its own memory and fatals
#     with a readable stack trace rather than swallowing the machine's RAM.
#   * xdebug.max_nesting_level, if xdebug is present, turns infinite recursion
#     into an immediate, named error.
#
# A recursion here produces a stack trace in a second. The same recursion on the
# VPS produced a forty-minute outage. That is the whole argument for this file.
#
# Usage:
#   ./scripts/local-wp.sh up        # build the site and start it on :8080
#   ./scripts/local-wp.sh down      # tear it all down
#   ./scripts/local-wp.sh reset     # down, then up

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP="$ROOT/.local-wp"
PORT="${BROADSIDE_LOCAL_PORT:-8080}"
THEME_SRC="$ROOT/broadside"
THEME_DST="$WP/wp-content/themes/broadside"

# The 17 blocks live in a companion PLUGIN now, because the Theme Directory
# forbids a theme from calling register_block_type(). The sandbox must install
# BOTH, or the masthead, the folio, the byline and every editorial block render
# nothing — and every test would still pass, because a missing block is not an
# error. That silence is exactly why the sandbox installs the plugin too.
PLUGIN_SRC="$ROOT/broadside-blocks"
PLUGIN_DST="$WP/wp-content/plugins/broadside-blocks"

# The guardrails. These are the difference between "a bug" and "an outage".
PHP_FLAGS=(
  -d max_execution_time=10
  -d memory_limit=256M
  -d error_reporting=E_ALL
  -d display_errors=1
  -d log_errors=1
)

# The 10-second execution cap above is for the SERVER, and it is the whole point of
# this sandbox: it turns a runaway recursion into a stack trace in ten seconds
# instead of a machine that has to be power-cycled. Do not raise it there.
#
# It is exactly wrong for wp-cli. Seeding is a one-off setup job — it installs
# WooCommerce, creates the shop pages, and writes 7 products with variations and
# images — and it takes about eight seconds of honest work. Against a 10-second
# ceiling that is not a margin, it is a coin toss, and the coin came up tails the
# moment activating the blocks plugin added a beat to the run: PHP killed the
# seeder mid-flight, `set -e` killed the script, and local-wp.sh exited BEFORE it
# ever flushed the rewrite rules or started the server. Every route then returned
# HTTP 000, which reads like "the theme is catastrophically broken" and is in fact
# "the web server was never started".
#
# So the CLI gets its own limits. A recursion in a render callback cannot hang
# wp-cli — wp-cli does not render pages.
CLI_FLAGS=(
  -d max_execution_time=0
  -d memory_limit=512M
  -d error_reporting=E_ALL
  -d display_errors=1
  -d log_errors=1
)

wp_cli() {
  php "${CLI_FLAGS[@]}" "$(command -v wp)" --path="$WP" --allow-root "$@"
}

cmd_down() {
  echo "── stopping any running server"
  pkill -f "php.*-S.*:$PORT" 2>/dev/null || true
  echo "── removing $WP"
  rm -rf "$WP"
  echo "── done"
}

cmd_up() {
  if [ -d "$WP" ]; then
    echo "!! $WP already exists — run './scripts/local-wp.sh reset' to rebuild"
    exit 1
  fi

  mkdir -p "$WP"
  cd "$WP"

  echo "── downloading WordPress core"
  wp_cli core download --version=latest --skip-content

  echo "── configuring for SQLite (no MySQL daemon, nothing to leak)"
  # The SQLite integration is an official WordPress plugin; it lets core run
  # against a file instead of a database server.
  mkdir -p wp-content/{plugins,themes,mu-plugins,database}
  wp_cli config create \
    --dbname=wordpress \
    --dbuser=root \
    --dbpass='' \
    --skip-check \
    --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );
// Surface every notice. A theme that is noisy locally is quiet in production.
@ini_set( 'display_errors', '1' );
PHP

  echo "── installing the SQLite database drop-in"
  curl -sL -o /tmp/sqlite-plugin.zip \
    https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
  unzip -qo /tmp/sqlite-plugin.zip -d wp-content/plugins/
  cp wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php
  # Point the drop-in at our paths.
  sed -i "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|$WP/wp-content/plugins/sqlite-database-integration|g" wp-content/db.php
  sed -i "s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|g" wp-content/db.php
  rm -f /tmp/sqlite-plugin.zip

  echo "── installing WordPress"
  wp_cli core install \
    --url="http://localhost:$PORT" \
    --title="Digest — local test" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=raywinkelman@gmail.com \
    --skip-email

  echo "── linking the theme (a symlink, so edits are live)"
  ln -sfn "$THEME_SRC" "$THEME_DST"
  wp_cli theme activate broadside

  echo "── linking and activating the blocks plugin"
  # Without this the theme renders, returns 200, and is WRONG: no nameplate, no
  # folio, no byline, no editorial blocks. WordPress does not error on a block it
  # cannot find — it silently omits it. A liveness check would pass on the wreck.
  ln -sfn "$PLUGIN_SRC" "$PLUGIN_DST"
  wp_cli plugin activate broadside-blocks

  echo "── seeding content that EXERCISES THE BUG"
  # The article page is the one that brought the server down. It must exist here,
  # with the table-of-contents block in it, or this sandbox proves nothing.
  wp_cli eval-file "$ROOT/scripts/local-seed.php"

  echo "── seeding a full front page (six sections, three posts each)"
  # Without this the sandbox front page has one category and four posts against
  # a live site's six of each — a layout can only be judged against the content
  # it was designed for.
  wp_cli eval-file "$ROOT/scripts/local-seed-front.php"

  echo "── attaching a featured image to every post"
  # post-featured-image is wired into templates/single.html and the front-page
  # lead grid; with nothing to show it, no audit here ever exercises responsive
  # image markup, srcset, or image-delivery performance — the same class of gap
  # that let the front page look "fine" while genuinely broken during the outage.
  wp_cli eval-file "$ROOT/scripts/local-seed-images.php"

  # WooCommerce, and a shop to look at.
  #
  # marksmansdigest.com runs WooCommerce in production, and the theme styles every
  # page it adds (see §13 of digest.css). Without a shop in the sandbox, none of
  # that CSS is ever executed before it ships — which is the exact condition that
  # produced the outage this file exists to prevent. It also caught a REAL bug the
  # moment it was added: Woo's block templates ask for a template part called
  # `header`, this theme's is called `masthead`, and every cart and checkout page on
  # the live site was rendering with NO masthead at all.
  #
  # Opt out with SKIP_WOO=1 if you only care about the newspaper.
  if [ "${SKIP_WOO:-0}" != "1" ]; then
    echo "── installing WooCommerce (SKIP_WOO=1 to skip)"
    wp_cli plugin install woocommerce --activate 2>&1 | grep -E 'Plugin installed|activated' || true

    echo "── creating the shop pages"
    wp_cli wc --user=admin tool run install_pages >/dev/null 2>&1 || true

    echo "── seeding a shop (7 products: a sale, an out-of-stock, an imageless, a variable)"
    wp_cli eval-file "$ROOT/scripts/local-seed-woo.php"
  fi

  echo "── setting permalinks and flushing rewrite rules"
  # Deliberately last, after every post/term the seed scripts create. Flushing
  # earlier — this used to happen inside local-seed.php, before
  # local-seed-front.php's terms and posts existed — produced a rewrite_rules
  # option with NO flat /%postname%/ rule for posts at all: every single article
  # 404'd, silently, with the homepage still rendering "fine". That is the same
  # shape of failure as the outage this sandbox exists to catch (a page that
  # looks fine hiding one that is completely broken), just triggered by rewrite
  # rules instead of a render callback.
  wp_cli option update permalink_structure '/%postname%/'
  wp_cli rewrite flush --hard

  echo
  echo "── starting the server on http://localhost:$PORT"
  echo "   (max_execution_time=10 — a recursion dies in 10s, not forever)"
  # NOTE: the router is scripts/local-router.php, NOT WordPress's index.php.
  # Passing index.php here routes *every* request through WordPress, including
  # ones for real files — so no CSS, JS or font ever loads and every page in the
  # sandbox renders unstyled. See the header of local-router.php.
  php "${PHP_FLAGS[@]}" -S "localhost:$PORT" -t "$WP" "$ROOT/scripts/local-router.php" \
    > "$ROOT/.local-wp.log" 2>&1 &
  echo "   pid $! — logging to .local-wp.log"

  sleep 2
  echo
  echo "── ready:"
  echo "     site:  http://localhost:$PORT/"
  echo "     admin: http://localhost:$PORT/wp-admin/  (admin / admin)"
}

case "${1:-up}" in
  up)    cmd_up ;;
  down)  cmd_down ;;
  reset) cmd_down; cmd_up ;;
  *)     echo "usage: $0 {up|down|reset}"; exit 1 ;;
esac
