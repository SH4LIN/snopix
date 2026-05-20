# Pixel Scout

Reverse image search and duplicate detection for the WordPress media library.

**Version:** 0.1.0
**Requires:** WordPress 6.0+, PHP 8.0+
**License:** GPLv2 or later

> ⚠️ **Early-stage software.** Pixel Scout is in active development. The
> features below work, but ranking thresholds, duplicate clustering, and the
> index schema are still being tuned. Expect occasional false positives in
> search and duplicate detection, and possible breaking changes between
> 0.x releases. Please test on a staging site before deploying to
> production, and treat results as advisory rather than authoritative.

---

## What it does

* **Reverse-image search.** Drop an image on the admin dashboard or the
  `[ps_search]` shortcode and Pixel Scout returns the visually closest
  attachments already in your library.
* **Duplicate detection.** Scans your indexed media for near-identical groups
  and lets you pick one to keep + bulk-delete the rest.
* **Background indexing.** Bulk fingerprint generation runs in chained
  WP-Cron batches — no PHP timeouts on large libraries.
* **REST + shortcode.** Public `POST /wp-json/ps/v1/search` (rate-limited) for
  the front-end shortcode; full admin REST surface gated by `manage_options`.

How it scores: each indexed attachment carries a 64-bit pHash, a 48-element
RGB histogram, and a 32-element Sobel edge histogram. A probe image is
fingerprinted the same way and ranked against the index with a composite
score of `0.40·pHash + 0.35·colour + 0.25·edge`, after a Hamming pre-filter
on the pHash to keep the inner loop cheap.

Supported formats: `image/jpeg`, `image/png`, `image/gif`, `image/webp`,
`image/bmp`.

---

## Install (from source)

```bash
git clone https://github.com/your-org/pixel-scout wp-content/plugins/pixel-scout
cd wp-content/plugins/pixel-scout
composer install
( cd admin/app && npm ci && npm run build )
wp plugin activate pixel-scout
```

Then go to **Media → Pixel Scout** and click **Index Remaining** to
fingerprint any attachments already in the library.

For a packaged release zip, see the **Build a release zip** section below or
download the artifact from a GitHub Actions run of the `Build Release Zip`
workflow.

---

## Architecture

Plugin source lives under `includes/` and is grouped by domain:

| Layer | Path | Purpose |
| --- | --- | --- |
| Imaging | `includes/imaging/` | GD loader, pHash / colour / edge processors, similarity metrics |
| Search | `includes/search/` | Fingerprint factory, scoring, pipeline, query-image upload |
| Indexing | `includes/indexing/` | Single + bulk indexers, progress transients, MIME validator |
| Duplicates | `includes/duplicates/` | Finder, scanner cron, progress tracking |
| Repository | `includes/repository/` | DB schema + `$wpdb`-bound index access |
| API | `includes/api/` | REST controllers + rate limiter |
| Hooks | `includes/hooks/` | WordPress integration (cron, media, settings) |
| Admin | `includes/admin/` + `admin/app/` | Dashboard page (PHP) + React app (TSX/Vite) |
| Frontend | `includes/frontend/` + `public/` | `[ps_search]` shortcode + widget JS/CSS |
| Infrastructure | `includes/infrastructure/` | Autoloader, plugin bootstrap, attachment query helpers |

The admin app is a Vite-built React 18 SPA (TanStack Query + TanStack
Router + Zustand) that lives under `admin/app/`. Only the built bundle
under `admin/app/dist/` ships in the release zip.

---

## REST API

Base namespace: `ps/v1`.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/search` | public (rate-limited 10/60s) | Reverse-image search; multipart `file=`. |
| `GET`  | `/status` | `manage_options` | Total / indexed / pending counts. |
| `GET`  | `/images` | `manage_options` | Paginated indexed-attachment list. |
| `POST` | `/reindex` | `manage_options` | Schedule index of pending attachments. |
| `GET`  | `/progress` | `manage_options` | Indexing job progress. |
| `DELETE` | `/index/{id}` | `manage_options` | Remove one row from the index. |
| `POST` | `/tools/reindex-all` | `manage_options` | Wipe + rebuild every fingerprint. |
| `POST` | `/tools/clear-index` | `manage_options` | Delete all fingerprints. |
| `POST` | `/tools/delete-orphans` | `manage_options` | Remove rows whose attachment is gone. |
| `POST` | `/tools/clear-cache` | `manage_options` | Flush plugin transients. |
| `GET`  | `/tools/orphans` | `manage_options` | Orphan-row count. |
| `GET`  | `/duplicates` | `manage_options` | Cached duplicate groups. |
| `POST` | `/duplicates/scan` | `manage_options` | Start a fresh duplicate scan. |
| `GET`  | `/duplicates/progress` | `manage_options` | Duplicate scan progress. |
| `DELETE` | `/duplicates/attachment/{id}` | `manage_options` | Delete one attachment from a group. |

The `POST /search` endpoint responds with `422 unprocessable_image` when the
uploaded file can't be fingerprinted (corrupted bytes or unsupported MIME),
so the UI can distinguish a broken upload from a legitimately empty result
set.

---

## Front-end shortcode

```text
[ps_search]
```

Drops a search widget anywhere on the front end. Visibility is configurable
under **Settings → Connectors → Pixel Scout** — either `anyone` (default) or
`logged-in`.

---

## Testing

```bash
composer install
composer test            # PHPUnit unit tests
composer lint            # PHPCS (WordPress Coding Standards)
composer analyse         # PHPStan level 5

npm --prefix admin/app ci
npm --prefix admin/app run build
npx playwright test      # End-to-end (Playwright)
```

The PHPUnit suite uses the `wp-phpunit` test scaffold and a MySQL service
container (see `.github/workflows/ci.yml` for the CI configuration that
provisions WordPress core and the test database).

A reproducible reverse-image-search regression suite lives under
`tests/fixtures/images/run_search_tests.py` — see `TEST_REPORT.md` for the
matrix and findings.

---

## Continuous integration

`.github/workflows/ci.yml` runs on every PR and push to `main` / `development`:

| Job | What it runs |
| --- | --- |
| `phpcs` | `composer lint` on PHP 8.1 |
| `phpstan` | `composer analyse` on PHP 8.1 |
| `phpunit` | `composer test` across PHP 8.0 / 8.1 / 8.2 / 8.3 |

---

## Build a release zip

`.github/workflows/release.yml` produces a WordPress.org-deployable zip on
either of:

* a push of a tag matching `v*` (e.g. `git tag v0.2.0 && git push --tags`),
  which also attaches the zip to a GitHub release; or
* a manual run from **Actions → Build Release Zip → Run workflow**.

The build runs `npm ci && npm run build` for the admin app, then `rsync`s
the source tree through `.distignore` to strip dev artifacts (tests, vendor,
configs, `node_modules`, etc.) before zipping. The final archive is ~500 KB
and contains only `pixel-scout.php`, `uninstall.php`, `readme.txt`,
`includes/`, `admin/app/dist/`, `admin/app/views/`, and `public/`.

To build locally:

```bash
( cd admin/app && npm ci && npm run build )
rsync -a --exclude-from=.distignore --exclude=build ./ build/pixel-scout/
( cd build && zip -rq pixel-scout-0.1.0.zip pixel-scout )
```

---

## Roadmap

| Area | Status |
| --- | --- |
| pHash + colour + edge fingerprinting | ✅ Implemented |
| Reverse-image search (admin + shortcode) | ✅ Implemented |
| Bulk WP-Cron indexing | ✅ Implemented |
| Duplicate detection + bulk delete | ✅ Implemented |
| Admin React UI (Dashboard / Duplicates / Tools) | ✅ Implemented |
| CI: PHPCS + PHPStan + PHPUnit matrix | ✅ Implemented |
| Threshold tuning across more real-world libraries | ⏳ Ongoing |
| WordPress.org `readme.txt` polishing + screenshots | ⏳ In progress |
| Localisation files (`languages/pixel-scout.pot`) | ⏳ Pending |
| BMP indexing parity with other formats | ⏳ Newly added, watching |
| Performance audit on libraries > 10k attachments | ⏳ Pending |

---

## Contributing

* Follow WordPress Coding Standards (`composer lint`).
* Escape all output; sanitise all input.
* Keep `$wpdb` calls inside repository classes only.
* Don't register `add_action` / `add_filter` from domain or service classes —
  bind hooks in `includes/hooks/` or in `Plugin::register()`.
* Add or update PHPUnit tests for any new public behaviour.

The plugin is pre-1.0 and the search/duplicate thresholds are still being
tuned; if you hit a case where Pixel Scout misranks or misses an obvious
match, please open an issue with the source and probe images and a snippet
of the response payload.

---

## License

GNU General Public License v2 or later. See `LICENSE`.
