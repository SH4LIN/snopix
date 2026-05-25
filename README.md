# Snopix

Reverse image search and duplicate detection for the WordPress media library.

> ⚠️ Search ranking and duplicate clustering are still being tuned, so
> results may be less accurate than expected and the index schema may change
> between 0.x releases.

**Requires:** WordPress 6.0+, PHP 8.0+ — **License:** GPLv2 or later

---

## Install (from source)

```bash
git clone <repo> wp-content/plugins/snopix
cd wp-content/plugins/snopix
composer install
( cd admin/app && npm ci && npm run build )
wp plugin activate snopix
```

For a packaged zip, see **Build a release zip** below.

---

## How it works

Each indexed attachment carries three fingerprints stored in
`{prefix}snopix_index`:

* a 64-bit perceptual hash (pHash) over a DCT of the greyscale thumbnail
* a 48-element RGB colour histogram
* a 32-element Sobel edge-orientation histogram

A probe image is fingerprinted the same way and scored against the index as
`0.40·pHash + 0.35·colour + 0.25·edge`, after a Hamming pre-filter on the
pHash to keep the inner loop cheap.

Supported MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`,
`image/bmp`.

---

## Front-end shortcode

```text
[snopix_search]
```

Drops a search widget on the front end. Visibility (`anyone` or
`logged-in`) is configurable under **Settings → Connectors → Snopix**.

---

## REST API

Base namespace: `snopix/v1`.

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/search` | public (rate-limited 10/60s) |
| `GET`  | `/status` | `manage_options` |
| `GET`  | `/images` | `manage_options` |
| `POST` | `/reindex` | `manage_options` |
| `GET`  | `/progress` | `manage_options` |
| `DELETE` | `/index/{id}` | `manage_options` |
| `POST` | `/tools/reindex-all` | `manage_options` |
| `POST` | `/tools/clear-index` | `manage_options` |
| `POST` | `/tools/delete-orphans` | `manage_options` |
| `POST` | `/tools/clear-cache` | `manage_options` |
| `GET`  | `/tools/orphans` | `manage_options` |
| `GET`  | `/duplicates` | `manage_options` |
| `POST` | `/duplicates/scan` | `manage_options` |
| `GET`  | `/duplicates/progress` | `manage_options` |
| `DELETE` | `/duplicates/attachment/{id}` | `manage_options` |

`POST /search` returns `422 unprocessable_image` when the upload cannot be
fingerprinted (corrupted bytes or unsupported MIME), so the UI can
distinguish a broken upload from an empty result set.

---

## Build a release zip

```bash
bash bin/build-zip.sh             # uses version from snopix.php
bash bin/build-zip.sh 0.1.1       # override
```

The script runs `npm ci && npm run build` for the admin app, then `rsync`s
the source tree through `.distignore` to strip dev artifacts before zipping.
Output lands at `build/snopix-<version>.zip`.

CI runs the same script in `.github/workflows/release.yml` on `v*` tag
pushes and on manual `workflow_dispatch`.

---

## Testing & CI

```bash
composer test            # PHPUnit
composer lint            # PHPCS (WordPress Coding Standards)
composer analyse         # PHPStan level 5
npx playwright test      # End-to-end
```

`.github/workflows/ci.yml` runs PHPCS, PHPStan, and PHPUnit (PHP 8.0–8.3
matrix) on every PR and push to `main` / `development`.

See [CONTRIBUTING.md](./CONTRIBUTING.md) for the full development workflow.

---

## License

GNU General Public License v2 or later. See `LICENSE`.
