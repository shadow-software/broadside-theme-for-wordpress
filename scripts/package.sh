#!/usr/bin/env bash
#
# Build the .zip that gets uploaded to the WordPress.org Theme Directory — and
# refuse to build one that would be rejected.
#
# This exists for the same reason deploy.sh exists. The last time something left
# this repo for a live server it went by hand, and it took a production box down.
# A theme submission is lower stakes than a deploy, but it is the same failure
# shape: a human assembling an artifact by hand, from memory, and shipping it
# somewhere they cannot easily take it back from. A rejected submission costs a
# week of queue time, and the reviewer tells you one problem at a time.
#
# So the zip is built, then INSPECTED — the checks below are the ones the Theme
# Review team actually runs, applied to the exact bytes that would be uploaded,
# not to the working tree they were copied from.
#
# Usage:
#   ./scripts/package.sh              build + verify → build/<slug>-<version>.zip
#   ./scripts/package.sh --check      verify only, build nothing (CI uses this)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="broadside"
SRC="$ROOT/$SLUG"
OUT="$ROOT/build"

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
grn()  { printf '\033[32m%s\033[0m\n' "$*"; }

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

VERSION=$(grep -oP '^Version:\s*\K[0-9.]+' "$SRC/style.css")
[ -n "$VERSION" ] || { red "cannot read Version from style.css"; exit 1; }

echo "════ packaging $SLUG $VERSION"

# ---------------------------------------------------------------- staging ----
#
# Copy to a staging dir and delete what must not ship, rather than trusting a
# .distignore that only some tools read. The Theme Directory rejects a package
# carrying build config, tests or dev dotfiles, and the two dotfiles that used to
# live INSIDE the theme directory (.distignore, .stylelintrc.json) were shipping
# in every hand-made zip, because .distignore's own rules are anchored to the repo
# root and could never match them.

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
DIR="$STAGE/$SLUG"

cp -r "$SRC" "$DIR"

# Dev tooling and repo meta. None of this runs on a user's site.
find "$DIR" \( \
	-name '.distignore'      -o -name '.stylelintrc.json' -o \
	-name '.editorconfig'    -o -name '.gitignore'        -o \
	-name '.gitattributes'   -o -name 'composer.json'     -o \
	-name 'composer.lock'    -o -name 'package.json'      -o \
	-name 'package-lock.json' -o -name 'phpcs.xml.dist'   -o \
	-name 'phpunit.xml.dist' -o -name '.DS_Store'         -o \
	-name 'Thumbs.db'        -o -name '*.log'             -o \
	-name '*.zip'            -o -name '*.map' \
	\) -delete

find "$DIR" \( -name 'node_modules' -o -name 'vendor' -o -name '.git' \
	-o -name 'tests' -o -name '.phpunit.cache' \) -type d -prune -exec rm -rf {} +

# ------------------------------------------------------------------ checks ----
#
# Applied to the STAGED tree — the actual bytes that would be uploaded.

fail=0
note() { red "   ✗ $*"; fail=1; }
okay() { echo "   ✓ $*"; }

echo
echo "── required files"
for f in style.css index.php readme.txt screenshot.png LICENSE theme.json; do
	[ -f "$DIR/$f" ] && okay "$f" || note "missing required file: $f"
done

echo
echo "── no dev tooling survived into the package"
strays=$(find "$DIR" -name '.*' -not -name '.' -not -path "$DIR" 2>/dev/null || true)
if [ -n "$strays" ]; then
	while IFS= read -r s; do note "dotfile in package: ${s#"$DIR"/}"; done <<< "$strays"
else
	okay "no dotfiles"
fi

for bad in node_modules vendor composer.json package.json phpcs.xml.dist tests; do
	[ -e "$DIR/$bad" ] && note "dev artefact in package: $bad" || true
done
okay "no build config, no dependencies, no tests"

echo
echo "── no minified or third-party code (the Directory requires readable source)"
if find "$DIR" \( -name '*.min.js' -o -name '*.min.css' \) | grep -q .; then
	note "minified assets present"
else
	okay "unminified source only"
fi

echo
echo "── version is in lockstep"
v_style=$(grep -oP '^Version:\s*\K[0-9.]+'        "$DIR/style.css")
v_func=$(grep -oP  "DIGEST_VERSION', '\K[0-9.]+"  "$DIR/functions.php")
v_read=$(grep -oP  '^Stable tag:\s*\K[0-9.]+'     "$DIR/readme.txt")
if [ "$v_style" = "$v_func" ] && [ "$v_style" = "$v_read" ]; then
	okay "style.css = functions.php = readme.txt = $v_style"
else
	note "version mismatch: style.css=$v_style functions.php=$v_func readme.txt=$v_read"
fi

echo
echo "── screenshot is exactly 1200x900"
dims=$(file "$DIR/screenshot.png" | grep -oE '[0-9]+ x [0-9]+' | head -1)
[ "$dims" = "1200 x 900" ] && okay "screenshot.png $dims" || note "screenshot.png must be 1200x900, got $dims"

echo
echo "── licence declared"
# "GNU General Public License v2 or later" and "GPLv2 or later" are both valid and
# both common. Matching only the literal "GPL" rejected the former — which is what
# this theme actually says. A check that fails on correct input is how you end up
# editing correct code to satisfy a broken test.
grep -qiE 'License:.*(GPL|GNU General Public License)' "$DIR/style.css" \
	&& okay "style.css declares GPL" || note "style.css must declare a GPL licence"
grep -q '^License: *GPLv2'  "$DIR/readme.txt" && okay "readme.txt declares GPLv2" || note "readme.txt must declare GPLv2"
grep -q 'SIL Open Font'     "$DIR/readme.txt" && okay "bundled fonts attributed"  || note "readme.txt must attribute the bundled OFL fonts"
[ -f "$DIR/assets/fonts/LICENSE-OFL.txt" ] && okay "OFL licence text ships" || note "assets/fonts/LICENSE-OFL.txt is missing"

echo
echo "── nothing renders post content (the outage guard, on the packaged bytes)"
if php "$ROOT/scripts/guard-no-content-render.php" "$DIR" >/dev/null 2>&1; then
	okay "no content-rendering call"
else
	note "a live content-rendering call is in the package — see docs/INCIDENT-2026-07-13-vps-outage.md"
fi

echo
echo "── the theme does not do plugin things (this is what got it rejected)"
if "$ROOT/scripts/guard-no-plugin-territory.sh" "$DIR" >/dev/null 2>&1; then
	okay "no register_block_type(), no custom post types, no shortcodes"
else
	note "plugin-territory code is in the package — the blocks belong in broadside-blocks/"
fi

echo
echo "── text domain matches the directory name (WordPress requires this)"
if grep -q "Text Domain: *$SLUG" "$DIR/style.css"; then
	okay "text domain = $SLUG"
else
	note "style.css Text Domain must equal the directory name: $SLUG"
fi

# ---------------------------------------------------------------- review ----
#
# 2026-08 Theme Directory bounce (reviewer @bijayyadav):
#   - accessibility-ready queues a *separate* a11y review — do not claim it
#     unless we deliberately want that longer queue.
#   - Theme URI, if present, must be about the theme ON WordPress.org — not a
#     GitHub repo, product marketing page, or author homepage.
# House rule: never ship accessibility-ready. Omit Theme URI until the theme is
# live on wordpress.org/themes/<slug>/, then set Theme URI to that URL only.

echo
echo "── no accessibility-ready tag (separate a11y queue — house ban)"
# Only the Tags: line is machine-read by the Directory. Prose that *disclaims*
# the tag (as our readme does) must not trip this gate.
tag_hits=$(grep -nE '^Tags:.*accessibility-ready' "$DIR/style.css" "$DIR/readme.txt" 2>/dev/null || true)
if [ -n "$tag_hits" ]; then
	note "accessibility-ready on Tags: line — remove it (queues separate a11y review)"
	printf '%s\n' "$tag_hits" | sed 's/^/      /'
else
	okay "no accessibility-ready tag"
fi

echo
echo "── Theme URI is omitted or points at wordpress.org/themes/ only"
if theme_uri=$(grep -oP '^Theme URI:\s*\K\S+' "$DIR/style.css" 2>/dev/null); then
	if printf '%s' "$theme_uri" | grep -qiE '^https?://(www\.)?wordpress\.org/themes/'; then
		okay "Theme URI is the directory page: $theme_uri"
	else
		note "Theme URI must be about this theme on WordPress.org (got: $theme_uri) — omit it, or use https://wordpress.org/themes/$SLUG/"
	fi
else
	okay "Theme URI omitted (valid until the theme is live on the Directory)"
fi

# ---------------------------------------------------------------- naming ----
#
# The first submission was rejected on the name:
#
#   "There is already a theme called digest by a different author. Please change
#    the name of your theme in style.css and upload it again."
#
# and the handbook adds a rule the reviewer did not have to mention, because the
# old slug broke it too — theme names "must not use: WordPress, Theme, Twenty*",
# and the slug IS the name, lowercased and hyphenated. The old
# `shadow-software-digest-theme-for-wordpress` contained BOTH forbidden words.
#
# So the name is now checked mechanically, here, against the bytes being shipped.
# https://make.wordpress.org/themes/handbook/review/required/

echo
echo "── the theme name is legal and not already taken"

NAME=$(grep -oP '^Theme Name:\s*\K.*' "$DIR/style.css" | tr -d '\r')
if [ -z "$NAME" ]; then
	note "style.css has no Theme Name"
else
	okay "Theme Name: $NAME"

	# Forbidden words. Matched case-insensitively as whole words, so "Broadside"
	# is fine and "Press Theme" is not.
	for word in wordpress theme; do
		if printf '%s' "$NAME" | grep -qiE "(^|[^a-z])${word}([^a-z]|$)"; then
			note "theme name must not contain \"${word}\" — handbook, Naming"
		fi
	done
	printf '%s' "$NAME" | grep -qiE '(^|[^a-z])twenty' \
		&& note 'theme name must not begin with "Twenty" — reserved for core'

	# The slug the Directory will publish under is derived FROM the name.
	want=$(printf '%s' "$NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
	if [ "$want" = "$SLUG" ]; then
		okay "slug derived from the name matches the directory: $SLUG"
	else
		note "directory is '$SLUG' but the name '$NAME' derives the slug '$want' — they must agree"
	fi
fi

# Is the slug already taken on wordpress.org? A 200 means someone owns it, and
# shipping under it is worse than a rejection: WordPress matches update payloads
# by slug, so a site running this theme could be auto-updated to a stranger's.
# Skipped without network (CI has one; an offline developer does not).
if curl -s -o /dev/null --max-time 8 "https://wordpress.org/themes/$SLUG/" 2>/dev/null; then
	code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "https://wordpress.org/themes/$SLUG/" || echo 000)
	case "$code" in
		404) okay "wordpress.org/themes/$SLUG/ is free (404)" ;;
		200) note "wordpress.org/themes/$SLUG/ ALREADY EXISTS — pick another name" ;;
		*)   echo "   · could not check the Directory (HTTP $code) — verify by hand" ;;
	esac
else
	echo "   · no network; slug availability not checked"
fi

# A 404 on wordpress.org is NECESSARY BUT NOT SUFFICIENT, and this check learned
# that the hard way. "Pressroom" 404s on the Directory — and is also an established
# commercial news theme by QuanticaLabs, sold on ThemeForest. The .org check passed
# it happily. The scanner did not:
#
#     ERROR: "Pressroom" currently has 500+ active installations. Please check for
#            name collisions outside of WordPress.org before approval.
#
# A script cannot do that search — it needs the open web, and a judgement call about
# whether a hit is really a WordPress theme. So this does the honest thing and says
# what it cannot verify, rather than printing a green tick that means less than it
# looks like. A check that quietly under-tests is worse than no check: it buys
# confidence it has not earned.
echo "   · NOTE: a .org 404 does not prove the name is free. \"Pressroom\" was a"
echo "     404 here AND a 500+ install commercial theme. Search the open web for"
echo "     \"$NAME WordPress theme\" before you submit."

echo
if [ "$fail" -ne 0 ]; then
	red "════ NOT SHIPPABLE — fix the above before submitting."
	exit 1
fi
grn "════ package is clean"

[ "$CHECK_ONLY" -eq 1 ] && { echo "   (--check: no zip written)"; exit 0; }

# ------------------------------------------------------------------- write ----

mkdir -p "$OUT"
ZIP="$OUT/$SLUG-$VERSION.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr "$ZIP" "$SLUG" -x '*.DS_Store' )

echo
echo "════ $ZIP"
echo "   $(du -h "$ZIP" | cut -f1)  ·  $(unzip -l "$ZIP" | tail -1 | awk '{print $2}') files"
echo
echo "   Upload at https://wordpress.org/themes/upload/"
echo "   The zip's top-level directory is '$SLUG', which is the slug the"
echo "   Directory will publish under and the text domain the theme uses."
