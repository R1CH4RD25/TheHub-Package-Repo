#!/bin/bash

# ==============================================================================
# CSS Build Script - Builds context-specific CSS bundles
# ==============================================================================
# Builds three production bundles from organized source files:
# - admin-bundle.css  (admin dashboard)
# - hub-bundle.css    (user hub/sections)
# - mgmt-bundle.css   (management console)
# ==============================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directories
CSS_DIR="public/assets/css"
TIMESTAMP=$(date)

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}CSS Bundle Build Script${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# ==============================================================================
# Build Admin Bundle
# ==============================================================================
echo -e "${BLUE}Building admin-bundle.css...${NC}"

ADMIN_BUNDLE="$CSS_DIR/admin-bundle.css"
cat > "$ADMIN_BUNDLE" << HEADER
/**
 * Admin Bundle - Concatenated CSS
 * Generated: $TIMESTAMP
 */

HEADER

# Admin bundle files (in order)
ADMIN_FILES=(
    "shared/enterprise-design-system.css"
    "shared/enterprise-components.css"
    "admin/admin-layout.css"
    "admin/admin-dashboard.css"
    "admin/admin-theme.css"
    "admin/admin-animations.css"
    "shared/shared-media.css"
)

for file in "${ADMIN_FILES[@]}"; do
    if [ -f "$CSS_DIR/$file" ]; then
        echo -e "\n/* ========== $file ========== */\n" >> "$ADMIN_BUNDLE"
        cat "$CSS_DIR/$file" >> "$ADMIN_BUNDLE"
    else
        echo -e "${RED}⚠️  Missing: $file${NC}"
    fi
done

echo -e "${GREEN}✓ admin-bundle.css: $(du -h "$ADMIN_BUNDLE" | cut -f1)${NC}"

# ==============================================================================
# Build Hub Bundle
# ==============================================================================
echo -e "${BLUE}Building hub-bundle.css...${NC}"

HUB_BUNDLE="$CSS_DIR/hub-bundle.css"
cat > "$HUB_BUNDLE" << HEADER
/**
 * Hub Bundle - Concatenated CSS
 * Generated: $TIMESTAMP
 */

HEADER

# Hub bundle files (in order)
HUB_FILES=(
    "shared/enterprise-design-system.css"
    "shared/enterprise-components.css"
    "shared/hub-components.css"
    "shared/footer.css"
    "shared/modals.css"
    "hub/hub-animations.css"
    "hub/hub-pages.css"
    "shared/shared-media.css"
)

for file in "${HUB_FILES[@]}"; do
    if [ -f "$CSS_DIR/$file" ]; then
        echo -e "\n/* ========== $file ========== */\n" >> "$HUB_BUNDLE"
        cat "$CSS_DIR/$file" >> "$HUB_BUNDLE"
    else
        echo -e "${RED}⚠️  Missing: $file${NC}"
    fi
done

echo -e "${GREEN}✓ hub-bundle.css: $(du -h "$HUB_BUNDLE" | cut -f1)${NC}"

# ==============================================================================
# Build Management Bundle
# ==============================================================================
echo -e "${BLUE}Building mgmt-bundle.css...${NC}"

MGMT_BUNDLE="$CSS_DIR/mgmt-bundle.css"
cat > "$MGMT_BUNDLE" << HEADER
/**
 * Management Bundle - Concatenated CSS
 * Generated: $TIMESTAMP
 */

HEADER

# Management bundle files (in order) - Uses admin styles now
MGMT_FILES=(
    "shared/enterprise-design-system.css"
    "shared/enterprise-components.css"
    "admin/admin-layout.css"
    "admin/admin-dashboard.css"
    "admin/admin-theme.css"
    "admin/admin-animations.css"
    "shared/shared-media.css"
)

for file in "${MGMT_FILES[@]}"; do
    if [ -f "$CSS_DIR/$file" ]; then
        echo -e "\n/* ========== $file ========== */\n" >> "$MGMT_BUNDLE"
        cat "$CSS_DIR/$file" >> "$MGMT_BUNDLE"
    else
        echo -e "${RED}⚠️  Missing: $file${NC}"
    fi
done

echo -e "${GREEN}✓ mgmt-bundle.css: $(du -h "$MGMT_BUNDLE" | cut -f1)${NC}"

# ==============================================================================
# Summary
# ==============================================================================
echo ""
echo -e "${BLUE}Build Complete!${NC}"

# ==============================================================================
# Optional: Minify CSS (disabled - use build-css-bundles.sh for minification)
# ==============================================================================
# Minification disabled in this script to avoid hanging
# Use build-css-bundles.sh instead which properly minifies all bundles

# ==============================================================================
# Create version file for cache busting
# ==============================================================================
VERSION_FILE="public/assets/css/css-version.json"
echo "{\"timestamp\": $(date +%s), \"date\": \"$(date)\"}" > "$VERSION_FILE"
echo ""
echo -e "${GREEN}✓ Build complete! Bundles ready.${NC}"
echo -e "${BLUE}================================${NC}"
