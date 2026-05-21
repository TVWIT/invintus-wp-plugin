#!/usr/bin/env bash
# Scorched-earth reset of the local wp-env environment.
#
# Tears down wp-env (containers + volumes + db), removes build
# artifacts, rebuilds the dist candidate, starts wp-env from scratch,
# and applies the one-time post-start bootstrap (permalink structure +
# .htaccess) so REST routes resolve and the Settings page can save.
#
# Use this when you want to verify the "fresh clone" recipe end-to-end
# or when you suspect env state is the cause of a bug.
#
# WARNING: destroys the local wp-env database. Anything created in
# wp-admin (posts, settings, users) will be gone.
#
# Does NOT touch node_modules. If you also want a fresh npm install,
# delete node_modules manually before running this.
#
# Usage:
#   bash scripts/reset-env.sh
#   npm run env:reset

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> destroying wp-env (containers + volumes + db)"
echo y | npm run env:destroy

echo "==> removing build artifacts (build/, vendor/, .dist-candidate/)"
rm -rf build/ vendor/ .dist-candidate/

echo "==> rebuilding dist candidate"
npm run dist:build

echo "==> starting wp-env"
npm run env:start

# Docker Desktop on macOS can lose bind-mount visibility when the host
# path is removed and recreated (which is exactly what we just did to
# .dist-candidate). The container ends up seeing an empty plugin dir
# even though the host path is populated. Cycle stop+start to force
# Docker to re-resolve the mount.
echo "==> stop+start cycle to re-resolve bind-mount"
npx wp-env stop
npx wp-env start

echo "==> post-start bootstrap: permalink structure"
npx wp-env run cli -- wp rewrite structure '/%postname%' --hard

echo "==> post-start bootstrap: .htaccess"
npx wp-env run cli -- bash -c 'cat > /var/www/html/.htaccess <<EOF
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\\.php\$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF'

echo
echo "==> ready"
echo "    http://localhost:8888/wp-admin   (admin / password)"
echo "    plugin mount: $(grep -oE '"plugins": \[[^]]+\]' .wp-env.override.json 2>/dev/null || grep -oE '"plugins": \[[^]]+\]' .wp-env.json)"
echo "    mu-plugins:   $(ls .local-mu-plugins/ 2>/dev/null | grep -v '\.off$' | tr '\n' ' ' || echo '(none)')"
