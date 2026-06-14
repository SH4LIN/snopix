=== Snopix ===
Contributors: SH4LIN, akrocks, vishalkakadiya, hilayt24
Tags: image-search, reverse-image-search, similarity-search, duplicates, media-library
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reverse image search and duplicate detection for the WordPress media library, powered by perceptual hashing.

== Accuracy notice ==

Snopix is functional, but search ranking, duplicate detection and the
threshold tuning may be less accurate than expected. You may see occasional
false positives or miss visually-similar images depending on your media
library. Please report anything that looks off.

== Description ==

Snopix adds reverse-image search to your WordPress site. Visitors drop an
image onto the search widget and the plugin returns the most visually similar
images already in your media library - ranked by a composite score that looks
at overall structure, colour palette, and edge patterns all at once.

The same fingerprints power a **Duplicates** tab in the admin: it clusters
near-identical attachments so you can keep one copy and bulk-delete the rest.

= Frontend search widget =

Place a search widget on any page using the `[snopix_search]` shortcode.

**Default card layout** - framed widget, good for a standalone search page:
`[snopix_search]`

**Inline layout** - borderless, flows naturally with your page content:
`[snopix_search variant="inline"]`

**Narrow layout** - compact single column, fits a sidebar or tight layout:
`[snopix_search variant="narrow" max_results="6"]`

**Custom title and more results:**
`[snopix_search title="Find similar products" max_results="24"]`

**Card with a custom prompt and result cap:**
`[snopix_search variant="card" title="Reverse Image Search" max_results="16"]`

Optional attributes:

* `variant` - `card` (default), `inline`, or `narrow`.
* `title` - header label shown above the drop zone. Default: "Search by image". Ignored by the `inline` variant.
* `max_results` - number of result images to show. Accepts 1-48. Default: 12.

The block editor also exposes these options via a **Snopix Search** panel on
the core **Shortcode** block, so you can configure the widget without typing
the shortcode manually.

By default the search endpoint is open to all visitors. Restrict it to
logged-in users only from the **Settings** tab in **Media → Snopix**.

= Admin search =

The dashboard includes a drag-and-drop search panel that lets admins test
reverse-image search directly from the media library page without publishing a
shortcode anywhere.

= Duplicate detection =

The **Duplicates** tab scans your indexed images against each other and groups
near-identical files. Each group shows the images side by side with a "keep"
selector. Delete all duplicates in a group with one click, or use the global
**Delete all duplicates** button to clean everything at once.

= Background indexing =

New uploads are fingerprinted automatically on save. Existing images are
indexed in the background via WP-Cron in batches, so large libraries
(10 000+ images) can be indexed without hitting PHP time limits.

= Features =

* Frontend reverse-image search via `[snopix_search]` shortcode (card, inline, and narrow variants).
* Block-editor shortcode panel for configuring the widget visually.
* Admin dashboard search dropzone for quick manual testing.
* Duplicate detection with per-group keep selection and bulk delete.
* Automatic indexing of new uploads; background bulk indexing for existing images.
* REST API at `/wp-json/snopix/v1/` with rate limiting on the public search endpoint.
* Settings: search visibility, rate limit, match and duplicate thresholds, indexer batch size, probe downscale ceiling.
* Tools panel: reindex everything, clear the index, delete orphan rows, flush caches.
* Clean uninstall with option to keep or drop all data.

= Supported image formats =

JPEG, PNG, GIF, WebP, BMP.

= Where data lives =

Snopix creates one custom table (`{prefix}snopix_index`) with one row per
indexed image. Uninstalling the plugin drops the table unless you turn off
**Drop data on uninstall** in the Settings tab.

== Installation ==

1. Upload the `snopix` folder to `/wp-content/plugins/` (or install the zip via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin in **Plugins**.
3. Go to **Media → Snopix** and click **Index Remaining** to fingerprint images already in your library. This runs in the background and may take a few minutes for large libraries.
4. Once indexing is complete, drop any image onto the **Search by Image** panel on the dashboard to test.
5. Add `[snopix_search]` to any page or post to give visitors the same search widget.

== Frequently Asked Questions ==

= Which image formats are supported? =

JPEG, PNG, GIF, WebP, and BMP. Other types (SVG, HEIC, AVIF, TIFF) are
rejected at both the upload and search endpoints.

= What does each shortcode variant look like? =

* `card` (default) - a self-contained framed widget with a drop zone, progress bar, and result grid. Best as a standalone element on a dedicated search page.
* `inline` - no card border or header label; the drop zone and results sit flush with your page content. Good when the search feels like part of a larger UI.
* `narrow` - compact single-column layout designed for sidebars or narrow content areas.

= How do I add the search widget to a page? =

Add `[snopix_search]` to the body of any post or page. In the block editor,
insert a Shortcode block and either paste the tag or use the **Snopix Search**
panel in the block sidebar to configure it visually.

For a sidebar, use `[snopix_search variant="narrow" max_results="6"]`. For a
full-width search page, use `[snopix_search max_results="24"]`.

= Can I restrict search to logged-in users? =

Yes. Go to **Media → Snopix → Settings** and set **Search visibility** to
**Logged-in users only**. The REST endpoint and the frontend widget both
enforce the same rule.

= How big can my media library be? =

The fingerprint table is compact (one row per image). The bulk indexer runs in
chained WP-Cron batches, so it will not time out regardless of library size,
though indexing 10 000+ images takes several minutes.

= Does it work with images stored on S3 or a CDN? =

The indexer reads raw bytes via PHP-GD. If your offload plugin keeps a local
copy until indexing is done, you are fine. If files are removed from the local
filesystem before the indexer runs, those images are skipped.

= How accurate is the search? =

The composite score combines perceptual hash, colour histogram, and edge
histogram (weights 0.40 / 0.35 / 0.25). Format conversions, resizes, and JPEG
recompression are typically recovered with >0.95 similarity. Heavy blur,
extreme downscale, or noise corruption may score near the threshold and
occasionally not rank first. Tuning is ongoing.

= How is duplicate detection different from search? =

Search matches a probe image (something you upload) against the indexed
library. Duplicate detection clusters the indexed images against each other
to find pairs or groups that are already near-identical, without needing an
external probe.

= Does it work on WordPress multisite? =

Snopix is built for single-site installs. It creates one
`{prefix}snopix_index` table per site and is not network-activation aware.
Activate it per-site rather than network-wide.

= How do I uninstall cleanly? =

Deactivate and delete the plugin. By default your index is kept on uninstall.
To remove all plugin data - index table, options, transients, scheduled cron
events, and per-user meta - enable **Drop data on uninstall** in the Settings
tab before deleting the plugin.

== Screenshots ==

1. Dashboard with stat counters, indexed-image table, and the reverse-image search dropzone.
2. Frontend search widget (card variant) showing the drop zone and result grid on a public page.
3. Duplicate groups with per-group keep selection and bulk delete.
4. Tools panel for reindexing, clearing the index, deleting orphan rows, and flushing caches.
5. Settings for match thresholds, search rate limiting, batch size, and the keep / drop-on-uninstall choice.

== Changelog ==

= 0.2.0 - 2026-06-14 =
* Added: "Search by image" is now available throughout the wp-admin media flow - a panel on the Upload New Media screen, a toolbar button in the Media Library list and grid views, and a dedicated tab in the media modal.
* Changed: colour fingerprints now use HSV histograms instead of RGB averages, improving tolerance to lighting and exposure differences.
* Changed: edge fingerprints expanded to orientation histograms for finer structural matching.
* Changed: perceptual hash now uses the low-frequency AC block of the DCT (DC components excluded), so uniform brightness shifts no longer dominate the hash.
* Changed: histogram comparison switched from cosine to Bhattacharyya similarity for more accurate distribution matching.
* Changed: images that fail fingerprinting are now marked unprocessable instead of receiving an all-zero hash, preventing failed images from clustering as false duplicates.
* Added: upgrade routine that migrates the database schema and automatically rebuilds the image index when fingerprint formats change between versions.
* Changed: per-IP search rate limiting now uses advisory locks to avoid double-counting under concurrent requests.

= 0.1.4 - 2026-06-04 =
* Changed: search visibility default changed from "Anyone" to "Logged-in users only" for stricter access out of the box.
* Changed: "Drop data on uninstall" now defaults to disabled - your index is preserved on plugin removal unless you opt in.
* Changed: slider values in Settings are now click-to-edit - click any numeric label to type a value directly.
* Changed: indexing panel shows "Last run completed" once a reindex job finishes, rather than staying silent.
* Changed: admin UI terminology standardised from "attachments" to "images" throughout.

= 0.1.3 - 2026-06-03 =
* Fixed: bulk indexer no longer aborts the entire queue when a later batch encounters only unreadable images; the stall guard now fires only on the first batch, so isolated file failures are skipped rather than stranding all remaining attachments.
* Changed: inter-batch delay reduced from 60 s to 15 s, keeping the reindex progress UI responsive during a full library scan.
* Fixed: third-party admin notices are now suppressed on the Snopix dashboard to prevent them from breaking the full-bleed layout.
* Changed: admin script now declares `wp-i18n` as a dependency; build artefacts reorganised to `assets/` subdirectories.
* Fixed: uninstall cleanup now removes all plugin data - index table, all options, transients, scheduled cron events, and per-user meta - when the "Remove all plugin data on uninstall" setting is enabled.

= 0.1.2 - 2026-06-02 =
* Fixed: removed `readonly` properties that broke activation on PHP 8.0 (the declared minimum version).
* Changed: per-IP search rate limiting now uses an atomic counter when a persistent object cache is present, and fails closed when the client IP cannot be resolved.
* Fixed: bulk indexing no longer stalls when a run of unsupported image types (SVG/AVIF/TIFF/HEIC) is queued - they are skipped at enqueue time.
* Fixed: the daily duplicate scan no longer restarts an in-progress scan, and a failed scan tick recovers instead of appearing to run forever.
* Fixed: deleting a non-existent index row now returns 404; plus minor admin-UI memory and dead-code cleanups.
* Changed: the dashboard "Indexed" count is now shown in green, with status colours centralised as reusable design tokens.

= 0.1.1 - 2026-05-30 =
* Fixed: certain extreme aspect-ratio images could trigger a fatal error during indexing; working dimensions are now clamped.
* Changed: index vector columns now use LONGTEXT instead of JSON for compatibility with older MySQL / MariaDB.
* Fixed: capitalised indexed-image status labels and corrected spacing on the duplicate "Keep" badge.
* Removed: the non-functional Plugins-screen delete-confirmation modal and its "require confirmation" setting. The keep / drop-on-uninstall setting is unchanged.

= 0.1.0 - 2026-05-30 =
* Initial release.
* Perceptual hash + colour histogram + edge histogram fingerprinting.
* Reverse-image search via admin dropzone and `[snopix_search]` shortcode.
* Block-editor panel for inserting and configuring the search shortcode.
* Duplicate detection with per-group keep selection.
* WP-Cron bulk indexing, rate-limited public search endpoint.
* Configurable thresholds, rate limit, and batch size, plus a keep or drop-on-uninstall choice.
* WordPress.org compatibility: JPEG, PNG, GIF, WebP, BMP.

== Upgrade Notice ==

= 0.2.0 =
Fingerprint formats changed (HSV colour, orientation edges, AC-block pHash). Your image index is wiped and rebuilt automatically after upgrading; search and duplicate detection may show partial results until the reindex completes.

= 0.1.4 =
Changes default search visibility to logged-in users only and disables drop-on-uninstall by default. Review your Settings tab after upgrading.

= 0.1.3 =
Hardens bulk indexing resilience, speeds up reindex progress, and fixes uninstall cleanup to remove all plugin data.

= 0.1.2 =
Restores PHP 8.0 compatibility and hardens background indexing, duplicate scanning, and search rate limiting.

= 0.1.1 =
Stability and compatibility fixes. Removes the non-working uninstall-confirmation prompt; your keep / drop-on-uninstall setting is unaffected.

= 0.1.0 =
First public preview. Expect breaking changes between 0.x releases as thresholds and the index schema are still being tuned.
