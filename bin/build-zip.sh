#!/usr/bin/env bash
#
# Build a WordPress.org-deployable zip of the Pixel Scout plugin.
#
# Usage:
#   bin/build-zip.sh [version]
#
# - `version` is optional. When omitted, it is read from the plugin header
#   in pixel-scout.php. When provided, it overrides the header value.
# - The zip is written to build/<slug>-<version>.zip.
# - Files excluded from the zip are read from .distignore at the repo root.

set -euo pipefail

PLUGIN_SLUG="pixel-scout"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/build"
STAGING_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ADMIN_APP_DIR="$ROOT_DIR/admin/app"
DISTIGNORE="$ROOT_DIR/.distignore"
PLUGIN_FILE="$ROOT_DIR/pixel-scout.php"

log() { printf '[build-zip] %s\n' "$*"; }

# --- preflight ------------------------------------------------------------
for cmd in rsync zip npm grep sed; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "Required command not found: $cmd" >&2
        exit 1
    fi
done

[ -f "$PLUGIN_FILE" ]   || { echo "Missing $PLUGIN_FILE" >&2; exit 1; }
[ -f "$DISTIGNORE" ]    || { echo "Missing $DISTIGNORE" >&2; exit 1; }
[ -d "$ADMIN_APP_DIR" ] || { echo "Missing $ADMIN_APP_DIR" >&2; exit 1; }

# --- resolve version ------------------------------------------------------
override_version="${1:-}"
header_version="$(
    grep -E "^\s*\*\s*Version:" "$PLUGIN_FILE" \
        | head -n1 \
        | sed -E 's/.*Version:[[:space:]]*//' \
        | tr -d '\r' \
        | xargs
)"

if [ -n "$override_version" ]; then
    VERSION="$override_version"
else
    VERSION="$header_version"
fi

if [ -z "$VERSION" ]; then
    echo "Could not resolve plugin version." >&2
    exit 1
fi

# Sanitise: only [A-Za-z0-9._-] survive — anything else becomes "-".
VERSION="$(printf '%s' "$VERSION" | tr -c 'A-Za-z0-9._-' '-')"

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$BUILD_DIR/$ZIP_NAME"

log "Plugin: $PLUGIN_SLUG"
log "Version: $VERSION"
log "Output: $ZIP_PATH"

# --- build admin app ------------------------------------------------------
log "Building admin app (npm ci && npm run build)"
(
    cd "$ADMIN_APP_DIR"
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi
    npm run build
)

if [ ! -d "$ADMIN_APP_DIR/dist" ] || [ -z "$(ls -A "$ADMIN_APP_DIR/dist" 2>/dev/null)" ]; then
    echo "admin/app/dist is empty after build — refusing to ship a zip without the bundle." >&2
    exit 1
fi

# --- stage ----------------------------------------------------------------
log "Staging into $STAGING_DIR"
rm -rf "$BUILD_DIR"
mkdir -p "$STAGING_DIR"

# Always exclude the build dir itself so a re-run doesn't recurse.
rsync -a \
    --exclude-from="$DISTIGNORE" \
    --exclude="build" \
    "$ROOT_DIR"/ "$STAGING_DIR"/

# --- safety checks --------------------------------------------------------
for forbidden in node_modules vendor tests .git .github composer.json package.json README.md; do
    if [ -e "$STAGING_DIR/$forbidden" ]; then
        echo "FATAL: $forbidden leaked into the staging directory." >&2
        exit 1
    fi
done

for required in pixel-scout.php uninstall.php readme.txt includes admin/app/dist; do
    if [ ! -e "$STAGING_DIR/$required" ]; then
        echo "FATAL: required path missing from staging: $required" >&2
        exit 1
    fi
done

# --- zip ------------------------------------------------------------------
log "Creating $ZIP_NAME"
(cd "$BUILD_DIR" && zip -rq "$ZIP_NAME" "$PLUGIN_SLUG")

# Drop the staging tree once the zip exists — build/ should contain only the
# release artifact.
log "Removing staging directory $STAGING_DIR"
rm -rf "$STAGING_DIR"

log "Done."
log "Zip:  $ZIP_PATH"
log "Size: $(du -h "$ZIP_PATH" | cut -f1)"

# Emit machine-readable outputs when invoked from GitHub Actions.
if [ -n "${GITHUB_OUTPUT:-}" ]; then
    {
        echo "zip_path=build/$ZIP_NAME"
        echo "zip_name=$ZIP_NAME"
        echo "version=$VERSION"
    } >> "$GITHUB_OUTPUT"
fi
