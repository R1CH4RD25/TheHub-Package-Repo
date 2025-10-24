#!/bin/bash
# Comprehensive Cleanup Script for The Hub - SAFE MODE
# This script MOVES files to a backup folder instead of deleting them.
# If everything works, you can delete the backup folder later.
#
# Usage: ./cleanup-safe.sh [--restore]

set -e

RESTORE_MODE=false
if [[ "$1" == "--restore" ]]; then
    RESTORE_MODE=true
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

BACKUP_DIR="$PROJECT_ROOT/.cleanup-backup-$(date +%Y%m%d-%H%M%S)"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

removed_count=0
moved_count=0

# Function to safely backup a file
backup_file() {
    local file="$1"
    if [ -f "$file" ]; then
        local backup_path="$BACKUP_DIR/$file"
        local backup_dir=$(dirname "$backup_path")
        
        mkdir -p "$backup_dir"
        mv "$file" "$backup_path"
        echo -e "${CYAN}📦${NC} Backed up: $file → .cleanup-backup/"
        removed_count=$((removed_count + 1))
    else
        echo -e "${BLUE}ℹ${NC} Already removed: $file"
    fi
}

# Function to move a file
move_file() {
    local src="$1"
    local dest="$2"
    if [ -f "$src" ]; then
        local dest_dir=$(dirname "$dest")
        mkdir -p "$dest_dir"
        mv "$src" "$dest"
        echo -e "${GREEN}→${NC} Moved: $src → $dest"
        moved_count=$((moved_count + 1))
    else
        echo -e "${BLUE}ℹ${NC} Already moved: $src"
    fi
}

# Function to restore from backup
restore_from_backup() {
    echo "======================================================================"
    echo " Searching for backup folders..."
    echo "======================================================================"
    echo ""
    
    # Find all backup folders
    backup_folders=($(find . -maxdepth 1 -type d -name ".cleanup-backup-*" | sort -r))
    
    if [ ${#backup_folders[@]} -eq 0 ]; then
        echo -e "${RED}✗${NC} No backup folders found."
        exit 1
    fi
    
    echo "Found ${#backup_folders[@]} backup folder(s):"
    for i in "${!backup_folders[@]}"; do
        echo "  $((i+1)). ${backup_folders[$i]}"
    done
    echo ""
    
    read -p "Which backup to restore? [1]: " choice
    choice=${choice:-1}
    
    selected_backup="${backup_folders[$((choice-1))]}"
    
    if [ ! -d "$selected_backup" ]; then
        echo -e "${RED}✗${NC} Invalid selection."
        exit 1
    fi
    
    echo ""
    echo "======================================================================"
    echo " Restoring from: $selected_backup"
    echo "======================================================================"
    echo ""
    
    # Restore all files
    restored=0
    while IFS= read -r -d '' backup_file; do
        # Get relative path from backup folder
        rel_path="${backup_file#$selected_backup/}"
        dest_path="$PROJECT_ROOT/$rel_path"
        dest_dir=$(dirname "$dest_path")
        
        mkdir -p "$dest_dir"
        cp -a "$backup_file" "$dest_path"
        echo -e "${GREEN}✓${NC} Restored: $rel_path"
        restored=$((restored + 1))
    done < <(find "$selected_backup" -type f -print0)
    
    echo ""
    echo "======================================================================"
    echo -e " ${GREEN}RESTORE COMPLETE${NC}"
    echo "======================================================================"
    echo "  Restored $restored files"
    echo ""
    echo "The backup folder is still preserved at:"
    echo "  $selected_backup"
    echo ""
}

if $RESTORE_MODE; then
    restore_from_backup
    exit 0
fi

echo "======================================================================"
echo " The Hub - Safe Cleanup (Backup Mode)"
echo "======================================================================"
echo ""
echo -e "${YELLOW}Files will be moved to backup folder instead of deleted.${NC}"
echo "Backup location: $(basename $BACKUP_DIR)"
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"

# ============================================================================
# PHASE 1: Backup Test Files
# ============================================================================
echo -e "${BLUE}PHASE 1: Backing up test and temporary files...${NC}"
echo "----------------------------------------------------------------------"

backup_file "cli/test-email.php"
backup_file "cli/test-themes.php"
backup_file "test-groups.php"
backup_file "public/test.php"

echo ""

# ============================================================================
# PHASE 2: Backup One-Time Migration Scripts
# ============================================================================
echo -e "${BLUE}PHASE 2: Backing up one-time migration scripts...${NC}"
echo "----------------------------------------------------------------------"

backup_file "cli/migrate-env.php"
backup_file "cli/migrate-additional-badges.php"
backup_file "cli/migrate-complete-color-system.php"
backup_file "cli/migrate-role-badge-colors.php"
backup_file "cli/recreate-all-themes.php"

echo ""

# ============================================================================
# PHASE 3: Backup Implementation/Status Documentation (Root Level)
# ============================================================================
echo -e "${BLUE}PHASE 3: Backing up implementation and status documentation...${NC}"
echo "----------------------------------------------------------------------"

backup_file "ADVANCED_SETTINGS_ENHANCED.md"
backup_file "ADVANCED_SETTINGS_IMPLEMENTATION.md"
backup_file "CSS_BUILD_IMPLEMENTATION.md"
backup_file "IMPLEMENTATION_SUMMARY.md"
backup_file "MIGRATION_CHECKLIST.md"
backup_file "MIGRATION_STATUS.md"
backup_file "NAMESPACE_MIGRATION.md"
backup_file "PROJECT_SUMMARY.md"
backup_file "RESPONSIVE_IMPLEMENTATION.md"
backup_file "THEME_SAVING_IMPLEMENTATION.md"
backup_file "THEME_SYSTEM_FIXES.md"
backup_file "THEME_SYSTEM_REFACTOR.md"
backup_file "UPGRADING.md"

echo ""

# ============================================================================
# PHASE 4: Backup Redundant Documentation (docs/ folder)
# ============================================================================
echo -e "${BLUE}PHASE 4: Backing up redundant documentation from docs/...${NC}"
echo "----------------------------------------------------------------------"

backup_file "docs/ADVANCED_SETTINGS.md"
backup_file "docs/CASCADING_DEPENDENCIES_INDEX.md"
backup_file "docs/CASCADING_DEPENDENCIES_SUMMARY.md"
backup_file "docs/CASCADING_DEPENDENCIES_VISUAL.md"
backup_file "docs/CENTRALIZED_ROLES_IMPLEMENTATION.md"
backup_file "docs/COLOR_SYSTEM_AUDIT.md"
backup_file "docs/COLOR_SYSTEM_COMPLETE.md"
backup_file "docs/CSS_BUILD_SYSTEM.md"
backup_file "docs/CSS_DATABASE_INTEGRATION.md"
backup_file "docs/CSS_MINIFICATION.md"
backup_file "docs/HUB_COLOR_CUSTOMIZATION.md"
backup_file "docs/ROLE_BADGES_REFERENCE.md"
backup_file "docs/SECTION_TYPES_IMPLEMENTATION.md"

echo ""

# ============================================================================
# PHASE 5: Reorganize Essential Documentation
# ============================================================================
echo -e "${BLUE}PHASE 5: Moving essential docs to docs/ folder...${NC}"
echo "----------------------------------------------------------------------"

# Ensure docs/ directory exists
mkdir -p docs

move_file "AUDIT_LOGGING.md" "docs/AUDIT_LOGGING.md"
move_file "INVITATION_SYSTEM.md" "docs/INVITATION_SYSTEM.md"
move_file "MODULAR_ARCHITECTURE.md" "docs/MODULAR_ARCHITECTURE.md"
move_file "ROLES_DOCUMENTATION.md" "docs/ROLES_DOCUMENTATION.md"
move_file "SECTION_ACCESS.md" "docs/SECTION_ACCESS.md"
move_file "CSS_BUILD_QUICKSTART.md" "docs/CSS_BUILD_QUICKSTART.md"

echo ""

# ============================================================================
# PHASE 6: Clean up temporary analysis files
# ============================================================================
echo -e "${BLUE}PHASE 6: Backing up temporary analysis files...${NC}"
echo "----------------------------------------------------------------------"

backup_file "cleanup.sh"
backup_file "cleanup-report.json"
backup_file "cli/cleanup-analyzer.py"

echo ""

# ============================================================================
# Create backup manifest
# ============================================================================
echo "Creating backup manifest..."
cat > "$BACKUP_DIR/MANIFEST.txt" << EOF
Cleanup Backup Manifest
Created: $(date)
======================

This backup contains $removed_count files that were removed during cleanup.

To restore all files:
  ./cleanup-safe.sh --restore

To permanently delete this backup after verifying everything works:
  rm -rf $(basename $BACKUP_DIR)

Files in this backup:
EOF

find "$BACKUP_DIR" -type f ! -name "MANIFEST.txt" | sed "s|$BACKUP_DIR/||" | sort >> "$BACKUP_DIR/MANIFEST.txt"

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "======================================================================"
echo -e " ${GREEN}CLEANUP COMPLETE${NC}"
echo "======================================================================"
echo ""
echo "Summary:"
echo "  - Files backed up:  $removed_count"
echo "  - Files moved:      $moved_count"
echo "  - Backup location:  $(basename $BACKUP_DIR)"
echo ""
echo -e "${GREEN}✓${NC} All files safely backed up!"
echo ""
echo "Next steps:"
echo "  1. Test the application thoroughly"
echo "  2. Run: php cli/check-dependencies.php"
echo "  3. Test login, modules, admin panel"
echo "  4. If everything works, delete backup:"
echo "     rm -rf $(basename $BACKUP_DIR)"
echo "  5. If something broke, restore with:"
echo "     ./cleanup-safe.sh --restore"
echo ""
echo "Backup manifest saved to:"
echo "  $(basename $BACKUP_DIR)/MANIFEST.txt"
echo ""
