#!/bin/bash

# ==============================================================================
# CSS Build Script - Combines ALL CSS files into ONE production.css
# ==============================================================================
# This script combines all CSS source files from public/assets/css/ into a 
# single production.css file. Run after any CSS changes or site settings updates.
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
OUTPUT_FILE="public/assets/css/production.css"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}CSS Production Build Script${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

echo -e "${BLUE}Combining all CSS files...${NC}"

# Create production.css with header
cat > "$OUTPUT_FILE" << 'HEADER'
/**
 * ============================================================================
 * PRODUCTION CSS - COMBINED FROM ALL SOURCE FILES
 * ============================================================================
 * Auto-generated bundle - DO NOT EDIT DIRECTLY
 * Edit source files in public/assets/css/ and run build-css.sh
 * 
 * Build Order:
 * 1. style.css       - Base styles (buttons, forms, cards)
 * 2. header.css      - Header/navbar (shared)
 * 3. footer.css      - Footer (shared)
 * 4. hub.css         - Hub page styles
 * 5. admin.css       - Dashboard layout
 * 6. admin-theme.css - Dashboard theming
 * 7. admin-colors.css - Dashboard colors
 * 8. media.css       - Responsive/mobile
 * 
 * Generated: TIMESTAMP
 * ============================================================================
 */

HEADER

# Replace TIMESTAMP with actual timestamp
sed -i "s/TIMESTAMP/$(date)/" "$OUTPUT_FILE"

# Combine all CSS files in correct order
echo -e "\n/* ========== BASE STYLES ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/style.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== HEADER STYLES ========== */\n" >> "$OUTPUT_FILE"
echo -e "\n/* ========== HEADER STYLES (MODERN UNIFIED) ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/header-modern.css" >> "$OUTPUT_FILE"

cat "$CSS_DIR/header.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== FOOTER STYLES ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/footer.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== HUB PAGE STYLES ========== */\n" >> "$OUTPUT_FILE"
echo -e "\n/* ========== HUB MODERN ENHANCEMENTS ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/hub-modern.css" >> "$OUTPUT_FILE"

cat "$CSS_DIR/hub.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== DASHBOARD LAYOUT ========== */\n" >> "$OUTPUT_FILE"
echo -e "\n/* ========== DASHBOARD MODERN ENHANCEMENTS ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/admin-modern.css" >> "$OUTPUT_FILE"

cat "$CSS_DIR/admin.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== DASHBOARD THEME ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/admin-theme.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== DASHBOARD COLORS ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/admin-colors.css" >> "$OUTPUT_FILE"

echo -e "\n/* ========== RESPONSIVE/MEDIA QUERIES ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/media.css" >> "$OUTPUT_FILE"

echo -e "${GREEN}✓ Production CSS created: $OUTPUT_FILE${NC}"

# ==============================================================================
# Get file size
# ==============================================================================
echo ""
echo -e "${BLUE}Build Summary:${NC}"
echo -e "${YELLOW}Production CSS:${NC} $(du -h "$OUTPUT_FILE" | cut -f1)"

# ==============================================================================
# Optional: Minify CSS (requires csso or clean-css-cli)
# ==============================================================================
if command -v csso &> /dev/null; then
    echo ""
    echo -e "${BLUE}Minifying CSS...${NC}"
    
    csso "$OUTPUT_FILE" -o "public/assets/css/production.min.css"
    
    echo -e "${GREEN}✓ Minified version created${NC}"
    echo -e "${YELLOW}Production Minified:${NC} $(du -h "public/assets/css/production.min.css" | cut -f1)"
else
    echo ""
    echo -e "${YELLOW}Note: Install 'csso-cli' for CSS minification:${NC}"
    echo -e "  npm install -g csso-cli"
fi

# ==============================================================================
# Create version file for cache busting
# ==============================================================================
VERSION_FILE="public/assets/css/version.txt"
echo "$(date +%s)" > "$VERSION_FILE"
echo ""
echo -e "${GREEN}✓ Build complete! Version: $(cat $VERSION_FILE)${NC}"
echo -e "${BLUE}================================${NC}"
