#!/bin/bash

# ==============================================================================
# CSS Bundle Build Script - Concatenates and Optionally Minifies CSS
# ==============================================================================
# Rebuilds three context-specific bundles by concatenating source files:
#   - admin-bundle.css   (Enterprise console)
#   - mgmt-bundle.css    (Management workflow)
#   - hub-bundle.css     (PWA frontend)
#
# Usage:
#   ./build-css-bundles.sh       - Development (no minification)
#   ./build-css-bundles.sh --min - Production (with minification)
# ==============================================================================

set -e  # Exit on any error

# Parse arguments
MINIFY=false
if [[ "$1" == "--min" ]] || [[ "$1" == "--minify" ]]; then
    MINIFY=true
fi

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Directory
CSS_DIR="public/assets/css"

echo ""
if $MINIFY; then
    echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}${BOLD}║      CSS Production Build - Concatenate + Minify          ║${NC}"
    echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}${BOLD}║         CSS Development Build - Concatenate Bundles        ║${NC}"
    echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
fi
echo ""

cd "$CSS_DIR" || exit 1

# ==============================================================================
# Build Admin Bundle
# ==============================================================================

echo -e "${BLUE}${BOLD}1. Admin Bundle${NC}"

python3 << 'ADMINBUILD'
import os

files = [
    "shared/enterprise-design-system.css",
    "shared/enterprise-components.css",
    "shared/enterprise-header-sidebar.css",
    "shared/footer.css",
    "admin/admin-layout.css",
    "admin/admin-dashboard.css",
    "admin/admin-theme.css",
    "admin/admin-animations.css",
    "shared/shared-media.css"
]

output = []
output.append("/**\n * Admin Bundle - Concatenated CSS\n * Generated: auto\n */\n\n")

for file in files:
    if os.path.exists(file):
        output.append(f"\n/* ========== {file} ========== */\n\n")
        with open(file, 'r', encoding='utf-8') as f:
            output.append(f.read())
    else:
        print(f"⚠️  Missing: {file}")

bundle_content = ''.join(output)
with open('admin-bundle.css', 'w', encoding='utf-8') as f:
    f.write(bundle_content)

print(f"✅ admin-bundle.css: {len(bundle_content)} bytes ({len(bundle_content)//1024}KB)")
ADMINBUILD

echo ""

# ==============================================================================
# Build Hub Bundle
# ==============================================================================

echo -e "${BLUE}${BOLD}2. Hub Bundle${NC}"

python3 << 'HUBBUILD'
import os

files = [
    "shared/base.css",
    "shared/enterprise-design-system.css",
    "shared/enterprise-components.css",
    "shared/footer.css",
    "shared/hub-components.css",
    "shared/modals.css",
    "hub/hub-pages.css",
    "hub/hub-animations.css",
    "shared/shared-media.css"
]

output = []
output.append("/**\n * Hub Bundle - Concatenated CSS\n * Generated: auto\n */\n\n")

for file in files:
    if os.path.exists(file):
        output.append(f"\n/* ========== {file} ========== */\n\n")
        with open(file, 'r', encoding='utf-8') as f:
            output.append(f.read())
    else:
        print(f"⚠️  Missing: {file}")

bundle_content = ''.join(output)
with open('hub-bundle.css', 'w', encoding='utf-8') as f:
    f.write(bundle_content)

print(f"✅ hub-bundle.css: {len(bundle_content)} bytes ({len(bundle_content)//1024}KB)")
HUBBUILD

echo ""

# ==============================================================================
# Minification (if requested)
# ==============================================================================

if $MINIFY; then
    echo -e "${BLUE}${BOLD}3. Minification${NC}"
    echo ""

    for bundle in admin-bundle hub-bundle; do
        if [ -f "${bundle}.css" ]; then
            echo -e "${YELLOW}  Minifying ${bundle}.css...${NC}"
            csso "${bundle}.css" -o "${bundle}.min.css" --no-restructure 2>/dev/null || {
                echo -e "${GREEN}  ⚠️  csso failed, copying unminified${NC}"
                cp "${bundle}.css" "${bundle}.min.css"
            }

            SIZE_DEV=$(du -h "${bundle}.css" | cut -f1)
            SIZE_MIN=$(du -h "${bundle}.min.css" | cut -f1)
            echo -e "${GREEN}  ✓${NC} ${bundle}.css: ${SIZE_DEV} → ${SIZE_MIN}"
        fi
    done
    echo ""
fi

# ==============================================================================
# Summary
# ==============================================================================

echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║                Build Complete! ✓                           ║${NC}"
echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if $MINIFY; then
    echo -e "${GREEN}Production bundles ready:${NC}"
    echo -e "  • ${BOLD}admin-bundle.min.css${NC}"
    echo -e "  • ${BOLD}hub-bundle.min.css${NC}"
    echo ""
    echo -e "${YELLOW}Next: Enable production mode in bootstrap.php${NC}"
else
    echo -e "${GREEN}Development bundles ready:${NC}"
    echo -e "  • ${BOLD}admin-bundle.css${NC}"
    echo -e "  • ${BOLD}hub-bundle.css${NC}"
    echo ""
    echo -e "${YELLOW}For production minified bundles: ${CYAN}./build-css-bundles.sh --min${NC}"
fi
echo ""
