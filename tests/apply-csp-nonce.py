#!/usr/bin/env python3
"""
Apply CSP nonce to inline script tags
Replaces <script> with <script nonce="<?php echo CSP_NONCE; ?>">
"""

import re
import sys
from pathlib import Path

files_to_update = [
    'public/admin/section-config-tab.php',
    'public/admin/index.php',
    'public/hub.php',
    'public/profile.php',
    'public/login.php',
    'public/command/submission.php',
    'public/command/section.php',
]

def apply_nonce_to_file(filepath):
    """Apply CSP nonce to inline script tags in a file"""
    path = Path(filepath)
    if not path.exists():
        print(f"⚠️  File not found: {filepath}")
        return False
    
    content = path.read_text(encoding='utf-8')
    original_content = content
    
    # Pattern: <script> or <script > (with optional space before >)
    # Don't match <script src= (external scripts don't need nonce)
    # Don't match if nonce already exists
    
    # Count existing nonces
    existing_nonces = content.count('nonce="<?php echo CSP_NONCE; ?>"')
    
    # Replace <script> with nonce (inline scripts only)
    # Negative lookahead to avoid src= scripts and existing nonces
    pattern = r'<script(?!\s+(?:src=|nonce=))(\s*)>'
    replacement = r'<script nonce="<?php echo CSP_NONCE; ?>"\1>'
    
    content = re.sub(pattern, replacement, content)
    
    # Count changes
    new_nonces = content.count('nonce="<?php echo CSP_NONCE; ?>"')
    added = new_nonces - existing_nonces
    
    if content != original_content:
        path.write_text(content, encoding='utf-8')
        print(f"✅ {filepath}: Added {added} nonce(s)")
        return True
    else:
        print(f"ℹ️  {filepath}: No changes needed (already has {existing_nonces} nonce(s))")
        return False

def main():
    print("🔒 Applying CSP nonces to inline scripts\n")
    
    updated = 0
    skipped = 0
    
    for filepath in files_to_update:
        if apply_nonce_to_file(filepath):
            updated += 1
        else:
            skipped += 1
    
    print(f"\n📊 Summary:")
    print(f"   Updated: {updated}")
    print(f"   Skipped: {skipped}")
    print(f"   Total:   {updated + skipped}")
    
    if updated > 0:
        print("\n✨ CSP nonce application complete!")
    else:
        print("\n✅ All files already have nonces applied")

if __name__ == '__main__':
    main()
