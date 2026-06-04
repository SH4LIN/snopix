# Snopix

Reverse image search and duplicate detection for the WordPress media library.

> ⚠️ Search ranking and duplicate clustering are still being tuned — results
> may be less accurate than expected and the index schema may change between
> 0.x releases.

**Requires:** WordPress 6.0+, PHP 8.0+ — **License:** GPLv2 or later

---

## Frontend Search Widget

Drop a search widget onto any post or page with the `[snopix_search]` shortcode.
Visitors upload (or drag and drop) an image and Snopix returns the most
visually similar images already in your media library.

### Shortcode examples

**Default card layout — good for dedicated search pages:**
```text
[snopix_search]
```

**Inline layout — embeds flush with surrounding content, no card border:**
```text
[snopix_search variant="inline"]
```

**Narrow layout — fits a sidebar or a tight content column:**
```text
[snopix_search variant="narrow" max_results="6"]
```

**Custom title and more results:**
```text
[snopix_search title="Find similar products" max_results="24"]
```

**Logged-in-only search with a custom prompt:**
```text
[snopix_search title="Find similar images" variant="card" max_results="12"]
```
*(Combined with Search visibility → Logged-in users only in Settings.)*

### Shortcode attributes

| Attribute | Values | Default | Notes |
| --- | --- | --- | --- |
| `variant` | `card`, `inline`, `narrow` | `card` | `card` — framed widget; `inline` — borderless, flows with text; `narrow` — compact single-column, good for sidebars. |
| `title` | any string | `Search by image` | Header label. Ignored by the `inline` variant. |
| `max_results` | `1`–`48` | `12` | Number of result images shown. Clamped to range. |

### Block editor

The block editor adds a **Snopix Search** panel to the core **Shortcode** block.
Open any Shortcode block, expand **Snopix Search**, and choose a variant, title,
and result cap without typing the shortcode manually.

### Access control

Search visibility (`Anyone` or `Logged-in users only`) is set in the
**Settings** tab of **Media → Snopix**. The REST endpoint that powers the
widget enforces the same rule, so server-side access control is always applied
regardless of how the shortcode is placed.

---

## Install (from source)

```bash
git clone <repo> wp-content/plugins/snopix
cd wp-content/plugins/snopix
composer install
npm ci          # installs all JS workspaces (admin, search, editor)
npm run build   # admin app + front-end search widget + editor block
wp plugin activate snopix
```

For a packaged zip, see **Build a release zip** below.

---

## How it works

Each indexed image is fingerprinted three ways and stored in `{prefix}snopix_index`:

- **pHash** — 64-bit perceptual hash over a DCT of the greyscale thumbnail. Recovers format conversions, resizes, and JPEG recompression.
- **Colour histogram** — 48-element RGB histogram. Catches palette changes and colour-graded variants.
- **Edge histogram** — 32-element Sobel edge-orientation histogram. Distinguishes structurally different images with similar colours.

A search probe is fingerprinted the same way and scored against the index as
`0.40 · pHash + 0.35 · colour + 0.25 · edge`, after a Hamming pre-filter on the
pHash to keep the inner loop cheap.

The same fingerprints power the **Duplicates** tab, which clusters near-identical
images among themselves so you can keep one and delete the rest.

Supported MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/bmp`.

---

## REST API

Base namespace: `snopix/v1`.

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/search` | Public (or logged-in only). Rate-limited — default 10 req/min, configurable 1–60. |
| `GET`  | `/status` | `manage_options` |
| `GET`  | `/images` | `manage_options` |
| `POST` | `/reindex` | `manage_options` |
| `GET`  | `/progress` | `manage_options` |
| `POST` | `/reset-progress` | `manage_options` |
| `DELETE` | `/index/{id}` | `manage_options` |
| `GET` `POST` | `/settings` | `manage_options` |
| `POST` | `/tools/reindex-all` | `manage_options` |
| `POST` | `/tools/clear-index` | `manage_options` |
| `GET`  | `/tools/orphans` | `manage_options` |
| `POST` | `/tools/delete-orphans` | `manage_options` |
| `POST` | `/tools/clear-cache` | `manage_options` |
| `GET`  | `/duplicates` | `manage_options` |
| `POST` | `/duplicates/scan` | `manage_options` |
| `GET`  | `/duplicates/progress` | `manage_options` |
| `POST` | `/duplicates/reset` | `manage_options` |
| `GET`  | `/notices` | `manage_options` |
| `POST` | `/notices/{id}/dismiss` | `manage_options` |
| `POST` | `/notices/dismiss-all` | `manage_options` |
| `POST` | `/tour/complete` | `manage_options` |

`POST /search` returns `422 unprocessable_image` when the upload cannot be
fingerprinted (corrupted bytes or unsupported MIME). Deleting a duplicate uses
the core `wp/v2/media/{id}` endpoint; the index row is cleaned up by the
attachment-deletion hook.

---

## Build a release zip

```bash
bash bin/build-zip.sh             # uses version from snopix.php
bash bin/build-zip.sh 0.1.2       # override
```

The script runs `npm ci && npm run build` then `rsync`s the source tree through
`.distignore` to strip dev artifacts before zipping. Output: `build/snopix-<version>.zip`.

CI runs the same script in `.github/workflows/release.yml` on `v*` tag pushes
and on manual `workflow_dispatch`.

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
