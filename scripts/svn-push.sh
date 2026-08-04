#!/usr/bin/env bash
# Push a packaged theme version to https://themes.svn.wordpress.org/broadside/
#
# Usage:
#   SVN_USERNAME=shadowsoftware SVN_PASSWORD=*** ./scripts/svn-push.sh 1.3.4
#
# Requires: svn, unzip. Builds via package.sh if build/broadside-<ver>.zip is missing
# and the working tree Version matches, or pass path to zip as 2nd arg.

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VER="${1:?version required, e.g. 1.3.4}"
ZIP="${2:-$ROOT/build/broadside-${VER}.zip}"
USER="${SVN_USERNAME:?set SVN_USERNAME}"
PASS="${SVN_PASSWORD:?set SVN_PASSWORD}"
SLUG=broadside
SVN_URL="https://themes.svn.wordpress.org/${SLUG}"
WC="$(mktemp -d)"
trap 'rm -rf "$WC"' EXIT

if [ ! -f "$ZIP" ]; then
	echo "missing $ZIP — build with: git checkout v${VER} && ./scripts/package.sh" >&2
	exit 1
fi

echo "════ SVN push ${SLUG}/${VER}"
svn checkout --depth=immediates --username "$USER" --password "$PASS" --non-interactive "$SVN_URL" "$WC"
if [ -d "$WC/$VER" ]; then
	echo "ERROR: ${VER}/ already on SVN" >&2
	exit 1
fi
mkdir "$WC/$VER"
STAGE="$(mktemp -d)"
unzip -q "$ZIP" -d "$STAGE"
# zip top-level is the slug directory
rsync -a "$STAGE/${SLUG}/" "$WC/$VER/"
svn add "$WC/$VER"
svn commit -m "Broadside ${VER} — Theme Directory review: remove accessibility-ready and GitHub Theme URI" \
	--username "$USER" --password "$PASS" --non-interactive "$WC"
echo "════ done → ${SVN_URL}/${VER}/"
