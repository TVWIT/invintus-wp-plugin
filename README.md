# Invintus WordPress Plugin

WordPress plugin that integrates the Invintus video platform. Registers an `invintus_video` custom post type, a Gutenberg block for embedding events, an admin settings page for API credentials and player preferences, and webhook routes that keep videos in sync with the Invintus API.

## Features

- `invintus_video` CPT with `invintus_category` / `invintus_tag` taxonomies
- Gutenberg block (`taproot/invintus`) with live editor preview
- Settings UI for API key, client ID, default player preference, and watch redirect path
- REST endpoints under `invintus/v2/` for webhook upserts and player preferences
- `invintus/...` filters for extending almost everything (see `Extension points` below)

## Installation

Most installs pull from the release dist (see `Repository layout`).

1. Download the latest ZIP from the [dist repo releases page](https://github.com/TVWIT/invintus-wp-plugin-dist/releases).
2. WP Admin > Plugins > Add New > Upload Plugin > choose the ZIP > Install + Activate.
3. Configure under `Invintus Videos > Settings`, or define the constants in `wp-config.php`:
   ```php
   define( 'INVINTUS_API_KEY',   'your-api-key'   );
   define( 'INVINTUS_CLIENT_ID', 'your-client-id' );
   ```

Constants take precedence over the UI when defined.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Node.js 16+ and Composer (development only)

## Development

Two ways to run the plugin locally. Pick one.

### Quickstart with wp-env (recommended)

Spins up a Dockerized WP at `http://localhost:8888`, mounts this repo as a plugin, and gives you `wp` / `composer` / `phpcs` inside the container. No host PHP needed.

```bash
npm install
npm run dist:build
npm run env:start
```

Then visit http://localhost:8888/wp-admin (login: `admin` / `password`).

To run the plugin the way it actually ships to users (compiled `build/`, vendored `vendor/`, no dev tooling), build a dist candidate and tell wp-env to mount that instead:

```bash
npm run dist:build   # writes .dist-candidate/invintus/
```

Then create `.wp-env.override.json` (gitignored) at the repo root:

```json
{
  "plugins": ["./.dist-candidate/invintus"],
  "config": {
    "INVINTUS_API_KEY":   "your-key",
    "INVINTUS_CLIENT_ID": "your-client-id"
  }
}
```

`npm run env:start` again to pick up the override.

Other env scripts: `env:stop`, `env:destroy`, `env:clean`, `env:logs`, `env:wp` (wp-cli passthrough), `env:composer`, `env:phpcs`.

#### One-time bootstrap after a fresh `env:start`

After every `env:destroy` + `env:start`, WP boots with Plain permalinks and no `.htaccess`. Pretty REST URLs (`/wp-json/...`) return Apache 404s until both are fixed. The "Save Permalinks in wp-admin" trick reports success but doesn't actually write `.htaccess` -- `/var/www/html` is owned by `root` inside the wp-env container so the `www-data` PHP process can't write there. Do it from the host instead:

```sh
# 1. set a non-plain permalink structure
npx wp-env run cli -- wp rewrite structure '/%postname%' --hard

# 2. write the standard WP .htaccess (as root, via the cli container)
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

After this the Settings page can save, REST routes resolve, and the block editor works normally. The file persists for the life of the env -- only re-run after a destroy.

To do the whole "wipe everything and start over" cycle in one shot (destroy + rebuild dist + start + bootstrap):

```bash
npm run env:reset
```

That runs `scripts/reset-env.sh`. It destroys the wp-env database, rebuilds `build/` and `.dist-candidate/`, brings the env back up, and applies the bootstrap above. Use it to verify the "fresh clone" recipe end-to-end or when env state is the suspected cause of a bug. Does not touch `node_modules` -- delete that manually first if you also want a fresh `npm install`.

### Traditional setup

Clone into an existing WP install:

```bash
cd wp-content/plugins
git clone https://github.com/TVWIT/invintus-wp-plugin invintus
cd invintus
composer install
npm install
npm run start   # watch-mode dev build
```

## Build, lint, format

| Script                 | What it does                                       |
|---                     |---                                                 |
| `npm run start`        | Watch-mode webpack build (also copies `render.php`)|
| `npm run build`        | Production build into `build/`                     |
| `npm run lint:js`      | ESLint on JS sources                               |
| `npm run lint:css`     | Stylelint on SCSS sources                          |
| `npm run format`       | Prettier across the tree                           |
| `composer php:lint`    | PHPCS (WordPress Coding Standards + PHPCompat)     |
| `composer php:lint:autofix`   | PHPCBF                                      |
| `composer php:lint:changed`   | PHPCS on unstaged changes only              |

## Overriding the player URL

The player script source is filterable. Drop a [must-use plugin](https://wordpress.org/documentation/article/must-use-plugins/) at `wp-content/mu-plugins/invintus-player.php` to redirect it:

```php
<?php
add_filter( 'invintus/player/script/url', function() {
    return 'https://player.beta.invintusmedia.com/app.js';
} );
```

`mu-plugins` load before regular plugins, can't be deactivated through the admin UI, and survive plugin updates -- ideal for "site policy" overrides that shouldn't ride with a release. Remove the file to revert.

The override is scoped to the screens that actually need the player (the Invintus settings page, the block editor, and front-end posts with the block). It does not load on unrelated wp-admin screens.

### Cross-origin player builds

If the override URL points to a build that uses dynamic `import()` for its own chunks (some bundlers split runtime code this way), the browser will refuse to resolve the relative module specifiers unless the `<script>` tag opts into CORS. Symptom: errors like `Failed to resolve module specifier './chunks/...'. The base URL is about:blank because import() is called from a CORS-cross-origin script.`

Add a companion filter alongside the URL override:

```php
add_filter( 'invintus/player/script/url',         fn() => 'https://player.beta.invintusmedia.com/app.js' );
add_filter( 'invintus/player/script/crossorigin', fn() => 'anonymous' );
```

This emits `crossorigin="anonymous"` on the player `<script>` tag. The target URL must return `Access-Control-Allow-Origin: *` (or matching) for the script to load -- if it doesn't, the script load itself will fail with a CORS error. The default URL does not need CORS, so leave the filter off if you're not overriding the URL.

## Extension points

Most behavior is filterable. Grep `apply_filters` in `inc/` for the complete list. Highlights:

| Filter                              | Default                                | Purpose                              |
|---                                  |---                                     |---                                   |
| `invintus/player/script/url`        | `https://player.invintus.com/app.js`   | Player JS source URL                 |
| `invintus/player/script/crossorigin`| `''` (no attribute)                    | `crossorigin` attribute on player tag (set to `'anonymous'` for cross-origin builds with dynamic imports) |
| `invintus/api/url`                  | `https://api.v3.invintus.com/v2`       | Invintus API base                    |
| `invintus/register/slug/cpt`        | `invintus_video`                       | CPT slug                             |
| `invintus/register/slug/rewrite`    | `video`                                | Front-end rewrite slug               |
| `invintus/events/watch/endpoint`    | `video/watch`                          | `/watch` redirect base               |
| `invintus/block/attributes`         | (built-ins)                            | Add custom block attributes          |

## Repository layout

The plugin lives in two repositories. Knowing which is which matters before you make changes.

| Repo | Purpose | What's in it |
|---   |---      |---           |
| [`TVWIT/invintus-wp-plugin`](https://github.com/TVWIT/invintus-wp-plugin) (this repo) | Source of truth. All development happens here. | Full source: `src/`, `inc/`, build config, CI, tests, dev tooling. |
| [`TVWIT/invintus-wp-plugin-dist`](https://github.com/TVWIT/invintus-wp-plugin-dist) | Distribution. Where releases are published. | Build artifacts only: compiled `build/`, vendored `vendor/`, runtime PHP. No `src/`, no `package.json`, no CI. |

The dist repo is written **automatically** by this repo's release workflow. Do not hand-edit it -- changes get overwritten on the next tagged release. Install or update from GitHub Releases on the dist repo, or pull via Composer / `wp plugin install <url>`.

> An older repo named `TVWIT/wp-plugin-invintus` (default branch `master`) is a deprecated predecessor and should be ignored. It has not received updates since early 2025.

## Releasing a new version

Releases are driven by `create-release.js`. The script handles version bumping, build, dist push, tagging both repos, and creating GitHub Releases in one command. There is a tag-based CI workflow as well (see below), but the primary release path is the script.

Requires host-installed `composer` (`brew install composer` on macOS).

### Standard release sequence

```sh
# 1. Land changes on main via PR + merge
gh pr merge <N> --merge --repo TVWIT/invintus-wp-plugin

# 2. Pull main locally
git checkout main && git pull

# 3. Dry-run first (no remote effect, just version bumps + build)
node create-release.js version=2.0.13

# 4. Inspect the result
git status
git diff invintus.php package.json
ls build/

# 5. If clean, run the real release
node create-release.js version=2.0.13 --push --create-release

# 6. Clean up post-release
git checkout invintus.php package.json   # discard local bumps (existing convention)
rm -f invintus-wp-plugin.zip             # release artifact left in repo root
```

### What the script does

1. Updates `invintus.php` (`Version:` header + `INVINTUS_PLUGIN_VERSION` constant) and `package.json` `"version"`. **These are not committed** -- the bumps live only in the dist repo. Source stays at its in-dev version after release.
2. Runs `npm run build` and `composer install --no-dev --optimize-autoloader`.
3. With `--push`: clones the dist repo, wipes it, copies in `build`, `assets`, `inc`, `vendor`, `templates`, `views`, `invintus.php`, `LICENSE`, `doc.md` plus a stripped `composer.json`. Commits `Release vX.Y.Z`, tags the dist repo, and tags the source repo at current HEAD. Pushes both.
4. With `--create-release`: builds the ZIP and publishes a GitHub Release on both repos with the ZIP attached. Both releases share the same artifact (identical SHA-256).

### Script flags

| Flag | Effect |
|---|---|
| `version=X.Y.Z` | Required. `composer.json` has no `version` field, so the arg is mandatory. |
| `outdir=PATH` | Where the ZIP gets dropped. Defaults to repo root. |
| `--push` | Push to dist + tag both repos. Without this, the script is a local dry-run. |
| `--create-release` | Publish GitHub Releases on both repos. Requires `--push`. |
| `--no-zip` | Skip ZIP creation. |
| `--no-tag` | Skip tagging (still does dist push if `--push` is set). |

### Tag-based CI fallback

`.github/workflows/main.yml` runs on any push of a `v*.*.*` tag and does roughly the same work as the script (npm ci + composer install + npm run build + copy to dist + zip + GitHub Release). It exists as a safety net if someone tags by hand (`git tag vX.Y.Z && git push origin vX.Y.Z`) without using the script. The standard path is the script.

### History gotcha: orphan `v2.0.11`

`v2.0.11` was tagged on `main` in May 2025 without an actual version bump (the commit only added `.distignore`/`.gitignore` changes). The dist's `Release v2.0.11` commit doesn't correspond to a coherent source release. Versions skipped from 2.0.10 -> 2.0.12. Don't reuse v2.0.11.

## License

ISC -- see [`LICENSE`](LICENSE).
