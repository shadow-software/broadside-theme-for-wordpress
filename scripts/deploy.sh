#!/usr/bin/env bash
#
# Deploy the Digest theme to ONE site, safely.
#
# This script exists because the previous deploy procedure was `rsync`, run by
# hand, straight to two live sites, with a theme that had never been executed
# anywhere. It contained an infinite recursion and it took down a shared VPS —
# and with it a live firearms marketplace that had nothing to do with this theme.
# See docs/INCIDENT-2026-07-13-vps-outage.md.
#
# So this is not a convenience wrapper. It is a gate. It refuses to deploy unless:
#
#   1. The theme lints (PHP + JSON).
#   2. There is no live do_blocks() call in a render callback — the exact shape of
#      the bug that caused the outage.
#   3. The local sandbox is running.
#   4. local-smoke.sh passes    — every template renders, fast, no fatals.
#   5. local-assert.php passes  — the pages are CORRECT, not merely alive.
#      (Liveness alone is not enough: the re-entrancy guard makes the recursion
#      render fast-but-wrong instead of hanging, so a smoke test cannot see it.)
#
# It deploys to ONE site. Deploying to two sites at once is how one bad theme
# becomes two broken sites, and the incident is why that is no longer possible in
# a single command.
#
# After uploading it verifies the live site — including an article page, which is
# the page class that broke last time and which was never checked before.
#
# Usage:
#   ./scripts/deploy.sh cannabisdigest.net
#   ./scripts/deploy.sh marksmansdigest.com
#   ./scripts/deploy.sh <site> --rollback     # revert that site to the stock theme

set -euo pipefail

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEY=/home/shadow/Source/000-creds/1984_ssh_key
HOST=root@93.95.229.147
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=15"

SITE="${1:-}"
MODE="${2:-deploy}"

if [ -z "$SITE" ]; then
  echo "usage: $0 <cannabisdigest.net|marksmansdigest.com> [--rollback]"
  exit 1
fi

case "$SITE" in
  cannabisdigest.net|marksmansdigest.com) ;;
  *) echo "!! unknown site: $SITE"; exit 1 ;;
esac

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\032[32m%s\033[0m\n' "$*" 2>/dev/null || printf '%s\n' "$*"; }

# ---------------------------------------------------------------- rollback ----

if [ "$MODE" = "--rollback" ]; then
  echo "── ROLLBACK: switching $SITE to the stock theme"
  $SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate twentytwentyfive --allow-root"
  echo "── verifying"
  curl -s -o /dev/null -w "   %{http_code} in %{time_total}s\n" --max-time 20 "https://$SITE/"
  echo "── rolled back."
  exit 0
fi

# ------------------------------------------------------------------- gates ----

echo "════ GATE 1/5 — lint"
fail=0
while IFS= read -r f; do
  php -l "$f" >/dev/null 2>&1 || { red "   PHP syntax error: $f"; fail=1; }
done < <(find "$ROOT/broadside" "$ROOT/broadside-blocks" -name '*.php')
while IFS= read -r f; do
  python3 -c "import json,sys;json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { red "   bad JSON: $f"; fail=1; }
done < <(find "$ROOT/broadside" "$ROOT/broadside-blocks" -name '*.json')
[ "$fail" -eq 0 ] || { red "✗ lint failed — not deploying"; exit 1; }
echo "   ✓ clean"

echo
echo "════ GATE 2/5 — nothing renders post content (THE outage bug)"
# This was a grep. The grep was wrong — see the header of the guard script — and
# it could not reliably tell a live do_blocks() call from a comment about one.
# It now tokenizes the PHP, and CI runs the identical script, so the gate that
# blesses a deploy and the gate that blesses a commit cannot drift apart.
if ! php "$ROOT/scripts/guard-no-content-render.php" "$ROOT/broadside"; then
  red "✗ Not deploying. Read docs/INCIDENT-2026-07-13-vps-outage.md."
  exit 1
fi

echo
echo "════ GATE 3/5 — local sandbox is up"
if ! curl -sf -o /dev/null --max-time 5 http://localhost:8080/ 2>/dev/null; then
  red "✗ the local sandbox is not running."
  echo "  Start it:  ./scripts/local-wp.sh up"
  echo "  Nothing deploys that has not rendered locally first. That rule is the"
  echo "  entire lesson of the incident."
  exit 1
fi
echo "   ✓ running on :8080"

echo
echo "════ GATE 4/5 — every template renders (liveness)"
"$ROOT/scripts/local-smoke.sh" >/tmp/broadside-smoke.log 2>&1 || {
  red "✗ smoke test failed:"
  tail -20 /tmp/broadside-smoke.log
  exit 1
}
echo "   ✓ all templates render"

echo
echo "════ GATE 5/5 — the pages are CORRECT (not merely alive)"
php "$ROOT/scripts/local-assert.php" >/tmp/broadside-assert.log 2>&1 || {
  red "✗ correctness assertions failed:"
  grep -E "✗|FAILED" /tmp/broadside-assert.log | head -10
  echo
  red "  A page can return 200 in 100ms and still be wrong. The re-entrancy"
  red "  guard makes the recursion render fast-but-wrong rather than hang, which"
  red "  is precisely why liveness checks alone cannot be trusted here."
  exit 1
}
echo "   ✓ $(grep -oE '[0-9]+ assertions passed' /tmp/broadside-assert.log)"

# ------------------------------------------------------------------ deploy ----

echo
echo "════ DEPLOYING to $SITE (one site only)"

# The theme is HALF the deploy. Since 1.2.0 every block the templates call —
# nameplate, folio, utility-bar, byline, toc, takeaways, related, newsletter,
# colophon — lives in the broadside-blocks PLUGIN, because .org forbids a theme
# registering blocks. Shipping the theme alone leaves WordPress with template
# parts full of <!-- wp:broadside/* --> comments it cannot resolve, and an
# unregistered block renders as NOTHING — silently, with a 200 and no error.
# That is precisely how both live sites lost their mastheads. Ship both, always,
# and put the plugin down FIRST so the theme never activates without its blocks.
rsync -az --delete -e "$SSH" "$ROOT/broadside-blocks/" "$HOST:/var/www/$SITE/public/wp-content/plugins/broadside-blocks/"
$SSH "$HOST" "chown -R nobody:nogroup /var/www/$SITE/public/wp-content/plugins/broadside-blocks"
$SSH "$HOST" "cd /var/www/$SITE/public && wp plugin activate broadside-blocks --allow-root" >/dev/null 2>&1
echo "   ✓ plugin broadside-blocks uploaded + activated"

rsync -az --delete -e "$SSH" "$ROOT/broadside/" "$HOST:/var/www/$SITE/public/wp-content/themes/broadside/"
$SSH "$HOST" "chown -R nobody:nogroup /var/www/$SITE/public/wp-content/themes/broadside"
echo "   ✓ theme uploaded"

$SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate broadside --allow-root" >/dev/null
echo "   ✓ theme activated"

# A block that is registered renders; a block that is merely PRESENT on disk but
# not registered does not. Ask WordPress what it actually knows about, rather
# than trusting that the files landed.
# PHP 8.5 + Elementor flood wp-cli stdout with deprecation notices (WP error
# handler), so a bare echo of missing names gets drowned. Use a sentinel line
# and parse only that — never treat notice text as a registration failure.
MISSING=$($SSH "$HOST" "cd /var/www/$SITE/public && wp eval '\$m=[]; foreach ([\"nameplate\",\"folio\",\"utility-bar\",\"byline\",\"toc\"] as \$b) { if ( ! WP_Block_Type_Registry::get_instance()->is_registered(\"broadside/\$b\") ) \$m[]=\$b; } echo \"BROADSIDE_BLOCKS:\".implode(\",\",\$m).\":END\n\";' --allow-root 2>&1" \
  | tr -d '\r' \
  | sed -n 's/.*BROADSIDE_BLOCKS:\([^:]*\):END.*/\1/p' \
  | head -1 \
  | tr ',' ' ' \
  | xargs)
if [ -n "${MISSING}" ]; then
  red "✗ blocks NOT registered after activation: $MISSING"
  red "  The theme would render a page with no masthead. Refusing to leave it live."
  $SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate twentytwentyfive --allow-root" >/dev/null
  exit 1
fi
echo "   ✓ all masthead blocks registered"

# ------------------------------------------------------------------ verify ----

echo
echo "════ VERIFYING the live site"

# The article page is the one that broke last time and the one that was never
# checked. It is checked first, and with a hard timeout: if it hangs, we know
# within seconds and we roll back rather than leaving it to eat the box.
ARTICLE=$($SSH "$HOST" "cd /var/www/$SITE/public && wp post list --post_type=post --posts_per_page=1 --field=url --allow-root 2>/dev/null" | head -1)

# $3 = the CSS class that proves this page type's masthead furniture rendered.
# The front page uses parts/masthead.html   → broadside/nameplate → .digest-masthead
#                                            + broadside/folio    → .digest-folio
# An ARTICLE uses parts/masthead-compact.html, which is core site-title + nav and
# has NO nameplate and NO folio block by design. Asserting folio on an article is
# a false failure — it is not what that template renders.
probe() {
  local url="$1" label="$2" witness="$3"
  local start end ms code body
  body=/tmp/broadside-live-$$.html
  start=$(date +%s%N)
  code=$(curl -s -o "$body" -w "%{http_code}" --max-time 20 "$url" 2>/dev/null || echo 000)
  end=$(date +%s%N)
  ms=$(( (end - start) / 1000000 ))

  printf "   %-14s %s  %5dms  " "$label" "$code" "$ms"

  if [ "$ms" -gt 10000 ] || [ "$code" = "000" ] || [ "$code" -ge 500 ]; then
    red "✗ FAILING (status/latency)"
    return 1
  fi

  # A page whose blocks all failed to register returns 200 in 300ms and looks
  # perfectly healthy to a status-and-stopwatch check. It is not healthy: it has
  # no masthead. Liveness is not correctness — the incident doc says so, and this
  # gate ignored it. So look at what came back.
  #
  # Grep for the CLASS ATTRIBUTE, not the bare string: these class names also
  # appear in the inlined theme CSS on every page, so a bare `grep digest-folio`
  # passes even on a page with an empty <header>. That false pass is exactly the
  # trap this gate exists to catch — it is how a headless site looked healthy.
  if ! grep -qE "class=\"[^\"]*$witness" "$body"; then
    red "✗ FAILING — '$witness' absent: this page's masthead did not render"
    red "  Almost certainly broadside-blocks is missing or inactive on $SITE."
    rm -f "$body"
    return 1
  fi

  # Whatever the page, an unregistered dynamic block collapses to nothing. The
  # colophon (which emits .digest-footer) is in parts/footer.html and therefore on
  # EVERY template, so it is a page-agnostic witness that the plugin is really
  # live and not merely present on disk.
  if ! grep -qE 'class="[^"]*digest-footer' "$body"; then
    red "✗ FAILING — no colophon: broadside-blocks is not rendering"
    rm -f "$body"
    return 1
  fi

  rm -f "$body"
  echo "✓"
  return 0
}

ok=0
# Front page: full masthead (nameplate block) → .digest-masthead
# Article:    masthead-compact → core site-title with .digest-nameplate--compact.
#             It has no nameplate and no folio block; asserting those here would
#             be a false failure against a page that is perfectly correct.
probe "https://$SITE/" "front page" 'digest-masthead' || ok=1
[ -n "$ARTICLE" ] && { probe "$ARTICLE" "ARTICLE" 'digest-nameplate--compact' || ok=1; }

if [ "$ok" -ne 0 ]; then
  echo
  red "════ LIVE SITE IS UNHEALTHY — ROLLING BACK IMMEDIATELY"
  $SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate twentytwentyfive --allow-root" >/dev/null
  red "   reverted $SITE to the stock theme."
  red "   Do NOT retry the request. Read the code."
  exit 1
fi

echo
echo "════ ✓ $SITE deployed and healthy."
echo "   The other site is untouched. Deploy it separately, once you trust this one."
