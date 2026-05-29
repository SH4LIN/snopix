=== Snopix ===
Contributors: SH4LIN, akrocks, vishalkakadiya
Tags: image-search, reverse-image-search, similarity-search, duplicates, media-library
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reverse image search and duplicate detection for the WordPress media library, powered by perceptual hashing.

== Accuracy notice ==

Snopix is functional, but search ranking, duplicate detection and the
threshold tuning may be less accurate than expected - you may see occasional
false positives or miss visually-similar images depending on your media
library. Please report anything that looks off.

== Description ==

Snopix adds reverse-image search to the WordPress media library. Upload
an image (or drop one onto the dashboard widget) and the plugin returns the
visually most similar attachments you already have indexed, ranked by a
composite score over three fingerprints:

* A 64-bit perceptual hash (pHash) over a DCT of the greyscale thumbnail
* A 48-element RGB colour histogram
* A 32-element Sobel edge-orientation histogram

The same fingerprints power the **Duplicates** tab, which clusters
near-identical attachments so you can keep one and bulk-delete the rest.

= Features =

* Reverse-image search via the admin dashboard or a `[snopix_search]` shortcode for
  the front end.
* Duplicate detection with per-group "keep" selection and bulk delete.
* Background bulk indexing via WP-Cron — no PHP timeouts on large libraries.
* REST API at `/wp-json/snopix/v1/` with rate limiting on the public search
  endpoint.
* Settings panel to gate the public search endpoint to logged-in users only.
* Tools panel: reindex everything, clear the index, delete orphan rows,
  flush caches.
* Pre-downscale of large probe images before fingerprinting to keep search
  latency bounded.

= Supported MIME types =

`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/bmp`.

= Where data lives =

Snopix creates a single custom table, `{prefix}snopix_index`, that stores one
row per indexed attachment with its three fingerprints. Uninstalling the
plugin drops the table.

== Installation ==

1. Upload the `snopix` folder to `/wp-content/plugins/` (or install the
   zip via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin in **Plugins**.
3. Go to **Media → Snopix** and click **Index Remaining** to fingerprint
   any attachments already in your library. This runs in the background via
   WP-Cron.
4. Once at least a handful of images are indexed, drop an image onto the
   **Search by Image** panel to test reverse-image search.

= Front-end shortcode =

Add the search widget to any post or page with:

`[snopix_search]`

By default the endpoint is open to anyone. Restrict it to logged-in users in
**Settings → Connectors → Snopix**.

== Frequently Asked Questions ==

= What image formats are supported? =

JPEG, PNG, GIF, WebP, and BMP. Other types (SVG, HEIC, AVIF) are rejected at
upload time by the indexer and again at the search endpoint.

= How big can my media library be? =

The fingerprint table stores one compact row per attachment, so the storage
cost is small. Indexing scales linearly with the number of attachments. The
bulk indexer runs in chained WP-Cron batches so it does not time out, but
indexing a very large library (10k+) will take many minutes.

= Does it work with images stored on S3 / a CDN? =

The indexer needs to read raw bytes via PHP-GD. If your offload plugin keeps
a local copy until indexing is complete, you are fine. If files are removed
from the local filesystem before the indexer runs, those attachments will be
skipped.

= How accurate is the search? =

The composite score combines pHash + colour + edge histograms with weights
0.40 / 0.35 / 0.25. In our internal test matrix, format conversions,
resizing, and JPEG re-compression are recovered with > 0.95 similarity.
Heavy Gaussian blur, extreme downscale (sub-128 px), and noise-corrupted
images sit closer to the 0.85 threshold and may not always rank first.
Tuning is ongoing.

= How is duplicate detection different from search? =

Both rely on the same fingerprints. Search returns ranked matches for an
arbitrary probe image; duplicate detection clusters indexed images among
themselves and surfaces every group with two or more visually-identical
members.

= How do I uninstall cleanly? =

Deactivate, then delete the plugin from the **Plugins** screen. The
uninstall hook drops `{prefix}snopix_index` and removes all plugin options and
transients.

== Screenshots ==

1. Dashboard with stat counters, indexed-image table, and the reverse-image
   search dropzone.
2. Duplicate groups with per-group keep selection and bulk delete.
3. Tools panel for reindexing, clearing the index, deleting orphan rows, and
   flushing caches.

== Changelog ==

= 0.1.0 =
* Initial release.
* Perceptual hash + colour histogram + edge histogram fingerprinting.
* Reverse-image search via admin dropzone and `[snopix_search]` shortcode.
* Duplicate detection with per-group keep selection.
* WP-Cron bulk indexing, rate-limited public search endpoint.
* WordPress.org compatibility: JPEG, PNG, GIF, WebP, BMP.

== Upgrade Notice ==

= 0.1.0 =
First public preview. Expect breaking changes between 0.x releases as
thresholds and the index schema are still being tuned.
