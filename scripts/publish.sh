#!/usr/bin/env bash
# Publish this plugin to the WordPress.org plugin directory.
#
# Usage:
#   ./scripts/publish.sh           # One-time import to trunk/
#   ./scripts/publish.sh --update  # Upload as a new tag release
#
# Requirements:
#   - Subversion client installed (`apt install subversion`)
#   - Username: bestony (configured in ~/.subversion/config)
#   - Password stored in ~/.subversion/auth/svn.simple/*
#     (first run `svn ls https://plugins.svn.wordpress.org/bestony-ai-provider/trunk/`
#      and enter your password when prompted)

set -euo pipefail

PLUGIN_SLUG="bestony-ai-provider"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TMPDIR_BASE="${TMPDIR:-/tmp}"

# Read version from the main PHP file.
VERSION=$(grep '^ \* Version:' "$PLUGIN_DIR/bestony-ai-provider-for-xiaomi-mimo.php" | awk '{print $NF}' | tr -d '[:space:]')

if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not determine version from plugin header." >&2
    exit 1
fi

echo "=== Bestony AI Provider — WordPress.org Publisher ==="
echo "Plugin slug : $PLUGIN_SLUG"
echo "Version     : $VERSION"
echo ""

SVN_URL_BASE="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"

# Create a staging directory excluding dev-only files.
STAGE_DIR=$(mktemp -d "$TMPDIR_BASE/bestony-ai-provider-XXXXXX")
trap 'rm -rf "$STAGE_DIR"' EXIT

# Copy all files preserving structure.
(cd "$PLUGIN_DIR" && cp -a . "$STAGE_DIR/.")

# Remove development-only directories/files.
rm -rf "$STAGE_DIR/.git"
rm -rf "$STAGE_DIR/.github"
rm -rf "$STAGE_DIR/.svn"
rm -rf "$STAGE_DIR/.commandcode"
rm -rf "$STAGE_DIR/scripts"
rm -rf "$STAGE_DIR/.wordpress-org"
rm -f "$STAGE_DIR/assets/images/xiaomimimo.svg"

# Make the LICENSE file executable so it can be edited by CI scripts if needed.
chmod 644 "$STAGE_DIR/LICENSE" 2>/dev/null || true

case "${1:-}" in
    --update)
        # Upload as a tagged release.
        TAG_PATH="$SVN_URL_BASE/tags/$VERSION"
        echo "Importing to tag: $TAG_PATH"

        svn import "$STAGE_DIR" "$TAG_PATH" \
            --force-interactive \
            -m "$VERSION: Release $VERSION to WordPress.org plugin directory"

        echo ""
        echo "Tag $VERSION created successfully!"
        echo "Public URL: https://wordpress.org/plugins/$PLUGIN_SLUG/"
        ;;
    "")
        # Initial import to trunk.
        TRUNK_PATH="$SVN_URL_BASE/trunk"
        echo "Importing to trunk: $TRUNK_PATH"

        svn import "$STAGE_DIR" "$TRUNK_PATH" \
            --force-interactive \
            -m "Initial import to WordPress.org plugin directory"

        echo ""
        echo "Trunk updated successfully!"
        echo "Public URL: https://wordpress.org/plugins/$PLUGIN_SLUG/"
        echo ""
        echo "Next steps:"
        echo "  To release a new version, run:"
        echo "    ./scripts/publish.sh --update"
        ;;
    *)
        echo "Unknown option: $1" >&2
        echo "Usage: $0 [--update]" >&2
        exit 1
        ;;
esac
