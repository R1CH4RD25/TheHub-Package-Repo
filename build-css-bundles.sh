#!/bin/bash

# ==============================================================================
# CSS Development Build Script - Concatenates CSS Bundles
# ==============================================================================
# Rebuilds three context-specific bundles by concatenating source files:
#   - admin-bundle.css   (Enterprise console)
#   - mgmt-bundle.css    (Management workflow)
#   - hub-bundle.css     (PWA frontend)
#
# Use this during development when CSS files change.
# For production minification, use build-css-production.sh
# ==============================================================================

set -e  # Exit on any error

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
echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║         CSS Development Build - Concatenate Bundles        ║${NC}"
echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
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
    "shared/enterprise-footer.css",
    "admin/admin.css",
    "admin/admin-modern.css",
    "admin/admin-theme.css",
    "admin/admin-colors.css",
    "admin/admin-media.css",
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
    "shared/enterprise-design-system.css",
    "shared/enterprise-components.css",
    "shared/header.css",
    "shared/footer.css",
    "shared/modals.css",
    "shared/login.css",
    "hub/hub.css",
    "hub/hub-modern.css",
    "hub/sections.css",
    "hub/modules.css",
    "hub/hub-media.css",
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
# Build Management Bundle
# ==============================================================================

echo -e "${BLUE}${BOLD}3. Management Bundle${NC}"

python3 << 'MGMTBUILD'
import os

files = [
    "shared/enterprise-design-system.css",
    "shared/enterprise-components.css",
    "shared/enterprise-header-sidebar.css",
    "shared/enterprise-footer.css",
    "mgmt/management.css",
    "mgmt/dynamic-sections.css",
    "mgmt/mgmt-media.css",
    "shared/shared-media.css"
]

output = []
output.append("/**\n * Management Bundle - Concatenated CSS\n * Generated: auto\n */\n\n")

for file in files:
    if os.path.exists(file):
        output.append(f"\n/* ========== {file} ========== */\n\n")
        with open(file, 'r', encoding='utf-8') as f:
            output.append(f.read())
    else:
        print(f"⚠️  Missing: {file}")

bundle_content = ''.join(output)
with open('mgmt-bundle.css', 'w', encoding='utf-8') as f:
    f.write(bundle_content)

print(f"✅ mgmt-bundle.css: {len(bundle_content)} bytes ({len(bundle_content)//1024}KB)")
MGMTBUILD

echo ""

# ==============================================================================
# Summary
# ==============================================================================

echo -e "${CYAN}${BOLD}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║                Build Complete! ✓                           ║${NC}"
echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo -e "${GREEN}Development bundles ready:${NC}"
echo -e "  • ${BOLD}admin-bundle.css${NC}"
echo -e "  • ${BOLD}hub-bundle.css${NC}"
echo -e "  • ${BOLD}mgmt-bundle.css${NC}"
echo ""

echo -e "${YELLOW}Usage:${NC}"
echo -e "  Run this script whenever CSS source files change"
echo -e "  For production builds with minification: ${CYAN}./build-css-production.sh${NC}"
echo ""
