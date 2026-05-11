# Pixel Scout — Project Plan

No AI. Pure vector math. PHP 8.0+, WP 6.0+, React 18.

---

## NAME + IDENTIFIERS

| Thing | Value |
|---|---|
| Plugin name | Pixel Scout |
| Slug | pixel-scout |
| PHP prefix | Pixel_Scout_ |
| Text domain | pixel-scout |
| Option prefix | ps_ |
| REST namespace | ps/v1 |
| Table | {prefix}ps_index |
| Shortcode | [ps_search] |
| React mount | #ps-root |
| React bundle | admin/dist/ps-admin.js + ps-admin.css |

---

## STACK

| Layer | Tech | Why |
|---|---|---|
| Backend | PHP 8.0+ | WP native |
| Image math | GD (built-in) | No install |
| DB | MySQL via $wpdb + Query builder | Custom table, JSON cols |
| Similarity | Pure PHP | Hamming + cosine |
| REST | WP REST API | Bridge PHP ↔ React |
| Admin UI | React 18 + TypeScript + Vite | Fast, typed dashboard |
| Component lib | shadcn/ui + Tailwind | iOS-feel, minimal |
| State | Zustand | Lightweight |
| Data fetch | TanStack Query | Cache + loading states |
| Search UI | Vanilla JS | No build step |
| Background | WP-Cron | Bulk index, no timeouts |

---

## HOW WORKS

3 fingerprints per image on upload:
1. **pHash** — 64-bit visual structure
2. **Color vector** — 48 floats, RGB distribution
3. **Edge vector** — 32 floats, shapes + texture

Query image → same 3 fingerprints → compare all stored → weighted score → rank → top 20.

---

## FINGERPRINT ALGORITHMS

### pHash
```
1. Resize → 32×32 grayscale
2. DCT on 32×32 grid
3. Top-left 8×8 = 64 coefficients
4. Mean of 64 values
5. Each val: 1 if > mean, 0 if below
6. Result: 64-bit → 16-char hex
Compare: Hamming distance. 0=identical. ≤10=very similar. ≤20=somewhat.
```

### Color Vector
```
1. Resize → 150×150
2. Each pixel: bucket R/G/B into 16 bins each
3. Count per bucket per channel → 3×16 = 48 floats
4. Normalize each channel sum → 1.0
Compare: Cosine similarity. ≥0.85=very similar. ≥0.65=somewhat.
```

### Edge Vector
```
1. Resize → 64×64 grayscale
2. Sobel: Gx=[[-1,0,1],[-2,0,2],[-1,0,1]] Gy=[[-1,-2,-1],[0,0,0],[1,2,3]]
3. Gradient magnitude per pixel: sqrt(Gx²+Gy²)
4. Divide 64×64 → 8×8 blocks = 64 regions
5. Avg magnitude per block → 64 floats
6. Reduce → 32 by avg adjacent pairs
7. Normalize 0.0–1.0
Compare: Cosine similarity. ≥0.80=very similar.
```

---

## SCORING FORMULA

```
Final = (0.40 × pHash_score) + (0.35 × color_score) + (0.25 × edge_score)

pHash_score = 1 - (hamming_dist / 64)
color_score = cosine(query_color, stored_color)
edge_score  = cosine(query_edge, stored_edge)

Range: 0.0→1.0. Return top 20 where score ≥ 0.40, sorted DESC.
Weights fixed. Not user-configurable.
```

---

## DB SCHEMA

```sql
CREATE TABLE {prefix}ps_index (
    id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    attachment_id  BIGINT(20) UNSIGNED NOT NULL,
    phash          CHAR(16)            NOT NULL DEFAULT '',
    color_vector   JSON                         DEFAULT NULL,
    edge_vector    JSON                         DEFAULT NULL,
    width          SMALLINT UNSIGNED            DEFAULT 0,
    height         SMALLINT UNSIGNED            DEFAULT 0,
    mime_type      VARCHAR(50)                  DEFAULT '',
    file_size      BIGINT UNSIGNED              DEFAULT 0,
    indexed_at     DATETIME                     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attachment_id (attachment_id),
    INDEX idx_phash (phash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ARCHITECTURE — DOMAIN-DRIVEN

3 feature domains. Each owns its concerns. Adding feature = add file to domain, nothing else breaks.

```
Infrastructure  →  shared by all (Query builder, Plugin bootstrap)
Imaging domain  →  pixel data, GD ops, math
Search domain   →  pipeline from query image to ranked results
Indexing domain →  ingestion, bulk jobs, progress tracking
Repository      →  DB access only, no logic
API / Hooks     →  wire WP into domains
Admin / Public  →  UI layers
```

### Domain boundaries
- Imaging: knows pixels. Knows nothing about WP, DB, search.
- Search: uses Imaging (via Factory). Knows nothing about bulk jobs.
- Indexing: uses Imaging (via Factory). Knows nothing about search pipeline.
- Repository: knows $wpdb + Query builder. Knows nothing about pixels or scoring.
- Hooks: knows WP hooks. Delegates to domain classes immediately.

### Dependency injection — constructor only
```php
class Pixel_Scout_Search_Pipeline {
    public function __construct(
        private Pixel_Scout_Index_Repository $repository,
        private Pixel_Scout_Fingerprint_Factory $factory,
        private Pixel_Scout_Score_Calculator $calculator,
        private Pixel_Scout_Similarity $similarity
    ) {}
}
```
No global state in services. No add_action inside domain classes.

---

## FILE STRUCTURE

```
pixel-scout/
│
├── pixel-scout.php
├── uninstall.php
│
├── includes/
│   │
│   ├── infrastructure/
│   │   ├── class-plugin.php              # Singleton. Wires all hooks. Instantiates all components.
│   │   ├── class-query.php               # Fluent query builder. SELECT+INSERT+UPDATE+DELETE+UPSERT+OR groups.
│   │   └── functions.php                 # ps_get_allowed_mime_types(), ps_format_filesize(). Prefixed globals.
│   │
│   ├── imaging/                          # Domain: pixel data, GD ops, math
│   │   ├── interface-processor.php       # process( resource $gd ): array
│   │   ├── class-gd-loader.php           # load( int $attachment_id ): resource|false
│   │   ├── class-phash-processor.php     # implements Processor_Interface
│   │   ├── class-color-processor.php     # implements Processor_Interface
│   │   ├── class-edge-processor.php      # implements Processor_Interface
│   │   └── class-similarity.php          # hamming_distance(), cosine_similarity(), combined_score()
│   │
│   ├── search/                           # Domain: query pipeline, scoring, results
│   │   ├── class-fingerprint-factory.php # Runs all processors → merged fingerprint array
│   │   ├── class-query-image.php         # Temp upload: save → fingerprint → delete
│   │   ├── class-score-calculator.php    # combined_score() with fixed weights 0.40/0.35/0.25
│   │   ├── class-search-pipeline.php     # Pre-filter → score → sort → hydrate → return
│   │   └── class-search-result.php       # Value object: id, url, thumbnail, title, score
│   │
│   ├── indexing/                         # Domain: ingestion, bulk, progress
│   │   ├── class-image-indexer.php       # index_single( int $id ), on_delete( int $id )
│   │   ├── class-bulk-indexer.php        # schedule(), process_batch( array $ids )
│   │   ├── class-index-progress.php      # get(), set( int $done, int $total ), reset()
│   │   └── class-mime-validator.php      # is_allowed( string $mime ): bool, get_allowed(): array
│   │
│   ├── repository/                       # Data access only. No logic.
│   │   ├── interface-repository.php      # upsert, get_all, get_paginated, get_counts, delete
│   │   ├── class-index-repository.php    # Implements interface via Query builder
│   │   └── class-schema.php              # install(), uninstall(), maybe_upgrade() via dbDelta()
│   │
│   ├── api/
│   │   ├── class-rest-controller.php     # register_routes(). All endpoints. Permission + sanitize callbacks.
│   │   └── class-rate-limiter.php        # is_allowed( string $ip ): bool. Transient 10/min/IP.
│   │
│   └── hooks/
│       ├── class-media-hooks.php         # add_attachment → Image_Indexer. delete_attachment → Repository.
│       ├── class-cron-handler.php        # ps_bulk_index_batch → Bulk_Indexer::process_batch
│       └── class-settings.php            # register_setting( 'ps_settings' ), sanitize_callback
│
├── admin/
│   ├── class-admin-page.php              # add_submenu_page under Media, enqueue, wp_localize_script
│   ├── class-settings-page.php           # Settings UI page registration
│   ├── views/
│   │   └── admin-root.php                # <div id="ps-root"> + nonce output. Zero logic.
│   └── app/
│       ├── package.json
│       ├── vite.config.ts
│       ├── index.html
│       └── src/
│           ├── main.tsx
│           ├── App.tsx
│           ├── store/
│           │   └── use-store.ts
│           ├── hooks/
│           │   ├── use-index-status.ts
│           │   ├── use-images.ts
│           │   └── use-reindex.ts
│           └── components/
│               ├── Dashboard.tsx
│               ├── StatsBar.tsx
│               ├── ImageTable.tsx
│               ├── ImageRow.tsx
│               ├── SearchPreview.tsx
│               ├── EmptyState.tsx
│               └── ReindexButton.tsx
│
├── public/
│   ├── class-shortcode.php
│   └── assets/
│       ├── js/search.js
│       └── css/search.css
│
├── languages/
│   └── pixel-scout.pot
│
└── admin/dist/                           # Git-ignored. Vite build output.
    ├── ps-admin.js
    └── ps-admin.css
```

---

## QUERY CLASS — class-query.php

Fluent builder. Wraps $wpdb. All writes go through it. Repository is only caller.

### SELECT
```php
Query::create()
    ->from( 'ps_index' )
    ->select( ['attachment_id', 'phash', 'color_vector', 'edge_vector'] )
    ->where( 'attachment_id', 42, '=', '%d' )
    ->get( ARRAY_A );
```

### OR groups
```php
Query::create()
    ->from( 'ps_index' )
    ->where( 'mime_type', 'image/gif', '!=' )
    ->or_where_group( function( $q ) {
        $q->where( 'phash', 'abc123', '=', '%s' );
        $q->where( 'phash', 'def456', '=', '%s' );
    })
    ->get();
// Produces: WHERE mime_type != %s AND (phash = %s OR phash = %s)
```

### INSERT
```php
Query::create()->from( 'ps_index' )->insert([
    'attachment_id' => 42,
    'phash'         => 'a1b2c3d4e5f6a1b2',
    'color_vector'  => wp_json_encode( $color ),
    'indexed_at'    => current_time( 'mysql' ),
]);
// Returns insert ID or false
```

### UPDATE
```php
Query::create()
    ->from( 'ps_index' )
    ->where( 'attachment_id', 42, '=', '%d' )
    ->update([ 'phash' => 'newvalue', 'indexed_at' => current_time( 'mysql' ) ]);
// Returns rows affected or false
```

### DELETE
```php
Query::create()
    ->from( 'ps_index' )
    ->where( 'attachment_id', 42, '=', '%d' )
    ->delete();
// Returns rows affected or false
```

### UPSERT
```php
Query::create()->from( 'ps_index' )->upsert(
    insert: [ 'attachment_id' => 42, 'phash' => 'abc...', 'indexed_at' => current_time( 'mysql' ) ],
    update: [ 'phash', 'indexed_at' ]
);
// INSERT … ON DUPLICATE KEY UPDATE
```

### No static cache in Query
Cache lives in Repository. Uses wp_cache_get/set/delete. Caller controls TTL and invalidation.
```php
// Repository caches
$cached = wp_cache_get( 'ps_all_indexed', 'pixel-scout' );
if ( false !== $cached ) return $cached;
$results = Query::create()->from( 'ps_index' )->get( ARRAY_A );
wp_cache_set( 'ps_all_indexed', $results, 'pixel-scout', 5 * MINUTE_IN_SECONDS );

// Repository invalidates after write
public function upsert( int $id, array $fp ): bool {
    $result = Query::create()->from( 'ps_index' )->upsert( ... );
    wp_cache_delete( 'ps_all_indexed', 'pixel-scout' );
    return $result;
}
```

### Query methods
```
select( string|array )        → fields
from( string, string $alias ) → table (auto-prefixes)
from_subquery( Query, alias ) → subquery as FROM
inner_join( table, condition, alias )
left_join( table, condition, alias )
where( column, value, op, type )
where_between( column, start, end, type )
where_raw( string, array )
where_date_range( column, start_ts, end_ts, inclusive )
where_in( column, array, type )
where_not_in( column, array, type )
or_where_group( callable )    → groups conditions with OR
group_by( column )
having( condition, value, op, type )
order_by( column, direction )
limit( int )
offset( int )
paginate( page, per_page )
no_cache()                    → skip wp_cache_get for this call
build_sql(): string
get( output )                 → array|null
get_row( output )             → mixed
get_var( col_offset )         → mixed
get_col( col_offset )         → array|null
insert( array ): int|false
update( array ): int|false
delete(): int|false
upsert( insert, update ): bool
```

---

## WORDPRESS CODING STANDARDS — FULL

Every file follows these. No exceptions.

### Naming
```
Classes:   Pixel_Scout_Index_Repository, Pixel_Scout_Search_Pipeline
Methods:   snake_case — get_all_indexed(), compute_phash(), is_allowed()
Constants: PIXEL_SCOUT_VERSION, PIXEL_SCOUT_PLUGIN_DIR, PIXEL_SCOUT_TABLE
Hooks:     ps_bulk_index_batch, ps_after_index, ps_search_results
Options:   ps_settings, ps_bulk_progress, ps_bulk_total
```

### Sanitize all input
```
File uploads     → wp_check_filetype() + mime whitelist via Mime_Validator
REST params      → sanitize_text_field(), absint(), wp_unslash()
Query vars       → sanitize_key(), absint()
```

### Escape all output
```
HTML             → esc_html(), esc_attr()
URLs             → esc_url()
JSON (REST)      → wp_json_encode()
wp_localize_script data → escaped in PHP before passing
```

### i18n every user-facing string
```php
__( 'Indexed', 'pixel-scout' )
_n( '%d image', '%d images', $count, 'pixel-scout' )
esc_html__( 'Something went wrong.', 'pixel-scout' )
```

### Nonces
```
Admin REST calls  → wp_nonce_field() in view, check nonce in controller
Frontend shortcode → separate nonce via wp_localize_script
```

### Capability checks
```
All admin REST endpoints → current_user_can( 'manage_options' )
Public search            → check ps_settings option: anyone vs logged_in
```

### Hook binding — hooks/ classes only
```php
// CORRECT — in class-media-hooks.php
class Pixel_Scout_Media_Hooks {
    public function register(): void {
        add_action( 'add_attachment', [ $this, 'on_upload' ] );
        add_action( 'delete_attachment', [ $this, 'on_delete' ] );
    }
}

// WRONG — never add_action inside a service or domain class
```

---

## IMAGING DOMAIN — class responsibilities

### interface-processor.php
```
process( resource $gd, int $attachment_id ): array
```

### class-gd-loader.php
```
load( int $attachment_id ): resource|false
  → get_attached_file() → check mime → imagecreatefromjpeg/png/gif/webp → return resource
destroy( resource $gd ): void
  → imagedestroy()
```

### class-phash-processor.php
```
implements Processor_Interface
process(): array → ['phash' => '16-char-hex']
  → resize 32×32 gray → DCT → threshold vs mean → hex
```

### class-color-processor.php
```
implements Processor_Interface
process(): array → ['color_vector' => [48 floats]]
  → resize 150×150 → RGB 16-bucket histogram → normalize
```

### class-edge-processor.php
```
implements Processor_Interface
process(): array → ['edge_vector' => [32 floats]]
  → resize 64×64 gray → Sobel → 8×8 blocks → normalize
```

### class-similarity.php
```
hamming_distance( string $h1, string $h2 ): int     → 0-64
cosine_similarity( array $a, array $b ): float      → 0.0-1.0, div-zero guard
```

---

## SEARCH DOMAIN — class responsibilities

### class-fingerprint-factory.php
```
__construct( Processor_Interface ...$processors )
generate( int $attachment_id ): array
  → GD_Loader::load() → run each processor → merge arrays → GD_Loader::destroy()
```
Adding processor = pass new instance to constructor. Nothing else changes.

### class-query-image.php
```
from_upload( array $file ): int
  → validate mime → wp_handle_upload() → insert attachment → return attachment_id
cleanup( int $attachment_id ): void
  → wp_delete_attachment( $id, true )
```

### class-score-calculator.php
```
PHASH_WEIGHT = 0.40
COLOR_WEIGHT = 0.35
EDGE_WEIGHT  = 0.25

calculate( array $query_fp, array $stored_fp ): float
  → pHash_score = 1 - (hamming / 64)
  → color_score = cosine( query_color, stored_color )
  → edge_score  = cosine( query_edge, stored_edge )
  → return weighted sum
```

### class-search-pipeline.php
```
search( int $attachment_id, int $limit = 20 ): array
  1. Fingerprint_Factory::generate( $attachment_id )
  2. Repository::get_all_indexed()
  3. Pre-filter: skip if hamming_distance > 30
  4. Score_Calculator::calculate() on remaining
  5. Filter score < 0.40
  6. usort DESC → array_slice $limit
  7. Hydrate: wp_get_attachment_image_src + get_the_title
  8. Return Search_Result[]
```

### class-search-result.php
```
Value object. readonly properties:
  int $attachment_id
  string $url
  string $thumbnail
  string $title
  float $score
```

---

## INDEXING DOMAIN — class responsibilities

### class-mime-validator.php
```
is_allowed( string $mime ): bool
get_allowed(): array → ['image/jpeg','image/png','image/gif','image/webp']
```

### class-image-indexer.php
```
index_single( int $attachment_id ): bool
  → Mime_Validator::is_allowed() → Factory::generate() → Repository::upsert()
on_delete( int $attachment_id ): bool
  → Repository::delete( $attachment_id )
```

### class-bulk-indexer.php
```
schedule(): void
  → Repository::get_unindexed_ids()
  → chunk 50 → wp_schedule_single_event 60s apart
  → set transient ps_bulk_total

process_batch( array $ids ): void
  → foreach: Image_Indexer::index_single()
  → Index_Progress::increment()
```

### class-index-progress.php
```
get(): array → ['done' => int, 'total' => int]
set( int $done, int $total ): void
increment(): void
reset(): void
All via transients: ps_bulk_progress, ps_bulk_total
```

---

## REPOSITORY — class responsibilities

### interface-repository.php
```
upsert( int $attachment_id, array $fingerprint ): bool
get_all_indexed(): array
get_paginated( int $page, int $per_page, string $search ): array
get_counts(): array  → ['total' => int, 'indexed' => int, 'pending' => int]
get_unindexed_ids(): array
delete( int $attachment_id ): bool
```

### class-index-repository.php
```
Implements Index_Repository_Interface.
Uses Query builder only. Zero direct $wpdb calls.
wp_cache_get/set/delete for read caching.
Invalidates cache on every write.
```

### class-schema.php
```
install(): void    → dbDelta() to create ps_index table
uninstall(): void  → $wpdb->query DROP TABLE
maybe_upgrade(): void → check PIXEL_SCOUT_DB_VERSION option → run migrations
```

---

## REST API ENDPOINTS

```
POST   ps/v1/search          Upload query image → ranked results. Rate limited.
GET    ps/v1/status          Indexed/total counts + last run. Admin only.
GET    ps/v1/images          Paginated indexed list for React table. Admin only.
POST   ps/v1/reindex         Trigger Bulk_Indexer::schedule(). Admin only.
GET    ps/v1/progress        Transient progress for polling. Admin only.
DELETE ps/v1/index/(?P<id>\d+)  Remove single from index. Admin only.
```

### class-rest-controller.php
```
register_routes(): void  → called on rest_api_init
Each endpoint defines:
  - methods
  - callback
  - permission_callback → current_user_can() or public check
  - args with sanitize_callback and validate_callback
```

### class-rate-limiter.php
```
is_allowed( string $ip ): bool
  → transient key: ps_rl_ . md5($ip)
  → max 10 per 60s
  → returns false if limit hit
```

---

## ADMIN SETTINGS — iOS APPROACH

```
⚙️ Settings — one card, one choice:

  Search visibility
  ○ Anyone can search      ← default (ps_settings option)
  ○ Logged-in users only

Done. No weight sliders. No threshold inputs. No batch size.
```

### class-settings.php (hooks/)
```
register(): void
  → register_setting( 'ps_settings', 'ps_settings', [ 'sanitize_callback' => ... ] )
  → add_settings_section()
  → add_settings_field()
sanitize( array $input ): array
  → validate search_visibility is in ['anyone', 'logged_in']
get_visibility(): string → get_option default 'anyone'
```

---

## REACT APP

### wp_localize_script data
```js
window.ps_data = {
    rest_url: 'https://site.com/wp-json/ps/v1/',
    nonce:    '...',
    is_admin: true|false
}
```

### vite.config.ts
```ts
export default {
    build: {
        outDir: '../../dist',
        rollupOptions: {
            input: 'src/main.tsx',
            output: {
                entryFileNames: 'ps-admin.js',
                assetFileNames: 'ps-admin.[ext]'
            }
        }
    }
}
```

### Component tree
```
App.tsx
└── Dashboard.tsx
    ├── StatsBar.tsx          GET /status → 3 cards: Total, Indexed, Pending
    ├── ImageTable.tsx        GET /images → paginated, sortable, filterable
    │   └── ImageRow.tsx
    ├── ReindexButton.tsx     POST /reindex → polls GET /progress every 2s
    ├── SearchPreview.tsx     POST /search → results grid with score %
    └── EmptyState.tsx        When 0 indexed
```

---

## iOS DESIGN PHILOSOPHY — RULES

Apply everywhere, React + frontend:

```
1. ONE primary action per screen. Never two CTAs competing.
2. No settings user doesn't need. Defaults correct.
3. No confirmation dialogs except destructive actions.
4. System handles complexity. User sees simplicity.
5. Motion functional, not decorative.
6. White space is breathing room, not empty.
7. Labels are nouns not instructions. "Images" not "Click to see images".
8. Errors explain what happened + what to do. Never just a code.
9. Loading states always. Never blank screen.
10. Icons support text. Never replace it.
```

---

## DESIGN SYSTEM — REACT DASHBOARD

```
Philosophy: iOS + Linear.app + Apple System Settings
Palette:
  bg       #FFFFFF
  surface  #F5F5F7
  text     #1D1D1F
  muted    #6E6E73
  accent   #0071E3  (Apple blue)
  danger   #FF3B30
  success  #34C759  (Apple green)
  warning  #FF9500  (Apple orange)

Font:   -apple-system, BlinkMacSystemFont, "SF Pro Display"
Radius: 12px cards. 8px inputs. 20px pills.
Shadow: 0 1px 3px rgba(0,0,0,0.08) only.
Border: 1px solid #E5E5EA.
Motion: 200ms ease-out. Scale 0.98 on press.
```

### Stats Bar
```
3 cards horizontal. Numbers only. No charts.
[ Total Images ] [ Indexed ] [ Pending ]
     1,204          842         362
```

### Image Table
```
Columns: Thumbnail (48×48 rounded) | File Name | Dimensions | Size | Indexed At | Status

Status pill:
  ● Indexed  → #34C759 bg
  ● Pending  → #FF9500 bg
  ● Failed   → #FF3B30 bg

Features:
  - Search by filename (instant, client-side)
  - Sort by name/size/date (click header)
  - 25 per page pagination
  - Row click → opens WP media item in new tab
  - Bulk select → re-index selected (non-indexed only)

NO: export, column toggle, density controls.
```

### Re-index Card
```
State 1 — idle:
  "842 of 1,204 images indexed"
  [Index Remaining 362 →]

State 2 — running:
  Thin blue progress bar (Apple-style)
  "Indexing... 124 of 362"
  [Cancel]

No batch size setting. No concurrency setting.
```

### Search Preview
```
Drop zone: "Drop an image to test search"
POST /search → skeleton 3 cards → results grid
Score as % badge. e.g. "94% match"
No results: "No similar images found. Try a different image."
```

---

## FRONTEND SHORTCODE

```
[ps_search]

States:
  1. Idle     — drop zone + upload icon + "Find similar images"
  2. Loading  — shimmer skeleton grid 3 cards
  3. Results  — 4-col grid, thumbnail + title + score % pill (#0071E3 bg)
  4. No match — "No similar images found"
  5. Error    — "Something went wrong. Try a different image."

wp_localize_script: ps_public = { rest_url, nonce }
NO filters. NO sort. NO result count selector.
```

---

## PERFORMANCE

| Library size | Search time | Notes |
|---|---|---|
| < 1,000 | < 100ms | No optimization needed |
| 1,000–5,000 | < 500ms | pHash pre-filter handles it |
| 5,000–10,000 | 500ms–2s | Add DB pHash bucketing |
| 10,000+ | > 2s | Migrate → Qdrant vector DB |

pHash pre-filter (hamming > 30 skip) eliminates ~70-80% before cosine math.

---

## HOW TO ADD NEW FEATURE (scalability proof)

### New imaging processor (e.g. aspect ratio bucketing)
```
1. Create imaging/class-aspect-ratio-processor.php implements Processor_Interface
2. Register in Fingerprint_Factory constructor
3. Add column to schema in class-schema.php
4. Done. Nothing else touched.
```

### New search mode (e.g. color-only search)
```
1. Create search/class-color-search-pipeline.php
2. Add endpoint in class-rest-controller.php
3. Done. Imaging and indexing untouched.
```

### New indexing trigger (e.g. re-index on image edit)
```
1. Add hook in hooks/class-media-hooks.php for wp_update_attachment_metadata
2. Delegate to Image_Indexer::index_single()
3. Done. Search and imaging untouched.
```

---

## BUILD PHASES

---

## PHASE 1 VERIFICATION

Phase 1 includes a **smoke test** to validate Query builder and schema without WordPress Admin.

### Running the smoke test

```bash
cd /path/to/wordpress/wp-content/plugins/pixel-scout
wp eval-file includes/test-phase1.php
```

Tests:
1. Schema install (creates `ps_index` table)
2. Query upsert (insert fingerprint)
3. Query get (retrieve indexed records)
4. Query counts (total/indexed/pending)
5. Query delete (cleanup test row)

### Activation logging

With `WP_DEBUG` enabled, activation logs to debug.log:
```
[Pixel Scout] Activation hook triggered.
[Pixel Scout] Schema installed successfully.
[Pixel Scout] Plugin activation complete.
```

Same logging on deactivation and uninstall.

---

### Phase 1 — Infrastructure + Schema
```
pixel-scout.php         plugin header, constants, autoloader
class-plugin.php        singleton bootstrap
class-query.php         full query builder (SELECT+INSERT+UPDATE+DELETE+UPSERT+OR)
class-schema.php        install, uninstall, maybe_upgrade
interface-repository.php
class-index-repository.php

Test: Run smoke test: `wp eval-file includes/test-phase1.php`
      or activate plugin in Admin → check debug.log for activation logs.
```

### Phase 2 — Imaging Domain
```
interface-processor.php
class-gd-loader.php
class-phash-processor.php
class-color-processor.php
class-edge-processor.php
class-similarity.php
class-fingerprint-factory.php  (lives in search/ but uses imaging/)

Test: upload 1 image → factory generates fingerprint → all 3 values stored in DB.
```

### Phase 3 — Search Domain
```
class-query-image.php
class-score-calculator.php
class-search-pipeline.php
class-search-result.php

Test: query similar image → correct top results. Query unrelated → score < 0.40, no results.
```

### Phase 4 — Indexing Domain
```
class-mime-validator.php
class-image-indexer.php
class-index-progress.php
class-bulk-indexer.php
class-media-hooks.php    (hooks/)
class-cron-handler.php   (hooks/)

Test: upload image → auto-indexed. Delete image → removed from index. Bulk: 50-image batch processes via cron.
```

### Phase 5 — REST API
```
class-rest-controller.php
class-rate-limiter.php
class-settings.php

Test: Postman all endpoints. search returns ranked JSON. status returns counts. reindex triggers cron.
```

### Phase 6 — React Dashboard
```
class-admin-page.php
admin/views/admin-root.php
React app: Vite + TypeScript setup, all components

Test: dashboard loads, table shows images, stats accurate, reindex triggers + progress polls correctly.
```

### Phase 7 — Frontend Shortcode
```
class-shortcode.php
public/assets/js/search.js
public/assets/css/search.css

Test: place on page, upload image, results render, all 5 states work.
```

### Phase 8 — Hardening
```
Rate limiting verified (10 req/min per IP)
Input validation: file type, size, dimensions
Error handling: corrupt images, unsupported formats
Nonce verification on all state-changing requests
Capability checks on all admin endpoints
i18n: all user strings wrapped
Performance test: 500 images → search < 500ms. 5000 images → search < 2s.
```

---

## SESSION PROMPTS — PREAMBLE (add to every session)

```
Plugin: Pixel Scout. Slug: pixel-scout. PHP prefix: Pixel_Scout_.
Text domain: pixel-scout. Option prefix: ps_. REST namespace: ps/v1.
Table: {prefix}ps_index.

Architecture: Domain-driven. 3 domains: imaging/, search/, indexing/.
Infrastructure: infrastructure/ (Query builder, Plugin class, functions).
Repository: repository/ (DB access only, uses Query builder, no logic).
API: api/ + hooks/ (wire WP into domains only, no business logic).

Rules:
- No $wpdb outside repository classes
- No add_action inside domain or service classes
- No business logic in repository classes
- Constructor DI only, no global state in services
- Full WPCS: snake_case methods, esc_* all output, sanitize_* all input
- __() all user strings with text domain pixel-scout
- Nonce verify all state-changing requests
- current_user_can() all admin endpoints
- GD only, no Imagick
- No composer packages
- Weights fixed (0.40/0.35/0.25), not configurable
- No .md files, no phpcs inline, no commits
```

---

## NEVER

```
- Use Imagick (GD only)
- Install composer packages
- Add user-configurable algorithm weights
- Add unnecessary settings
- Create .md files (except this one)
- Run phpcs
- Commit anything
- add_action inside domain/service classes
- $wpdb calls outside repository classes
- Business logic inside repository classes
- Static cache in Query class (use wp_cache_*)
```

---

## DONE WHEN

```
✅ Upload image → fingerprints in DB in < 2s
✅ Search similar → correct top 5 results
✅ Search unrelated → score < 0.40, no false positives
✅ 500-image library → search < 500ms
✅ 5000-image library → search < 2s
✅ Bulk index 500 images → via WP-Cron, no timeout
✅ React dashboard → table loads, stats accurate, reindex works
✅ Shortcode → works on any page, no login if public enabled
✅ iOS feel → one CTA per screen, no unnecessary choices, clean white UI
✅ New processor → add 1 file, nothing else breaks
✅ All WPCS → naming, i18n, nonces, caps, sanitize, escape
```