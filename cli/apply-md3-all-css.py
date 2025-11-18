#!/usr/bin/env python3
"""
Apply Material Design 3 Motion System to ALL CSS files
Ensures consistent motion across entire production.css bundle
"""

import re
from pathlib import Path
from datetime import datetime

# MD3 Motion variables
MD3_VARIABLES = """
    /* Material Design 3 Motion System */
    --motion-standard: cubic-bezier(0.4, 0.0, 0.2, 1);
    --motion-decelerate: cubic-bezier(0.0, 0.0, 0.2, 1);
    --motion-accelerate: cubic-bezier(0.4, 0.0, 1, 1);
    --motion-sharp: cubic-bezier(0.4, 0.0, 0.6, 1);
"""

def backup_file(filepath):
    """Create backup"""
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup_dir = filepath.parent / 'backups-md3'
    backup_dir.mkdir(exist_ok=True)
    backup_path = backup_dir / f"{filepath.stem}_{timestamp}{filepath.suffix}"
    backup_path.write_text(filepath.read_text())
    return backup_path

def has_motion_variables(content):
    """Check if file already has MD3 variables"""
    return '--motion-standard' in content or '--motion-decelerate' in content

def add_motion_variables_to_root(content):
    """Add MD3 variables to :root if it exists"""
    if ':root' not in content:
        return content
    
    if has_motion_variables(content):
        return content
    
    # Add to existing :root
    content = re.sub(
        r'(:root\s*\{[^}]*?)(})',
        r'\1' + MD3_VARIABLES + r'\2',
        content,
        count=1,
        flags=re.DOTALL
    )
    
    return content

def replace_transition_easings(content):
    """Replace all transition easings with MD3 motion"""
    
    # Replace 'ease-in-out' with motion-standard
    content = re.sub(
        r'\btransition:\s*([^;]*?)\s+ease-in-out\b',
        r'transition: \1 var(--motion-standard)',
        content
    )
    
    # Replace 'ease-out' with motion-decelerate
    content = re.sub(
        r'\btransition:\s*([^;]*?)\s+ease-out\b',
        r'transition: \1 var(--motion-decelerate)',
        content
    )
    
    # Replace 'ease-in' with motion-accelerate
    content = re.sub(
        r'\btransition:\s*([^;]*?)\s+ease-in\b',
        r'transition: \1 var(--motion-accelerate)',
        content
    )
    
    # Replace generic 'ease' with motion-standard
    content = re.sub(
        r'\btransition:\s*([^;]*?)\s+ease\b',
        r'transition: \1 var(--motion-standard)',
        content
    )
    
    # Replace linear with motion-standard (better than linear)
    content = re.sub(
        r'\btransition:\s*([^;]*?)\s+linear\b',
        r'transition: \1 var(--motion-standard)',
        content
    )
    
    # Replace cubic-bezier with appropriate motion
    # Common Material Design beziers
    content = re.sub(
        r'cubic-bezier\(0\.4,\s*0,\s*0\.2,\s*1\)',
        'var(--motion-standard)',
        content
    )
    
    content = re.sub(
        r'cubic-bezier\(0,\s*0,\s*0\.2,\s*1\)',
        'var(--motion-decelerate)',
        content
    )
    
    content = re.sub(
        r'cubic-bezier\(0\.4,\s*0,\s*1,\s*1\)',
        'var(--motion-accelerate)',
        content
    )
    
    # Transitions without easing specified - add motion-standard
    content = re.sub(
        r'\btransition:\s*([\w-]+\s+[\d.]+m?s)(?=\s*[;}])',
        r'transition: \1 var(--motion-standard)',
        content
    )
    
    return content

def process_file(filepath):
    """Process a single CSS file"""
    content = filepath.read_text()
    original_content = content
    
    # Add variables to :root
    content = add_motion_variables_to_root(content)
    
    # Replace all transition easings
    content = replace_transition_easings(content)
    
    # Check if anything changed
    if content != original_content:
        filepath.write_text(content)
        return True
    
    return False

def main():
    css_dir = Path(__file__).parent.parent / 'public/assets/css'
    
    # Get all CSS files except production and backups
    css_files = [
        f for f in css_dir.glob('*.css')
        if 'production' not in f.name
        and 'backup' not in f.name
        and not f.name.startswith('.')
    ]
    
    print("🚀 Applying MD3 Motion to ALL CSS Files")
    print("="*60)
    
    # Create backup directory
    backup_dir = css_dir / 'backups-md3'
    backup_dir.mkdir(exist_ok=True)
    print(f"📁 Backups: {backup_dir}\n")
    
    changed_files = []
    unchanged_files = []
    
    for css_file in sorted(css_files):
        # Backup
        backup_file(css_file)
        
        # Process
        if process_file(css_file):
            changed_files.append(css_file.name)
            print(f"✅ {css_file.name}")
        else:
            unchanged_files.append(css_file.name)
            print(f"⚪ {css_file.name} (no changes needed)")
    
    print("\n" + "="*60)
    print(f"📊 SUMMARY")
    print("="*60)
    print(f"✅ Modified: {len(changed_files)} files")
    print(f"⚪ Unchanged: {len(unchanged_files)} files")
    print(f"💾 Backups: {len(css_files)} files")
    print("\n🎯 All CSS files now use Material Design 3 motion!")
    print("🔧 Run build-css.sh to rebuild production.css")
    print("="*60 + "\n")

if __name__ == '__main__':
    main()
