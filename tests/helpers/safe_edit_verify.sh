#!/bin/bash
# Safe edit and verify workflow
# Usage: ./safe_edit_verify.sh <file> <old_text> <new_text>
# 
# This script:
# 1. Creates a backup
# 2. Makes the edit
# 3. Verifies the change with multiple methods
# 4. Shows git diff
# 5. Validates syntax (for PHP files)

set -e

if [ $# -lt 3 ]; then
    echo "Usage: $0 <file> <old_text> <new_text>"
    echo ""
    echo "Example:"
    echo "  $0 src/Auth.php 'getUserId' 'getCurrentUserId'"
    exit 1
fi

FILE="$1"
OLD_TEXT="$2"
NEW_TEXT="$3"

if [ ! -f "$FILE" ]; then
    echo "❌ ERROR: File not found: $FILE"
    exit 1
fi

echo "=================================================="
echo "  SAFE EDIT & VERIFY"
echo "=================================================="
echo "File: $FILE"
echo "Old:  $OLD_TEXT"
echo "New:  $NEW_TEXT"
echo ""

# 1. Create backup
BACKUP="${FILE}.backup.$(date +%s)"
cp "$FILE" "$BACKUP"
echo "✅ 1. Backup created: $BACKUP"

# 2. Make the edit
sed -i "s/$OLD_TEXT/$NEW_TEXT/g" "$FILE"
echo "✅ 2. Edit applied"

# 3. Verify with grep
echo ""
echo "🔍 3. Verification:"
if grep -q "$NEW_TEXT" "$FILE"; then
    COUNT=$(grep -c "$NEW_TEXT" "$FILE")
    echo "   ✅ Found '$NEW_TEXT' ($COUNT occurrence(s))"
    echo ""
    echo "   Context:"
    grep -n -C 2 "$NEW_TEXT" "$FILE" | head -20
else
    echo "   ❌ WARNING: '$NEW_TEXT' not found in file!"
fi

# 4. Check if old text still exists
echo ""
if grep -q "$OLD_TEXT" "$FILE"; then
    COUNT=$(grep -c "$OLD_TEXT" "$FILE")
    echo "   ⚠️  WARNING: Old text '$OLD_TEXT' still present ($COUNT occurrence(s))"
    echo "   This may be intentional if only some instances were meant to change."
else
    echo "   ✅ Old text '$OLD_TEXT' completely replaced"
fi

# 5. Show git diff (if in git repo)
echo ""
echo "📝 4. Git diff:"
if git diff --exit-code "$FILE" > /dev/null 2>&1; then
    echo "   No changes detected by git"
else
    git diff "$FILE" | head -40
fi

# 6. Syntax validation (for PHP files)
if [[ "$FILE" == *.php ]]; then
    echo ""
    echo "🔧 5. PHP Syntax validation:"
    if php -l "$FILE" > /dev/null 2>&1; then
        echo "   ✅ PHP syntax valid"
    else
        echo "   ❌ SYNTAX ERROR:"
        php -l "$FILE"
        echo ""
        echo "   Restoring from backup..."
        cp "$BACKUP" "$FILE"
        echo "   File restored"
        exit 1
    fi
fi

echo ""
echo "=================================================="
echo "  ✅ EDIT COMPLETE & VERIFIED"
echo "=================================================="
echo "Backup: $BACKUP"
echo ""
echo "To undo: cp $BACKUP $FILE"
echo "To remove backup: rm $BACKUP"
