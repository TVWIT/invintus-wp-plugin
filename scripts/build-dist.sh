#!/usr/bin/env bash
# Builds a DIST candidate that matches what CI publishes to
# TVWIT/invintus-wp-plugin-dist. Use this to test the plugin the way a
# real client would receive it -- compiled assets + prod composer deps,
# none of the dev tooling.
#
# Output: .dist-candidate/invintus/   (mountable as a wp-env plugin)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

INCLUDE=(build assets inc vendor templates views invintus.php LICENSE doc.md)

echo "==> npm run build (compiles src/ into build/)"
npm run build

echo "==> composer install --no-dev (via dockerized composer; no host PHP needed)"
docker run --rm \
  -v "$ROOT:/app" \
  -w /app \
  composer:2 install --no-dev --optimize-autoloader --no-interaction

echo "==> Assembling .dist-candidate/invintus/"
rm -rf .dist-candidate
mkdir -p .dist-candidate/invintus
for f in "${INCLUDE[@]}"; do
  if [[ -e "$f" ]]; then
    cp -R "$f" ".dist-candidate/invintus/"
  else
    echo "    WARNING: $f not found, skipping"
  fi
done

echo
echo "Dist candidate ready at: .dist-candidate/invintus"
echo "Point wp-env at it via .wp-env.override.json (already set up)."
