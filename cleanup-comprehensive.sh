#!/bin/bash
# Comprehensive Cleanup Script for The Hub
# This script removes test files, one-time migration scripts, 
# and outdated documentation from the project.
#
# Usage: ./cleanup-comprehensive.sh [--dry-run]

set -e

DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "🔍 DRY RUN MODE - No files will be deleted"
    echo ""
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

removed_count=0
moved_count=0

remove_file() {
    local file="$1"
    if [ -f "$file" ]; then
        if $DRY_RUN; then
            echo -e "${YELLOW}[DRY RUN]${NC} Would remove: $file"
        else
            rm -f "$file"
            echo -e "${RED}✗${NC} Removed: $file"
        fi
        removed_count=$((removed_count + 1))
    else
        echo -e "${BLUE}ℹ${NC} Already removed: $file"
    fi
}

move_file() {
    local src="$1"
    local dest="$2"
    if [ -f "$src" ]; then
        if $DRY_RUN; then
            echo -e "${YELLOW}[DRY RUN]${NC} Would move: $src → $dest"
        else
            mv "$src" "$dest"
            echo -e "${GREEN}→${NC} Moved: $src → $dest"
        fi
        moved_count=$((moved_count + 1))
    else
        echo -e "${BLUE}ℹ${NC} Already moved: $src"
    fi
}

echo "======================================================================"
echo " The Hub - Comprehensive Cleanup"
echo "======================================================================"
echo ""

# ============================================================================
# PHASE 1: Remove Test Files
# ============================================================================
echo -e "${BLUE}PHASE 1: Removing test and temporary files...${NC}"
echo "----------------------------------------------------------------------"

remove_file "cli/test-email.php"
remove_file "cli/test-themes.php"
remove_file "test-groups.php"
remove_file "public/test.php"

echo ""

# ============================================================================
# PHASE 2: Remove One-Time Migration Scripts
# ============================================================================
echo -e "${BLUE}PHASE 2: Removing one-time migration scripts...${NC}"
echo "----------------------------------------------------------------------"

remove_file "cli/migrate-env.php"
remove_file "cli/migrate-additional-badges.php"
remove_file "cli/migrate-complete-color-system.php"
remove_file "cli/migrate-role-badge-colors.php"
remove_file "cli/recreate-all-themes.php"

echo ""

# ============================================================================
# PHASE 3: Remove Implementation/Status Documentation (Root Level)
# ============================================================================
echo -e "${BLUE}PHASE 3: Removing implementation and status documentation...${NC}"
echo "----------------------------------------------------------------------"

remove_file "ADVANCED_SETTINGS_ENHANCED.md"
remove_file "ADVANCED_SETTINGS_IMPLEMENTATION.md"
remove_file "CSS_BUILD_IMPLEMENTATION.md"
remove_file "IMPLEMENTATION_SUMMARY.md"
remove_file "MIGRATION_CHECKLIST.md"
remove_file "MIGRATION_STATUS.md"
remove_file "NAMESPACE_MIGRATION.md"
remove_file "PROJECT_SUMMARY.md"
remove_file "RESPONSIVE_IMPLEMENTATION.md"
remove_file "THEME_SAVING_IMPLEMENTATION.md"
remove_file "THEME_SYSTEM_FIXES.md"
remove_file "THEME_SYSTEM_REFACTOR.md"
remove_file "UPGRADING.md"

echo ""

# ============================================================================
# PHASE 4: Remove Redundant Documentation (docs/ folder)
# ============================================================================
echo -e "${BLUE}PHASE 4: Removing redundant documentation from docs/...${NC}"
echo "----------------------------------------------------------------------"

remove_file "docs/ADVANCED_SETTINGS.md"
remove_file "docs/CASCADING_DEPENDENCIES_INDEX.md"
remove_file "docs/CASCADING_DEPENDENCIES_SUMMARY.md"
remove_file "docs/CASCADING_DEPENDENCIES_VISUAL.md"
remove_file "docs/CENTRALIZED_ROLES_IMPLEMENTATION.md"
remove_file "docs/COLOR_SYSTEM_AUDIT.md"
remove_file "docs/COLOR_SYSTEM_COMPLETE.md"
remove_file "docs/CSS_BUILD_SYSTEM.md"
remove_file "docs/CSS_DATABASE_INTEGRATION.md"
remove_file "docs/CSS_MINIFICATION.md"
remove_file "docs/HUB_COLOR_CUSTOMIZATION.md"
remove_file "docs/ROLE_BADGES_REFERENCE.md"
remove_file "docs/SECTION_TYPES_IMPLEMENTATION.md"

echo ""

# ============================================================================
# PHASE 5: Reorganize Essential Documentation
# ============================================================================
echo -e "${BLUE}PHASE 5: Moving essential docs to docs/ folder...${NC}"
echo "----------------------------------------------------------------------"

# Ensure docs/ directory exists
if ! $DRY_RUN; then
    mkdir -p docs
fi

move_file "AUDIT_LOGGING.md" "docs/AUDIT_LOGGING.md"
move_file "INVITATION_SYSTEM.md" "docs/INVITATION_SYSTEM.md"
move_file "MODULAR_ARCHITECTURE.md" "docs/MODULAR_ARCHITECTURE.md"
move_file "ROLES_DOCUMENTATION.md" "docs/ROLES_DOCUMENTATION.md"
move_file "SECTION_ACCESS.md" "docs/SECTION_ACCESS.md"
move_file "CSS_BUILD_QUICKSTART.md" "docs/CSS_BUILD_QUICKSTART.md"

echo ""

# ============================================================================
# PHASE 6: Clean up generated analysis files
# ============================================================================
echo -e "${BLUE}PHASE 6: Removing temporary analysis files...${NC}"
echo "----------------------------------------------------------------------"

remove_file "cleanup.sh"
remove_file "cleanup-report.json"
remove_file "cli/cleanup-analyzer.py"

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "======================================================================"
echo -e " ${GREEN}CLEANUP COMPLETE${NC}"
echo "======================================================================"
echo ""
echo "Summary:"
echo "  - Files removed: $removed_count"
echo "  - Files moved:   $moved_count"
echo ""

if $DRY_RUN; then
    echo -e "${YELLOW}This was a DRY RUN. No actual changes were made.${NC}"
    echo "Run without --dry-run to execute cleanup:"
    echo "  ./cleanup-comprehensive.sh"
else
    echo -e "${GREEN}✓${NC} Cleanup completed successfully!"
    echo ""
    echo "Next steps:"
    echo "  1. Update .github/copilot-instructions.md (doc paths)"
    echo "  2. Update README.md (documentation index)"
    echo "  3. Test installation: php cli/check-dependencies.php"
    echo "  4. Commit changes to git"
fi
echo ""
