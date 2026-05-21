---
name: invintus-wp-plugin
description: How to work in this repo -- two-repo layout, the real release process (via create-release.js, not the tag-based CI flow), local wp-env setup with its quirks, partner override pattern, and common pitfalls
---

# Working in the invintus-wp-plugin repo

## Two-repo layout (important, easy to miss)

There are two repos. Knowing which is which prevents hours of confusion:

| Repo | Purpose |
|---|---|
| `TVWIT/invintus-wp-plugin` (this one) | Source of truth. All development happens here. Full source, build config, CI, dev tooling. |
| `TVWIT/invintus-wp-plugin-dist` | What partner sites install. Build artifacts only -- compiled `build/`, vendored `vendor/`, runtime PHP. No source, no `package.json`, no CI. |

The dist repo is written by the release script. **Never hand-edit dist** -- the next release overwrites it. There's also a third repo `TVWIT/wp-plugin-invintus` which is a deprecated predecessor; ignore it.

## Release process (this is the part the README gets wrong)

The README documents a tag-based CI flow via `.github/workflows/main.yml`. That workflow exists and runs on `v*.*.*` tag pushes, but **historically releases have been driven by `create-release.js`** instead. The script does everything in one shot: version bump, build, dist push, tag both repos, GitHub releases.

The CI workflow is a fallback / safety net. Either path works; the team has used the script.

### Standard release sequence

```sh
# 1. Land changes on main via PR + merge
gh pr merge <N> --merge --repo TVWIT/invintus-wp-plugin

# 2. Pull main locally
git checkout main && git pull

# 3. Dry-run (no remote effect, just local version bump + build)
node create-release.js version=X.Y.Z

# 4. Inspect: invintus.php + package.json bumps look right? build/ populated?
git status
git diff invintus.php package.json

# 5. Real release: clones dist, copies, commits, tags BOTH repos, pushes, creates GH releases
node create-release.js version=X.Y.Z --push --create-release

# 6. Clean up local artifacts
git checkout invintus.php package.json   # leave source at "in-dev" version (existing convention)
rm -f invintus-wp-plugin.zip             # release artifact left in repo root
```

### Things to know about create-release.js

- **Requires `version=X.Y.Z`** as a CLI arg. `composer.json` has no `version` field, so without the arg the script errors out.
- **Mutates `invintus.php` + `package.json`** in place but does **not commit** them. After `--push`, your working tree has uncommitted bumps -- that's by design. The convention is to discard them so source stays at its "in-dev" version. The released version only lives in the dist repo.
- **Requires host composer.** If you only have dockerized composer, `brew install composer` first, or use `scripts/build-dist.sh` for testing-only builds (it uses dockerized composer but doesn't push anywhere).
- **Tags BOTH source repo and dist repo** at the same version. The source tag points at whatever commit you ran the script against; it does not contain the version bump (since it isn't committed).
- **Reusing a tag silently no-ops the tagging step.** The script checks `tagExists` and skips if so. If you need to re-tag, delete the existing tag first (`git tag -d vX.Y.Z` + `git push origin :refs/tags/vX.Y.Z`).

### History gotcha: orphan `v2.0.11`

`v2.0.11` was tagged on `main` in May 2025 without a real version bump (the commit just added `.distignore` / `CLAUDE.md` to `.gitignore`). The dist's `Release v2.0.11` commit doesn't correspond to a coherent source-side release. The version was skipped going from 2.0.10 -> 2.0.12. Don't reuse v2.0.11.

## Local development

Two ways to run the plugin locally:

### A. Source-tree dev (default)

Mounts the source repo as the plugin. Fast iteration; changes to `inc/*.php` are immediately live without rebuilding the dist tree.

```sh
npm install
npm run env:start
```

http://localhost:8888/wp-admin -- admin / password.

### B. Dist-candidate dev (test the way partners install)

Mounts `.dist-candidate/invintus/` (compiled `build/`, vendored `vendor/`, no dev tooling). Catches dist-only bugs.

```sh
npm run dist:build       # writes .dist-candidate/invintus/
# Add .wp-env.override.json with: {"plugins": ["./.dist-candidate/invintus"]}
npm run env:start
```

### Post-start bootstrap (every time after env:destroy)

wp-env doesn't write `.htaccess` and starts with Plain permalinks. Pretty REST URLs (`/wp-json/...`) return Apache 404s until both are fixed. PHP in the container can't write `.htaccess` because `/var/www/html` is owned by root, so saving Permalinks in wp-admin reports success but does nothing.

`scripts/reset-env.sh` (= `npm run env:reset`) handles this automatically. It also includes a stop+start cycle after the initial start, because Docker Desktop on macOS loses bind-mount visibility when the host path is deleted then recreated (which is what the script does to `.dist-candidate`).

If you manually destroy + start without using `env:reset`, the bootstrap commands are:

```sh
npx wp-env run cli -- wp rewrite structure '/%postname%' --hard
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
```

### wp-env pinning

`.wp-env.json` pins WP to 6.6.2 via the wordpress.org zip URL, **not** a `WordPress/WordPress#6.6` git ref. The git ref form pulls a `wordpress-develop` dev build with content-hashed paths under `wp-includes/js/dist/development/` and broken `wp.i18n` exposure. Always use the zip URL.

WP 7.0 will break the toolchain (`@wordpress/scripts ^26.16.0` targets WP 6.4-era externalized `wp.*` shape). When you bump `@wordpress/scripts`, also bump the `.wp-env.json` core URL to a newer WP zip.

## Partner override pattern

Partner sites point the player at a different URL via a must-use plugin at `wp-content/mu-plugins/invintus-player.php`:

```php
<?php
add_filter( 'invintus/player/script/url', function( $url ) {
    return 'https://example.com/path/to/your/app.js';
} );
```

For cross-origin player builds that use dynamic `import()` for chunks, add the companion filter (introduced in 2.0.12):

```php
add_filter( 'invintus/player/script/crossorigin', function() {
    return 'anonymous';
} );
```

This emits `crossorigin="anonymous"` on the player `<script>` tag so the browser doesn't strip the script's base URL when resolving relative chunk imports. The target URL must respond with `Access-Control-Allow-Origin: *` or matching.

The override can be route-scoped (`is_page()`, `is_singular()`, `$_SERVER['REQUEST_URI']`). See `doc.md` and `README.md` "Scoped overrides" sections for examples. Always accept `$url` as the first arg and return it as the fallback.

The plugin only enqueues the player on three contexts: Settings page, block editor, and front-end posts with the Invintus block. The filter does not run elsewhere.

## Code organization

PSR-4 autoload under `Taproot\Invintus\` -> `inc/`:

- `Invintus.php` -- singleton orchestrator. Registers the CPT, taxonomies, post-status filters, the `script_loader_tag` filter that emits `crossorigin` on the player tag. Instantiates Settings, API, Block, Metadata in `setup()`.
- `API.php` -- REST controller. Routes under `invintus/v2/`. Webhook auth expects a WP user `invintusHooks` with an application password.
- `Block.php` -- registers `taproot/invintus` block + legacy `acf/invintus-event` block. Front-end enqueue (`enqueue_block_scripts`) is already gated to pages with the block via `has_block()`.
- `Settings.php` -- admin Settings page. Reads `INVINTUS_API_KEY` / `INVINTUS_CLIENT_ID` constants from `wp-config.php` if defined, otherwise from the `invintus_video_settings` option. **The admin player enqueue is gated to Settings page + block editor only** (fixed in 2.0.12; was previously global, causing CSS bleed on every wp-admin screen).
- `Metadata.php` -- block editor sidebar for CPT metadata.
- `DB.php` -- creates `{prefix}_invintus_logs` on activation when `can_log_payloads` is on.

JS/CSS sources in `src/`, compiled into `build/` by `@wordpress/scripts`:

- `src/block.json` + `src/index.js` + `src/edit.js` + `src/render.php` -- the `taproot/invintus` block (dynamic; render.php emits front-end HTML).
- `src/view.js` -- enqueued on front-end (block.json `viewScript`) to boot the player.
- `src/settings.js` + `src/sidebar.js` -- React UIs for Settings page + CPT sidebar.

## Filter catalog (extension points)

Grep `apply_filters` in `inc/` for the complete list. Highlights:

| Filter | Default | Purpose |
|---|---|---|
| `invintus/player/script/url` | `https://player.invintus.com/app.js` | Player JS source URL |
| `invintus/player/script/crossorigin` | `''` (no attribute) | `crossorigin` HTML attr on player tag |
| `invintus/api/url` | `https://api.v3.invintus.com/v2` | Invintus API base |
| `invintus/register/slug/cpt` | `invintus_video` | CPT slug |
| `invintus/register/slug/rewrite` | `video` | Front-end rewrite slug |
| `invintus/events/watch/endpoint` | `video/watch` | `/watch` redirect base |
| `invintus/block/attributes` | (built-ins) | Add custom block attributes |

## Common pitfalls

- **Don't pre-bump versions before running `create-release.js`.** The script does it. Pre-bumping causes the diff to be uninteresting and confuses the convention.
- **Don't run `composer install` without `--no-dev` in CI / release paths** -- ships dev tooling (PHPCS, etc.) into the dist tree.
- **Don't commit `vendor/` or `build/`.** Both are gitignored; CI / `create-release.js` builds them fresh.
- **PHPCS reports ~1000 errors on the codebase.** Pre-existing BSD-brace style vs. WPCS ruleset mismatch. Not a release blocker. Don't try to "fix" them in a release PR; would inflate the diff to nothing useful.
- **The CI workflow (`.github/workflows/main.yml`) was missing `npm run build`** until 2.0.12. Older dist commits may have stale or empty `build/`. If you suspect a dist regression, compare `.dist-candidate/invintus/build/` (freshly built) against the published dist's `build/`.

## Internal-only notes

`.claude/plans/` is gitignored and holds investigation notes from past debugging sessions:

- `admin-player-enqueue-findings.md` -- the original CSS-bleed bug investigation
- `player-wp-clobber-findings.md` -- the rollup IIFE issue we found in the hosted-player repo, with a reproduction recipe
- `exec-summary.md`, `consumer-summary.md` -- partner / CTO comms drafts
- `tranquil-drifting-coral.md` -- a plan-mode artifact

Read these for historical context if needed; they document the "why" behind decisions that look unobvious in commit history.
