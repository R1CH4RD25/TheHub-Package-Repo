#!/bin/bash
# Script to systematically replace hardcoded colors with CSS variables
# This handles the most common color patterns

CSS_DIR="/var/www/woodson/thehub/public/assets/css"

echo "=== Color Replacement Script ==="
echo "Backing up CSS files..."

# Backup files
cp "$CSS_DIR/admin.css" "$CSS_DIR/admin.css.backup"
cp "$CSS_DIR/admin-theme.css" "$CSS_DIR/admin-theme.css.backup"
cp "$CSS_DIR/style.css" "$CSS_DIR/style.css.backup"

echo "✓ Backups created"
echo ""
echo "Replacing common color patterns..."

# Function to replace in all CSS files
replace_in_all() {
    local search="$1"
    local replace="$2"
    local desc="$3"
    
    echo "  - $desc"
    sed -i "s/$search/$replace/g" "$CSS_DIR/admin.css"
    sed -i "s/$search/$replace/g" "$CSS_DIR/admin-theme.css"
    sed -i "s/$search/$replace/g" "$CSS_DIR/style.css"
}

# Text colors
replace_in_all "color: #111827" "color: var(--text-primary)" "Primary text color"
replace_in_all "color: #374151" "color: var(--text-secondary)" "Secondary text color"
replace_in_all "color: #6b7280" "color: var(--text-muted)" "Muted text color"
replace_in_all "color: #6c757d" "color: var(--text-muted)" "Muted text color (alt)"
replace_in_all "color: #d1d5db" "color: var(--text-disabled)" "Disabled text color"
replace_in_all "color: #666" "color: var(--text-muted)" "Muted text (short hex)"

# Borders
replace_in_all "border: 1px solid #e5e7eb" "border: 1px solid var(--border-primary)" "Primary borders"
replace_in_all "border: 2px solid #e5e7eb" "border: 2px solid var(--border-primary)" "Primary borders (2px)"
replace_in_all "border-bottom: 1px solid #e5e7eb" "border-bottom: 1px solid var(--border-primary)" "Bottom borders"
replace_in_all "border-bottom: 2px solid #e5e7eb" "border-bottom: 2px solid var(--border-primary)" "Bottom borders (2px)"
replace_in_all "border-top: 1px solid #e5e7eb" "border-top: 1px solid var(--border-primary)" "Top borders"
replace_in_all "border-top: 2px solid #e5e7eb" "border-top: 2px solid var(--border-primary)" "Top borders (2px)"

replace_in_all "border: 1px solid #d1d5db" "border: 1px solid var(--border-secondary)" "Secondary borders"
replace_in_all "border: 2px solid #d1d5db" "border: 2px solid var(--border-secondary)" "Secondary borders (2px)"
replace_in_all "border: 1px solid #ddd" "border: 1px solid var(--border-secondary)" "Generic borders"
replace_in_all "border: 2px dashed #d1d5db" "border: 2px dashed var(--border-secondary)" "Dashed borders"

# Backgrounds
replace_in_all "background: #f9fafb" "background: var(--bg-secondary)" "Secondary backgrounds"
replace_in_all "background-color: #f9fafb" "background-color: var(--bg-secondary)" "Secondary bg (color)"
replace_in_all "background: #f3f4f6" "background: var(--bg-hover)" "Hover backgrounds"
replace_in_all "background: #f8f9fa" "background: var(--bg-secondary)" "Secondary bg (alt)"
replace_in_all "background: #e5e7eb" "background: var(--bg-active)" "Active backgrounds"
replace_in_all "background: #f1f1f1" "background: var(--scrollbar-track)" "Light gray backgrounds"

# Success colors
replace_in_all "background: #10b981" "background: var(--success-button)" "Success button bg"
replace_in_all "background: #059669" "background: var(--success-button-hover)" "Success button hover"
replace_in_all "background: #d4edda" "background: var(--success-bg)" "Success message bg"
replace_in_all "color: #155724" "color: var(--success-text)" "Success text"
replace_in_all "border: 1px solid #c3e6cb" "border: 1px solid var(--success-border)" "Success border"

# Danger colors  
replace_in_all "background: #ef4444" "background: var(--danger-button)" "Danger button bg"
replace_in_all "background: #dc2626" "background: var(--danger-button-hover)" "Danger button hover"
replace_in_all "background-color: #c82333" "background-color: var(--danger-button-hover)" "Danger hover (alt)"
replace_in_all "background: #f8d7da" "background: var(--danger-bg)" "Danger message bg"
replace_in_all "color: #721c24" "color: var(--danger-text)" "Danger text"
replace_in_all "border: 1px solid #f5c6cb" "border: 1px solid var(--danger-border)" "Danger border"

# Warning colors
replace_in_all "background: #f59e0b" "background: var(--warning-button)" "Warning button bg"
replace_in_all "background: #d97706" "background: var(--warning-button-hover)" "Warning button hover"
replace_in_all "color: #000" "color: var(--text-primary)" "Black text on warning"

# Scrollbar
replace_in_all "background: #888" "background: var(--scrollbar-thumb)" "Scrollbar thumb"
replace_in_all "background: #555" "background: var(--scrollbar-thumb-hover)" "Scrollbar hover"
replace_in_all "scrollbar-color: #888 #f1f1f1" "scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track)" "Scrollbar colors"

# Code blocks
replace_in_all "background: #333" "background: var(--bg-code)" "Code block background"

# Modal overlays
replace_in_all "background: rgba(0,0,0,0.5)" "background: var(--bg-modal-overlay)" "Modal overlay"
replace_in_all "background: rgba(0, 0, 0, 0.5)" "background: var(--bg-modal-overlay)" "Modal overlay (spaces)"

# Shadows - replace specific rgba(0,0,0,X) patterns with var(--shadow-X)
replace_in_all "rgba(0,0,0,0.1)" "rgba(0,0,0,var(--shadow-light))" "Light shadows"
replace_in_all "rgba(0, 0, 0, 0.1)" "rgba(0,0,0,var(--shadow-light))" "Light shadows (spaces)"
replace_in_all "rgba(0,0,0,0.2)" "rgba(0,0,0,var(--shadow-medium))" "Medium shadows"
replace_in_all "rgba(0, 0, 0, 0.2)" "rgba(0,0,0,var(--shadow-medium))" "Medium shadows (spaces)"
replace_in_all "rgba(0,0,0,0.3)" "rgba(0,0,0,var(--shadow-heavy))" "Heavy shadows"
replace_in_all "rgba(0, 0, 0, 0.3)" "rgba(0,0,0,var(--shadow-heavy))" "Heavy shadows (spaces)"

echo ""
echo "✓ Replacement complete"
echo ""
echo "Remaining hardcoded colors (manual review needed):"
echo "  admin.css: $(grep -c '#[0-9a-fA-F]\{6\}\|rgba(' $CSS_DIR/admin.css || echo 0)"
echo "  admin-theme.css: $(grep -c '#[0-9a-fA-F]\{6\}\|rgba(' $CSS_DIR/admin-theme.css || echo 0)"
echo "  style.css: $(grep -c '#[0-9a-fA-F]\{6\}\|rgba(' $CSS_DIR/style.css || echo 0)"
echo ""
echo "Backups saved as *.backup - test the site and remove if working correctly"
