#!/usr/bin/env bash
#
# Smoke-test every template against the local sandbox.
#
# This is the test that would have prevented the 2026-07-13 outage. Run it before
# any deploy. It is not thorough — it is a tripwire. It asks one question of every
# template: does this page render, quickly, without a fatal?
#
# The timing matters as much as the status code. The recursion that killed the
# production server returned no error at all; it simply never finished. A page
# that takes more than a couple of seconds here is guilty until proven innocent.

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

BASE="http://localhost:${BROADSIDE_LOCAL_PORT:-8080}"
LOG="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.local-wp.log"
FAIL=0

# path | what it exercises
ROUTES=(
  "/|front page — the 3-column lead grid"
  "/the-thousand-yard-question/|ARTICLE — the page that killed the VPS"
  "/category/features/|category archive"
  "/?s=mirage|search results"
  "/author/e-vance/|author archive"
  "/definitely-not-a-real-page/|404"
  "/feed/|RSS feed"
  "/wp-json/wp/v2/posts|REST API"
)

printf "%-46s %-6s %8s %10s\n" "ROUTE" "HTTP" "TIME" "SIZE"
printf "%s\n" "────────────────────────────────────────────────────────────────────────────"

for entry in "${ROUTES[@]}"; do
  path="${entry%%|*}"
  desc="${entry##*|}"

  start=$(date +%s%N)
  code=$(curl -s -o /tmp/broadside-smoke.html -w "%{http_code}" --max-time 20 "$BASE$path" 2>/dev/null)
  end=$(date +%s%N)
  ms=$(( (end - start) / 1000000 ))
  size=$(wc -c < /tmp/broadside-smoke.html 2>/dev/null || echo 0)

  status="ok"

  # A slow page is the signature of the bug that caused the outage. Treat it as
  # a failure, not a curiosity.
  if [ "$ms" -gt 5000 ]; then
    status="SLOW — possible recursion"
    FAIL=1
  fi

  # 404 is the correct answer for the 404 route; everything else must be 2xx/3xx.
  case "$path" in
    /definitely-not-a-real-page/)
      [ "$code" = "404" ] || { status="expected 404, got $code"; FAIL=1; }
      ;;
    *)
      case "$code" in
        2*|3*) ;;
        *) status="HTTP $code"; FAIL=1 ;;
      esac
      ;;
  esac

  # A page that renders a PHP error is a failure even if it returns 200.
  if grep -qiE "fatal error|allowed memory size|maximum execution time" /tmp/broadside-smoke.html 2>/dev/null; then
    status="PHP FATAL IN OUTPUT"
    FAIL=1
  fi

  mark="✓"
  [ "$status" != "ok" ] && mark="✗"

  printf "%-46s %-6s %6dms %9d  %s %s\n" "$path" "$code" "$ms" "$size" "$mark" \
    "$([ "$status" = "ok" ] && echo "$desc" || echo "$status")"
done

echo
echo "── fatals in the server log?"
if grep -iqE "fatal error|allowed memory size|maximum execution time|nesting level" "$LOG" 2>/dev/null; then
  echo "   ✗ FOUND:"
  grep -iE "fatal error|allowed memory size|maximum execution time|nesting level" "$LOG" | tail -5 | sed 's/^/     /'
  FAIL=1
else
  echo "   ✓ none"
fi

echo
if [ "$FAIL" -eq 0 ]; then
  echo "✓ ALL TEMPLATES PASS — safe to consider deploying"
  exit 0
else
  echo "✗ FAILURES ABOVE — do NOT deploy"
  exit 1
fi
