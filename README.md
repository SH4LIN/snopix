# Pixel Scout

Image similarity search for WordPress media library.

**Version:** 0.1.0 (Phase 1 - Infrastructure)  
**Requires:** WordPress 6.0+, PHP 8.0+  
**License:** GPLv2 or later

---

## Quick start

### Installation

1. Clone/download into `wp-content/plugins/pixel-scout`
2. Activate plugin in WordPress Admin
3. Or use WP-CLI: `wp plugin activate pixel-scout`

### First-time verification

After activation, run Phase 1 smoke test:

```bash
wp eval-file wp-content/plugins/pixel-scout/includes/test-phase1.php
```

Expected output: all 5 tests pass, table created, sample record inserted/deleted.

See [PHASE1_VERIFICATION.md](./PHASE1_VERIFICATION.md) for details.

---

## Testing

Pixel Scout includes comprehensive unit tests (PHPUnit + wp-phpunit) and E2E tests (Playwright).

### Quick start

```bash
# Install all dependencies
./setup-tests.sh

# Run all tests
composer test
npm run test:e2e
```

### Unit tests (Phase 1 ✅)

- Query builder: 25+ tests covering SELECT, INSERT, UPDATE, DELETE, UPSERT, joins, pagination
- Schema manager: 12+ tests for table creation, upgrades, idempotency
- Repository: 20+ tests for data access, caching, concurrent operations
- Plugin lifecycle: 8+ tests for activation, deactivation, uninstall

Run with:
```bash
composer test                # All tests
composer test-coverage       # With coverage report
composer lint                # Code standards (PHPCS + WPCS)
```

### E2E tests (Phases 6–7)

- Admin dashboard tests (skipped until Phase 6)
- Frontend shortcode tests (skipped until Phase 7)
- Responsive design tests

Run with:
```bash
npm run test:e2e             # All tests
npm run test:e2e:ui          # Interactive UI
npm run test:e2e:headed      # Visible browser
```

See [TESTING.md](./TESTING.md) for comprehensive testing guide.

---

## Architecture

**Domain-driven design** with 3 core business domains:

- **Imaging** — Pixel data, GD operations, fingerprint math
- **Search** — Query pipeline, similarity scoring, result ranking
- **Indexing** — Ingestion, bulk jobs, progress tracking

Plus supporting layers:

- **Infrastructure** — Query builder, plugin bootstrap, utilities
- **Repository** — Database access only, zero business logic
- **Hooks** — WordPress integration, event wiring only
- **API** — REST endpoints + rate limiting
- **Admin/Public** — UI layers (React dashboard + vanilla shortcode)

See [plan.md](./plan.md) for full specifications.

---

## File structure

```
pixel-scout/
├── pixel-scout.php                plugin header, constants, autoloader
├── uninstall.php                  cleanup on plugin removal
├── plan.md                         full project spec
├── PHASE1_VERIFICATION.md         smoke test docs
├── .gitignore                      build output, deps
│
├── includes/
│   ├── infrastructure/            plugin bootstrap, query builder
│   │   ├── class-plugin.php
│   │   ├── class-query.php        fluent SQL builder
│   │   └── functions.php          helper functions
│   │
│   ├── repository/                DB layer only
│   │   ├── class-schema.php       table creation
│   │   ├── class-index-repository.php
│   │   └── interface-repository.php
│   │
│   ├── imaging/                   (Phase 2) pixel algorithms
│   ├── search/                    (Phase 3) similarity pipeline
│   ├── indexing/                  (Phase 4) bulk indexing
│   ├── api/                       (Phase 5) REST endpoints
│   └── hooks/                     (Phase 5) WordPress integration
│
├── admin/                         (Phase 6) WordPress admin
│   ├── class-admin-page.php
│   ├── views/admin-root.php
│   └── app/                       React app (TypeScript)
│       ├── vite.config.ts
│       ├── src/
│       │   ├── main.tsx
│       │   ├── App.tsx
│       │   ├── components/
│       │   ├── hooks/
│       │   └── store/
│       └── dist/                  (git-ignored) Vite build output
│
├── public/                        (Phase 7) frontend shortcode
│   ├── class-shortcode.php
│   └── assets/
│       ├── js/search.js
│       ├── css/search.css
│
└── languages/                     i18n translations
    └── pixel-scout.pot
```

---

## Development workflow

### Currently building (Phase 1 ✅)

- [x] Plugin bootstrap, constants, autoloader
- [x] Infrastructure: Query builder, Plugin class, helpers
- [x] Repository layer: interface, implementation, schema
- [x] Smoke test script + WP_DEBUG logging
- [x] Updated plan.md for TypeScript/TSX

### Next: Phase 2 (Imaging domain)

- [ ] GD loader
- [ ] pHash processor (DCT + fingerprinting)
- [ ] Color vector processor (RGB histogram)
- [ ] Edge vector processor (Sobel gradient)
- [ ] Similarity helpers (Hamming, cosine distance)

### Testing

Phase 1 smoke test validates Query builder and schema:

```bash
# WP-CLI
wp eval-file wp-content/plugins/pixel-scout/includes/test-phase1.php

# Or activate in WordPress Admin and check wp-content/debug.log
```

No external dependencies (no Composer, no NPM yet).

---

## Coding standards

All code follows **WordPress Coding Standards (WPCS)**. Run linter:

```bash
composer lint         # Summary
composer lint-fix     # Auto-fix
composer lint-strict  # Including WordPress-Extra
```

All PHP code follows **WordPress Coding Standards (WPCS)**:

- Class names: `Pixel_Scout_Module_Class`
- Methods: `snake_case()`
- Constants: `PIXEL_SCOUT_VERSION`
- Hooks: `ps_action_name`, `ps_filter_name`
- Options: `ps_settings`, `ps_bulk_progress`
- Database table: `{prefix}ps_index`
- REST namespace: `ps/v1`
- Text domain: `pixel-scout`

All user-facing strings are i18n-wrapped with `__()`, `esc_html__()`, etc.

---

## Performance targets

- Upload image → fingerprints in DB: **< 2s**
- Search 500 images: **< 500ms**
- Search 5,000 images: **< 2s**
- Bulk index 500 images via WP-Cron: **no timeout**

---

## Roadmap

| Phase | Focus | Status |
|-------|-------|--------|
| 1 | Infrastructure (Query, schema, plugin bootstrap) | ✅ Done |
| 2 | Imaging domain (GD, fingerprinting, similarity math) | — |
| 3 | Search domain (pipeline, scoring, results ranking) | — |
| 4 | Indexing domain (bulk jobs, progress) | — |
| 5 | REST API + hooks + settings | — |
| 6 | React admin dashboard (TypeScript) | — |
| 7 | Frontend shortcode search UI | — |
| 8 | Hardening + performance tuning | — |

---

## Contributing

- Follow WPCS on every file
- Escape all output, sanitize all input
- Constructor DI only, no globals in services
- No `$wpdb` calls outside Repository classes
- No `add_action` inside domain/service classes
- Test via the Phase 1 smoke script before committing

---

## License

GNU General Public License v2 or later


