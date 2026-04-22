#!/usr/bin/env bash
# =============================================================================
# package-plugin.sh
# Packages wp-ms365-graph into a distributable .zip suitable for:
#   - Manual WordPress "Upload Plugin" install
#   - WordPress.org SVN submission
#
# Usage:
#   ./bin/package-plugin.sh [VERSION]
#
#   VERSION defaults to the value found in wp-ms365-graph.php (Version: x.y.z).
#
# Output:
#   dist/wp-ms365-graph-<VERSION>.zip
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve paths
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_SLUG="wp-ms365-graph"

# ---------------------------------------------------------------------------
# Determine version
# ---------------------------------------------------------------------------
if [[ "${1:-}" != "" ]]; then
    VERSION="$1"
else
    VERSION="$(grep -m1 '^ \* Version:' "$PLUGIN_DIR/wp-ms365-graph.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
fi

if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not determine plugin version." >&2
    exit 1
fi

echo "==> Packaging $PLUGIN_SLUG v$VERSION"

# ---------------------------------------------------------------------------
# Prepare dist directory
# ---------------------------------------------------------------------------
DIST_DIR="$PLUGIN_DIR/dist"
STAGING_DIR="$DIST_DIR/$PLUGIN_SLUG"
ZIP_FILE="$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

# ---------------------------------------------------------------------------
# Copy plugin files (exclude dev/build artifacts)
# ---------------------------------------------------------------------------
rsync -av \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='bin/' \
    --exclude='dist/' \
    --exclude='tests/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='composer.lock' \
    --exclude='.phpunit.result.cache' \
    --exclude='coverage/' \
    --exclude='*.sh' \
    --exclude='.DS_Store' \
    --exclude='*.map' \
    "$PLUGIN_DIR/" "$STAGING_DIR/"

# ---------------------------------------------------------------------------
# Create the zip
# ---------------------------------------------------------------------------
cd "$DIST_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG/" --quiet
rm -rf "$STAGING_DIR"

echo ""
echo "==> Done!"
echo "    Output : $ZIP_FILE"
echo "    Size   : $(du -sh "$ZIP_FILE" | cut -f1)"
