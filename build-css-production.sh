#!/bin/bash

# ==============================================================================
# CSS Production Build Script - Context-Specific Bundles + Minification
# ==============================================================================
# Creates three optimized bundles:
#   - admin-bundle.min.css   (Enterprise console)
#   - mgmt-bundle.min.css    (Management workflow)
#   - hub-bundle.min.css     (PWA frontend)
#
# Features:
#   - Minification with CSSO
#   - Cache busting via version timestamps
#   - Compression statistics
# ==============================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Directories
CSS_DIR="public/assets/css"
VERSION=$(date +%s)

echo ""
echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║        CSS Production Build - Context-Specific Bundles      ║${NC}"
echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Build Version:${NC} $VERSION"
echo ""

# ==============================================================================
# Helper Functions
# ==============================================================================

build_bundle() {
    local NAME=$1
    local OUTPUT_DEV="${CSS_DIR}/${NAME}.css"
    local OUTPUT_MIN="${CSS_DIR}/${NAME}.min.css"
    
    echo -e "${YELLOW}▶ Building ${NAME}...${NC}"
    
    # Check if source file exists
    if [ ! -f "$OUTPUT_DEV" ]; then
        echo -e "${RED}✗ Source file not found: $OUTPUT_DEV${NC}"
        return 1
    fi
    
    # Minify with CSSO
    if command -v csso &> /dev/null; then
        csso "$OUTPUT_DEV" -o "$OUTPUT_MIN" 2>/dev/null
        
        # Get sizes
        SIZE_DEV=$(du -h "$OUTPUT_DEV" | cut -f1)
        SIZE_MIN=$(du -h "$OUTPUT_MIN" | cut -f1)
        
        # Calculate compression ratio
        BYTES_DEV=$(stat -c%s "$OUTPUT_DEV" 2>/dev/null || stat -f%z "$OUTPUT_DEV")
        BYTES_MIN=$(stat -c%s "$OUTPUT_MIN" 2>/dev/null || stat -f%z "$OUTPUT_MIN")
        RATIO=$(awk "BEGIN {printf \"%.1f\", (1 - $BYTES_MIN/$BYTES_DEV) * 100}")
        
        echo -e "  ${GREEN}✓${NC} Development: ${SIZE_DEV}"
        echo -e "  ${GREEN}✓${NC} Minified:    ${SIZE_MIN} ${CYAN}(-${RATIO}%)${NC}"
    else
        echo -e "${RED}✗ CSSO not found. Skipping minification.${NC}"
        return 1
    fi
}

add_cache_buster() {
    local FILE=$1
    local VERSION=$2
    
    # Add version comment at top of minified file
    echo "/* v${VERSION} */" | cat - "$FILE" > "${FILE}.tmp" && mv "${FILE}.tmp" "$FILE"
}

# ==============================================================================
# Build Admin Bundle (Enterprise Console)
# ==============================================================================

echo -e "${BLUE}${BOLD}1. Admin Bundle (Enterprise Console)${NC}"
echo -e "${BLUE}   ${NC}Microsoft 365 inspired, data-dense interface"
echo ""

# Admin bundle is already configured via @import in admin-bundle.css
# Just minify it
build_bundle "admin-bundle"
add_cache_buster "${CSS_DIR}/admin-bundle.min.css" "$VERSION"

echo ""

# ==============================================================================
# Build Management Bundle (Workflow Dashboard)
# ==============================================================================

echo -e "${BLUE}${BOLD}2. Management Bundle (Workflow Dashboard)${NC}"
echo -e "${BLUE}   ${NC}Theme-aware, submission-focused interface"
echo ""

# Create management bundle if it doesn't exist
MGMT_BUNDLE="${CSS_DIR}/mgmt-bundle.css"

cat > "$MGMT_BUNDLE" << 'EOF'
/**
 * ============================================================================
 * MANAGEMENT BUNDLE - Workflow Dashboard
 * ============================================================================
 * Theme-aware submission management interface
 * Scope: .mgmt-root
 * ============================================================================
 */

/* Foundation: Shared design tokens */
@import url('enterprise-design-system.css');

/* Management-specific styles */
@import url('management.css');

/* Dynamic form components */
@import url('dynamic-sections.css');

/* Modals */
@import url('modals.css');
EOF

build_bundle "mgmt-bundle"
add_cache_buster "${CSS_DIR}/mgmt-bundle.min.css" "$VERSION"

echo ""

# ==============================================================================
# Build Hub Bundle (PWA Frontend)
# ==============================================================================

echo -e "${BLUE}${BOLD}3. Hub Bundle (PWA Frontend)${NC}"
echo -e "${BLUE}   ${NC}Mobile-first, friendly interface for students/staff"
echo ""

# Create hub bundle if it doesn't exist
HUB_BUNDLE="${CSS_DIR}/hub-bundle.css"

cat > "$HUB_BUNDLE" << 'EOF'
/**
 * ============================================================================
 * HUB BUNDLE - PWA Frontend
 * ============================================================================
 * Mobile-first, friendly interface
 * Scope: .hub-root
 * ============================================================================
 */

/* Base styles */
@import url('style.css');

/* Layout components */
@import url('header.css');
@import url('footer.css');

/* Page-specific */
@import url('login.css');
@import url('hub.css');
@import url('sections.css');
@import url('modules.css');

/* Modals */
@import url('modals.css');

/* Responsive/Mobile */
@import url('media.css');
EOF

build_bundle "hub-bundle"
add_cache_buster "${CSS_DIR}/hub-bundle.min.css" "$VERSION"

echo ""

# ==============================================================================
# Create Version Manifest
# ==============================================================================

echo -e "${BLUE}${BOLD}4. Version Manifest${NC}"
echo ""

VERSION_FILE="${CSS_DIR}/css-version.json"

cat > "$VERSION_FILE" << EOF
{
  "version": "$VERSION",
  "timestamp": "$(date -Iseconds)",
  "bundles": {
    "admin": {
      "dev": "admin-bundle.css",
      "min": "admin-bundle.min.css?v=$VERSION"
    },
    "mgmt": {
      "dev": "mgmt-bundle.css",
      "min": "mgmt-bundle.min.css?v=$VERSION"
    },
    "hub": {
      "dev": "hub-bundle.css",
      "min": "hub-bundle.min.css?v=$VERSION"
    }
  }
}
EOF

echo -e "${GREEN}✓ Version manifest created: css-version.json${NC}"
echo -e "  Version: ${CYAN}$VERSION${NC}"

echo ""

# ==============================================================================
# Summary
# ==============================================================================

echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║                    Build Complete! ✓                        ║${NC}"
echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo -e "${GREEN}Production bundles ready:${NC}"
echo -e "  • ${BOLD}admin-bundle.min.css${NC} - Enterprise admin console"
echo -e "  • ${BOLD}mgmt-bundle.min.css${NC}  - Management workflow dashboard"
echo -e "  • ${BOLD}hub-bundle.min.css${NC}   - PWA frontend (students/staff)"
echo ""

echo -e "${YELLOW}Next steps:${NC}"
echo -e "  1. Clear browser cache (Ctrl+Shift+R)"
echo -e "  2. Test all three contexts"
echo ""

echo -e "${BLUE}Cache-busting version: ${CYAN}$VERSION${NC}"
echo ""
