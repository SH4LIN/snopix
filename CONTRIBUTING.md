# Contributing to Pixel Scout

Thanks for taking the time to contribute. This document covers the dev
environment, the conventions the codebase follows, and the checks every
pull request must pass.

---

## Requirements

* PHP 8.0 or newer
* Composer 2
* Node.js 20 + npm
* MySQL 5.7+ (for the PHPUnit suite — handled by the CI service container,
  or via `wp-env` locally)

The plugin uses its own PSR-4-ish autoloader (`includes/infrastructure/class-autoloader.php`),
so Composer is only needed for dev dependencies (PHPUnit, PHPCS, PHPStan).

---

## Bootstrapping a local dev environment

```bash
git clone <repo> wp-content/plugins/pixel-scout
cd wp-content/plugins/pixel-scout

composer install
( cd admin/app && npm ci && npm run build )
```

The optional `setup-tests.sh` script bootstraps a `wp-env`-based WordPress
test environment with the test database wired up. Use it if you want
PHPUnit to run locally with the same WP scaffold CI uses:

```bash
./setup-tests.sh
```

---

## Running the test suite

```bash
composer test            # PHPUnit (units + integration)
composer lint            # PHPCS (WordPress Coding Standards)
composer analyse         # PHPStan level 5

npm --prefix admin/app run lint
npx playwright test      # End-to-end (Playwright)
```

All four must pass before opening a PR. The PHPUnit tests rely on the
`wp-phpunit` scaffold installed via Composer and a MySQL database — see
`wp-tests-config.php` for the env vars it reads, and `.github/workflows/ci.yml`
for the CI invocation.

The reverse-image-search regression matrix lives under
`tests/fixtures/images/run_search_tests.py`. Re-run it whenever you change
fingerprint generation, scoring weights, or threshold constants:

```bash
python3 tests/fixtures/images/generate_variations.py     # builds variants
python3 tests/fixtures/images/run_search_tests.py        # exercises /search
```

---

## Branching & commits

* Base feature work on `development`. Long-running rewrites can branch off
  `main` when explicitly coordinated.
* One concern per pull request. Avoid bundling refactors with feature work
  or vice versa.
* Conventional, imperative commit subjects (`Fix BMP MIME allowlist`,
  `Raise Hamming threshold to 20`). Keep them under ~72 characters and
  explain *why* in the body when the *what* is not obvious.

---

## Coding standards (PHP)

* WordPress Coding Standards. Run `composer lint`. Auto-fixable nits with
  `composer lint-fix`.
* Escape every echoed value; sanitise every piece of input on the boundary.
* Constructor dependency injection only — no `global`, no service locators
  inside domain classes.
* `$wpdb` calls live in `includes/repository/` and nowhere else.
* Don't register `add_action` / `add_filter` from domain or service
  classes; hook wiring belongs in `includes/hooks/` or
  `Plugin::register()`.
* Every public function and method carries a WordPress-style PHPDoc with
  `@param` and `@return` tags (see existing files for the house style).

### Naming conventions used in this codebase

| Element | Convention | Example |
| --- | --- | --- |
| Classes | `PixelScout\<Domain>\<Class_Name>` | `PixelScout\Search\Search_Pipeline` |
| Methods | `snake_case` | `handle_search()` |
| Constants | `PIXEL_SCOUT_*` | `PIXEL_SCOUT_VERSION` |
| Hooks | `ps_<action_or_filter>` | `ps_after_index` |
| Options / transients | `ps_*` | `ps_bulk_progress` |
| DB tables | `{prefix}ps_*` | `wp_ps_index` |
| REST namespace | `ps/v1` | `/wp-json/ps/v1/search` |
| Text domain | `pixel-scout` | `__( 'Indexing…', 'pixel-scout' )` |

---

## Coding standards (TypeScript / React)

* The admin app lives in `admin/app/`. Source is TypeScript + React 18 with
  TanStack Query, TanStack Router, and Zustand.
* `npm --prefix admin/app run lint` must pass (ESLint flat config +
  Prettier).
* Every exported component, hook, and helper carries a JSDoc block with
  `@param` and `@return` annotations. Match the house style in
  `admin/app/src/components/`.
* `npm --prefix admin/app run build` must succeed — the GitHub Actions
  release workflow refuses to package a zip if `admin/app/dist/` is empty
  after the build step.

---

## Test expectations

* New public PHP behaviour ships with PHPUnit coverage in `tests/unit/`.
* New REST endpoints get at least one happy-path test plus one failure
  test (auth or validation).
* UI-only changes need a corresponding Playwright spec in `tests/e2e/`.
* If you change fingerprint math, scoring weights, or any threshold
  constant in `class-search-pipeline.php`, re-run the search test matrix
  and update `TEST_REPORT.md` with the post-change numbers.

---

## Pull request checklist

Before requesting review, confirm:

* [ ] `composer lint` passes
* [ ] `composer analyse` passes
* [ ] `composer test` passes on PHP 8.1 locally
* [ ] `npm --prefix admin/app run lint` passes
* [ ] `npm --prefix admin/app run build` succeeds
* [ ] New / changed functions carry PHPDoc or JSDoc with `@param` and
      `@return`
* [ ] User-facing strings are wrapped in `__()` / `esc_html__()` with the
      `pixel-scout` text domain
* [ ] No `$wpdb` outside `includes/repository/`
* [ ] No new top-level files end up in the release zip — re-run
      `bash bin/build-zip.sh` and inspect the staging tree if you added
      anything at the repo root

CI runs the first three checks plus PHPUnit on a PHP 8.0 / 8.1 / 8.2 / 8.3
matrix on every PR to `main` and `development`. PRs that fail CI will not
be merged.

---

## Reporting bugs

When the search or duplicate detector misranks an obvious case, please
open an issue with:

1. The source attachment (URL or attached file).
2. The probe image you uploaded.
3. The full JSON response from `POST /wp-json/ps/v1/search` or the
   `/duplicates` payload.
4. Plugin version, WordPress version, and PHP version.

Threshold tuning is an ongoing effort and concrete failure cases are the
fastest way to improve ranking.

---

## License

By contributing to Pixel Scout you agree that your contributions will be
licensed under the GNU General Public License v2 or later, matching the
plugin's license.
