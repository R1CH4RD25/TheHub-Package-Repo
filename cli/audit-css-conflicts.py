#!/usr/bin/env python3
"""
CSS Conflict Analyzer
Identifies potential conflicts between enterprise and existing styles
"""

import re
from pathlib import Path
from collections import defaultdict
import json

# Paths
CSS_DIR = Path(__file__).parent.parent / 'public' / 'assets' / 'css'
PRODUCTION_CSS = CSS_DIR / 'production.css'
ENTERPRISE_DESIGN = CSS_DIR / 'enterprise-design-system.css'
ENTERPRISE_COMPONENTS = CSS_DIR / 'enterprise-components.css'

def extract_selectors(css_content):
    """Extract all CSS selectors from content"""
    # Remove comments
    css_content = re.sub(r'/\*.*?\*/', '', css_content, flags=re.DOTALL)

    # Match selectors (simplified - handles most cases)
    selector_pattern = r'([.#]?[\w-]+(?:\s*[>+~]\s*[.#]?[\w-]+)*)\s*\{'
    selectors = re.findall(selector_pattern, css_content)

    return [s.strip() for s in selectors]

def extract_variables(css_content):
    """Extract CSS custom properties"""
    var_pattern = r'--[\w-]+:'
    variables = re.findall(var_pattern, css_content)
    return list(set(variables))

def extract_classes(css_content):
    """Extract class names"""
    class_pattern = r'\.([a-zA-Z][\w-]*)'
    classes = re.findall(class_pattern, css_content)
    return list(set(classes))

def count_declarations(css_content):
    """Count total CSS declarations"""
    return len(re.findall(r'\{[^}]+\}', css_content))

def analyze_specificity(selector):
    """Calculate CSS specificity (simplified)"""
    ids = len(re.findall(r'#\w+', selector))
    classes = len(re.findall(r'\.\w+', selector))
    elements = len(re.findall(r'\b[a-z]+\b', selector))
    return (ids, classes, elements)

def main():
    print("=" * 80)
    print("CSS CONFLICT ANALYZER")
    print("=" * 80)

    # Read files
    production_content = PRODUCTION_CSS.read_text() if PRODUCTION_CSS.exists() else ""
    enterprise_design_content = ENTERPRISE_DESIGN.read_text() if ENTERPRISE_DESIGN.exists() else ""
    enterprise_components_content = ENTERPRISE_COMPONENTS.read_text() if ENTERPRISE_COMPONENTS.exists() else ""

    print(f"\n📂 Files analyzed:")
    print(f"  • production.css: {len(production_content):,} bytes")
    print(f"  • enterprise-design-system.css: {len(enterprise_design_content):,} bytes")
    print(f"  • enterprise-components.css: {len(enterprise_components_content):,} bytes")

    # Extract data
    prod_selectors = extract_selectors(production_content)
    prod_variables = extract_variables(production_content)
    prod_classes = extract_classes(production_content)

    ent_selectors = extract_selectors(enterprise_components_content)
    ent_variables = extract_variables(enterprise_design_content)
    ent_classes = extract_classes(enterprise_components_content)

    print(f"\n📊 Production CSS Statistics:")
    print(f"  • Total selectors: {len(prod_selectors):,}")
    print(f"  • Unique classes: {len(prod_classes):,}")
    print(f"  • CSS variables: {len(prod_variables):,}")
    print(f"  • Declarations: {count_declarations(production_content):,}")

    print(f"\n📊 Enterprise CSS Statistics:")
    print(f"  • Total selectors: {len(ent_selectors):,}")
    print(f"  • Unique classes: {len(ent_classes):,}")
    print(f"  • CSS variables: {len(ent_variables):,}")
    print(f"  • Declarations: {count_declarations(enterprise_components_content):,}")

    # Find conflicts
    print("\n" + "=" * 80)
    print("⚠️  POTENTIAL CONFLICTS")
    print("=" * 80)

    # Class conflicts
    class_conflicts = set(prod_classes) & set(ent_classes)
    print(f"\n1. CLASS NAME CONFLICTS ({len(class_conflicts)} found):")
    if class_conflicts:
        critical_conflicts = [c for c in class_conflicts if c in ['btn', 'btn-primary', 'btn-secondary', 'card', 'alert']]
        if critical_conflicts:
            print(f"   🚨 CRITICAL (common components):")
            for cls in sorted(critical_conflicts)[:10]:
                print(f"      • .{cls}")

        other_conflicts = [c for c in class_conflicts if c not in critical_conflicts]
        if other_conflicts:
            print(f"   ⚠️  Other conflicts:")
            for cls in sorted(other_conflicts)[:15]:
                print(f"      • .{cls}")
            if len(other_conflicts) > 15:
                print(f"      ... and {len(other_conflicts) - 15} more")
    else:
        print("   ✅ No class conflicts found")

    # Variable conflicts
    var_conflicts = set(prod_variables) & set(ent_variables)
    print(f"\n2. CSS VARIABLE CONFLICTS ({len(var_conflicts)} found):")
    if var_conflicts:
        for var in sorted(var_conflicts)[:20]:
            print(f"   • {var}")
        if len(var_conflicts) > 20:
            print(f"   ... and {len(var_conflicts) - 20} more")
    else:
        print("   ✅ No variable conflicts found")

    # Analyze admin-specific patterns
    print("\n" + "=" * 80)
    print("🔍 ADMIN-SPECIFIC PATTERNS IN PRODUCTION.CSS")
    print("=" * 80)

    admin_patterns = [
        r'\.admin-[\w-]+',
        r'\.command-[\w-]+',
        r'\.sidebar-[\w-]+',
        r'\.tab-[\w-]+',
        r'\.metric-[\w-]+',
    ]

    admin_classes_in_prod = []
    for pattern in admin_patterns:
        matches = re.findall(pattern, production_content)
        admin_classes_in_prod.extend(matches)

    admin_classes_in_prod = list(set(admin_classes_in_prod))

    if admin_classes_in_prod:
        print(f"\nFound {len(admin_classes_in_prod)} admin-specific classes in production.css:")
        for cls in sorted(admin_classes_in_prod)[:25]:
            print(f"   • {cls}")
        if len(admin_classes_in_prod) > 25:
            print(f"   ... and {len(admin_classes_in_prod) - 25} more")
        print("\n💡 These should be moved to enterprise-admin.css")

    # Analyze frontend-specific patterns
    print("\n" + "=" * 80)
    print("🔍 FRONTEND-SPECIFIC PATTERNS IN PRODUCTION.CSS")
    print("=" * 80)

    frontend_patterns = [
        r'\.hub-[\w-]+',
        r'\.section-[\w-]+',
        r'\.module-[\w-]+',
        r'\.package-[\w-]+',
        r'\.form-[\w-]+',
    ]

    frontend_classes_in_prod = []
    for pattern in frontend_patterns:
        matches = re.findall(pattern, production_content)
        frontend_classes_in_prod.extend(matches)

    frontend_classes_in_prod = list(set(frontend_classes_in_prod))

    if frontend_classes_in_prod:
        print(f"\nFound {len(frontend_classes_in_prod)} frontend-specific classes in production.css:")
        for cls in sorted(frontend_classes_in_prod)[:25]:
            print(f"   • {cls}")
        if len(frontend_classes_in_prod) > 25:
            print(f"   ... and {len(frontend_classes_in_prod) - 25} more")
        print("\n💡 These should be moved to hub-components.css")

    # Recommendations
    print("\n" + "=" * 80)
    print("📋 MIGRATION RECOMMENDATIONS")
    print("=" * 80)

    print("\n1. CRITICAL ACTIONS REQUIRED:")
    if class_conflicts:
        print("   ⚠️  Resolve class name conflicts by:")
        print("      • Adding .admin-backend prefix to enterprise components")
        print("      • Adding .hub-frontend prefix to existing components")
        print("      • Or use separate CSS bundles per context")
    else:
        print("   ✅ No immediate conflicts detected")

    print("\n2. MIGRATION STRATEGY:")
    print("   Step 1: Add body class to all pages")
    print("      • Admin pages: <body class='admin-backend'>")
    print("      • Frontend pages: <body class='hub-frontend'>")
    print("")
    print("   Step 2: Scope enterprise CSS")
    print("      • Prefix all selectors with .admin-backend")
    print("      • Test admin pages in isolation")
    print("")
    print("   Step 3: Extract frontend CSS")
    print(f"      • Move {len(admin_classes_in_prod)} admin classes to enterprise-admin.css")
    print(f"      • Move {len(frontend_classes_in_prod)} frontend classes to hub-components.css")
    print("      • Keep shared utilities in production.css")
    print("")
    print("   Step 4: Create separate bundles")
    print("      • admin-bundle.css (enterprise only)")
    print("      • hub-bundle.css (frontend only)")
    print("      • shared.css (utilities, reset, variables)")

    print("\n3. FILE SIZE IMPACT:")
    prod_size_kb = len(production_content) / 1024
    ent_size_kb = (len(enterprise_design_content) + len(enterprise_components_content)) / 1024
    print(f"   • Current production.css: {prod_size_kb:.1f} KB")
    print(f"   • New enterprise bundle: ~{ent_size_kb:.1f} KB")
    print(f"   • Estimated frontend bundle: ~{(prod_size_kb - ent_size_kb):.1f} KB")
    print(f"   • Potential savings: Load only what's needed per page")

    # Export results
    results = {
        'production_stats': {
            'selectors': len(prod_selectors),
            'classes': len(prod_classes),
            'variables': len(prod_variables),
            'size_kb': prod_size_kb,
        },
        'enterprise_stats': {
            'selectors': len(ent_selectors),
            'classes': len(ent_classes),
            'variables': len(ent_variables),
            'size_kb': ent_size_kb,
        },
        'conflicts': {
            'class_conflicts': sorted(list(class_conflicts)),
            'variable_conflicts': sorted(list(var_conflicts)),
        },
        'admin_specific': sorted(admin_classes_in_prod),
        'frontend_specific': sorted(frontend_classes_in_prod),
    }

    output_file = Path(__file__).parent.parent / 'css-audit-results.json'
    with open(output_file, 'w') as f:
        json.dump(results, f, indent=2)

    print(f"\n📄 Full results exported to: {output_file.name}")
    print("=" * 80)

if __name__ == '__main__':
    main()
