#!/usr/bin/env python3
"""
PWA CSS Optimizer for The Hub
Safely transforms media.css with PWA best practices while preserving functionality
"""

import re
import sys
from pathlib import Path

def backup_file(filepath):
    """Create a timestamped backup"""
    from datetime import datetime
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup_path = filepath.parent / f"{filepath.stem}_backup_{timestamp}{filepath.suffix}"
    backup_path.write_text(filepath.read_text())
    print(f"✅ Backup created: {backup_path}")
    return backup_path

def add_pwa_variables(content):
    """Add PWA CSS variables at the top"""
    pwa_vars = """/**
 * PWA-Optimized Responsive Media Queries for The Hub
 * Mobile-first approach with standardized breakpoints
 */

:root {
    /* Spacing scale - PWA standard */
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 20px;
    --space-6: 24px;
    
    /* App bar heights - prevents layout shift */
    --app-bar-height: 56px;
    --app-bar-height-lg: 64px;
    
    /* Safe area insets - iPhone notch support */
    --safe-top: env(safe-area-inset-top);
    --safe-bottom: env(safe-area-inset-bottom);
    --safe-left: env(safe-area-inset-left);
    --safe-right: env(safe-area-inset-right);
}

/* PWA Touch enhancements */
body {
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    padding-top: var(--safe-top);
    padding-bottom: var(--safe-bottom);
}

/* Minimum touch target size - iOS/Android standard */
button, a, .btn, .sidebar-menu a, .nav-link {
    min-height: 48px;
    min-width: 48px;
}

"""
    # Replace existing header comment
    content = re.sub(
        r'/\*\*\s*\n\s*\*\s*Responsive Media Queries.*?\*/',
        pwa_vars.rstrip(),
        content,
        flags=re.DOTALL
    )
    return content

def transform_sidebar_animation(content):
    """Replace left-based animation with GPU-accelerated transform"""
    print("🔧 Transforming sidebar to use GPU-accelerated animations...")
    
    # Replace admin-sidebar positioning
    content = re.sub(
        r'\.admin-sidebar\s*\{([^}]*?)left:\s*-280px;([^}]*?)\}',
        r'.admin-sidebar {\1transform: translateX(-100%);\2transition: transform 0.3s ease;\1will-change: transform;\1}',
        content,
        flags=re.DOTALL
    )
    
    # Replace .open state
    content = re.sub(
        r'\.admin-sidebar\.open\s*\{([^}]*?)left:\s*0;([^}]*?)\}',
        r'.admin-sidebar.open {\1transform: translateX(0);\2}',
        content,
        flags=re.DOTALL
    )
    
    return content

def standardize_breakpoints(content):
    """Consolidate breakpoints to PWA standards"""
    print("🔧 Standardizing breakpoints...")
    
    # Map old breakpoints to new standards
    replacements = [
        # 767px -> 599px (mobile cutoff)
        (r'@media\s*\(\s*max-width:\s*767px\s*\)', '@media (max-width: 599px)'),
        
        # 768px max-width -> 599px
        (r'@media\s*\(\s*max-width:\s*768px\s*\)', '@media (max-width: 599px)'),
        
        # 768px min-width -> 600px
        (r'@media\s*\(\s*min-width:\s*768px\s*\)', '@media (min-width: 600px)'),
        
        # 480px -> 400px (better phone cutoff)
        (r'@media\s*\(\s*max-width:\s*480px\s*\)', '@media (max-width: 400px)'),
        
        # 375px -> 360px (standard small phone)
        (r'@media\s*\(\s*max-width:\s*375px\s*\)', '@media (max-width: 360px)'),
        
        # 360px stays as is (smallest common phone)
        # 1024px -> 900px (tablet cutoff)
        (r'@media\s*\(\s*max-width:\s*1024px\s*\)', '@media (max-width: 899px)'),
        
        # 992px -> 900px
        (r'@media\s*\(\s*min-width:\s*992px\s*\)', '@media (min-width: 900px)'),
        
        # 991px -> 899px
        (r'@media\s*\(\s*max-width:\s*991px\s*\)', '@media (max-width: 899px)'),
        
        # Keep 1200px as desktop cutoff
    ]
    
    for old, new in replacements:
        content = re.sub(old, new, content)
    
    return content

def remove_redundant_importants(content):
    """Remove obvious redundant !important flags"""
    print("🔧 Removing redundant !important flags...")
    
    # Remove !important from properties that don't need override power
    safe_removals = [
        # Display properties in media queries often don't need !important
        (r'(display:\s*(?:none|block|flex|grid|inline-block))\s*!important', r'\1'),
        
        # Width/height at 100% rarely needs forcing
        (r'((?:width|height):\s*100%)\s*!important(?=\s*;)', r'\1'),
        
        # Margin/padding 0 doesn't need forcing
        (r'((?:margin|padding)(?:-\w+)?:\s*0)\s*!important(?=\s*;)', r'\1'),
        
        # Position values
        (r'((?:left|right|top|bottom):\s*0)\s*!important(?=\s*;)', r'\1'),
    ]
    
    for pattern, replacement in safe_removals:
        content = re.sub(pattern, replacement, content)
    
    return content

def add_table_card_utility(content):
    """Add reusable table card view utility"""
    print("🔧 Adding table card utility class...")
    
    utility = """
/* ==========================================================================
   TABLE CARD VIEW UTILITY - Reusable mobile table pattern
   ========================================================================== */
.table-card-view thead {
    display: none;
}

.table-card-view tbody {
    display: block;
}

.table-card-view tr {
    display: block;
    background: #fff;
    margin-bottom: var(--space-4);
    border-radius: 8px;
    padding: var(--space-4);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table-card-view td {
    display: block;
    padding: var(--space-2) 0;
    position: relative;
    text-align: right;
    border: none;
}

.table-card-view td::before {
    content: attr(data-label);
    position: absolute;
    left: 0;
    font-weight: 600;
    color: #666;
}

"""
    
    # Add before first @media query
    content = re.sub(
        r'(/\* =+\s*\n\s*(?:TABLET|MOBILE|Breakpoints).*?\n\s*=+ \*/)',
        utility + r'\1',
        content,
        count=1
    )
    
    return content

def add_pwa_comments(content):
    """Add clear section markers for PWA breakpoints"""
    print("🔧 Adding PWA breakpoint documentation...")
    
    breakpoint_docs = """
/* ==========================================================================
   PWA BREAKPOINTS (Mobile-First)
   - Extra Small: 0-360px     (iPhone SE, small phones)
   - Small:       361-599px   (Standard phones)
   - Medium:      600-899px   (Large phones, small tablets)
   - Large:       900-1199px  (Tablets, small desktop)
   - Extra Large: 1200px+     (Desktop)
   ========================================================================== */

"""
    
    # Add after the :root variables
    content = re.sub(
        r'(min-width:\s*48px;\s*\n}\s*\n)',
        r'\1' + breakpoint_docs,
        content
    )
    
    return content

def generate_report(original, transformed):
    """Generate transformation report"""
    print("\n" + "="*60)
    print("PWA TRANSFORMATION REPORT")
    print("="*60)
    
    # Count !important reduction
    orig_important = len(re.findall(r'!important', original))
    new_important = len(re.findall(r'!important', transformed))
    
    # Count breakpoints
    orig_media = len(re.findall(r'@media', original))
    new_media = len(re.findall(r'@media', transformed))
    
    # File size
    orig_lines = len(original.split('\n'))
    new_lines = len(transformed.split('\n'))
    
    print(f"📊 !important usage:  {orig_important} → {new_important} ({orig_important - new_important} removed)")
    print(f"📊 @media queries:    {orig_media} → {new_media}")
    print(f"📊 File size:         {orig_lines} → {new_lines} lines")
    print(f"\n✨ Added:")
    print(f"   • CSS variables for spacing & app bar heights")
    print(f"   • Safe-area-inset support (iPhone notch)")
    print(f"   • Touch enhancements (-webkit-tap-highlight, touch-action)")
    print(f"   • GPU-accelerated sidebar (transform instead of left)")
    print(f"   • Reusable .table-card-view utility")
    print(f"   • 48px minimum touch targets")
    print(f"   • Standardized breakpoints (360px, 600px, 900px, 1200px)")
    print("="*60 + "\n")

def main():
    # File path
    css_path = Path(__file__).parent.parent / 'public/assets/css/media.css'
    
    if not css_path.exists():
        print(f"❌ Error: {css_path} not found")
        sys.exit(1)
    
    print("🚀 PWA CSS Optimizer")
    print(f"📁 Target: {css_path}\n")
    
    # Read original
    original_content = css_path.read_text()
    
    # Backup
    backup_file(css_path)
    
    # Transform
    print("🔄 Applying PWA transformations...\n")
    content = original_content
    
    content = add_pwa_variables(content)
    content = transform_sidebar_animation(content)
    content = standardize_breakpoints(content)
    content = remove_redundant_importants(content)
    content = add_table_card_utility(content)
    content = add_pwa_comments(content)
    
    # Write transformed file
    css_path.write_text(content)
    print(f"✅ Transformed file written to {css_path}\n")
    
    # Generate report
    generate_report(original_content, content)
    
    print("✅ DONE! Test your site and commit if everything looks good.")
    print("💡 To revert: Use the backup file created above\n")

if __name__ == '__main__':
    main()
