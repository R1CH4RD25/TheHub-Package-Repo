#!/usr/bin/env python3
"""
Scope Enterprise CSS to .admin-root

Automatically adds .admin-root scoping to all class selectors in
enterprise-components.css to prevent conflicts with Hub/frontend styles.

Usage:
    python3 cli/scope-enterprise-css.py

Date: November 19, 2025
"""

import re
import os
from pathlib import Path

# File to scope
CSS_FILE = Path('public/assets/css/enterprise-components.css')

# Classes to exclude from scoping (structural admin shell elements)
EXCLUDE_CLASSES = [
    'admin-shell',
    'admin-sidebar',
    'admin-header',
    'admin-main',
    'admin-nav',
    'admin-nav-link',
]

def scope_selector(match):
    """Add .admin-root prefix to class selectors."""
    indent = match.group(1)
    selector = match.group(2).strip()
    
    # Skip if already scoped
    if '.admin-root' in selector:
        return match.group(0)
    
    # Skip structural admin classes (they define the shell)
    if any(f'.{ex}' in selector for ex in EXCLUDE_CLASSES):
        return match.group(0)
    
    # Skip @ rules (@media, @keyframes, etc.)
    if selector.startswith('@'):
        return match.group(0)
    
    # Skip :root
    if selector.startswith(':root'):
        return match.group(0)
    
    # Skip comments
    if selector.startswith('/*'):
        return match.group(0)
    
    # Add .admin-root prefix
    return f'{indent}.admin-root {selector} {{'

def main():
    """Scope all enterprise components to .admin-root."""
    
    if not CSS_FILE.exists():
        print(f"❌ Error: {CSS_FILE} not found")
        return 1
    
    # Backup original
    backup_file = CSS_FILE.with_suffix('.css.backup')
    content = CSS_FILE.read_text()
    backup_file.write_text(content)
    print(f"📦 Backup created: {backup_file}")
    
    # Count selectors before
    before_count = len(re.findall(r'\n[ \t]*\.[\w-]+.*\{', content))
    
    # Apply scoping
    # Pattern: newline + indent + class selector + {
    scoped_content = re.sub(
        r'\n([ \t]*)(\.[\w\-][\w\s\.\#\[\]\:\(\),>+~\*\-]*)\s*\{',
        scope_selector,
        content
    )
    
    # Count after
    after_admin_root = scoped_content.count('.admin-root .')
    
    # Write scoped version
    CSS_FILE.write_text(scoped_content)
    
    print(f"\n✅ Scoping complete!")
    print(f"   Original selectors: {before_count}")
    print(f"   Scoped to .admin-root: {after_admin_root}")
    print(f"   File: {CSS_FILE}")
    print(f"\n💡 Next steps:")
    print(f"   1. Review changes: git diff {CSS_FILE}")
    print(f"   2. Test admin page in browser")
    print(f"   3. If issues, restore: cp {backup_file} {CSS_FILE}")
    
    return 0

if __name__ == '__main__':
    exit(main())
