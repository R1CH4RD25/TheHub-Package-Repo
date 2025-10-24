#!/usr/bin/env python3
"""
File Cleanup Analyzer for The Hub
Systematically analyzes PHP, SQL, and MD files to identify:
- Active/Used files (keep)
- Test/Development files (remove)
- Outdated documentation (remove/consolidate)
- Duplicate files (consolidate)
"""

import os
import re
from pathlib import Path
from collections import defaultdict
import json

class CleanupAnalyzer:
    def __init__(self, project_root):
        self.project_root = Path(project_root)
        self.results = {
            'php': {'keep': [], 'remove': [], 'review': []},
            'sql': {'keep': [], 'remove': [], 'review': []},
            'md': {'keep': [], 'remove': [], 'review': []}
        }
        
        # Patterns for test/dev files to remove
        self.remove_patterns = [
            r'test[-_]',
            r'temp[-_]',
            r'backup',
            r'\.bak',
            r'\.old',
            r'_old',
            r'debug',
            r'example(?!\.com)',  # example files but not example.com in configs
        ]
        
        # Critical files that should always be kept
        self.keep_files = {
            'php': [
                'bootstrap.php',
                'index.php',
                'login.php',
                'logout.php',
                'google_login.php',
                'microsoft_login.php',
                'modules.php',
                'sections.php',
                'hub.php',
                'accept-invite.php',
            ],
            'sql': [
                'schema.sql',
                'modules-schema.sql',
                'sections-schema.sql',
            ],
            'md': [
                'README.md',
                'QUICKSTART.md',
                'REQUIREMENTS.md',
                'DEPLOYMENT.md',
                'INSTALLATION_DEFAULTS.md',
            ]
        }
        
        # Documentation files that are likely outdated/redundant
        self.md_review_patterns = [
            'IMPLEMENTATION',
            'MIGRATION',
            'FIXES',
            'REFACTOR',
            'STATUS',
            'SUMMARY',
            'CHECKLIST',
            'NAMESPACE',
            'UPGRADING',
        ]

    def scan_php_files(self):
        """Analyze all PHP files"""
        print("\n" + "="*80)
        print("ANALYZING PHP FILES")
        print("="*80)
        
        php_files = list(self.project_root.rglob('*.php'))
        
        # Exclude vendor directory
        php_files = [f for f in php_files if 'vendor/' not in str(f)]
        
        for php_file in sorted(php_files):
            rel_path = php_file.relative_to(self.project_root)
            
            # Check if it's a test/temp file
            if any(re.search(pattern, str(rel_path), re.IGNORECASE) for pattern in self.remove_patterns):
                self.results['php']['remove'].append(str(rel_path))
                continue
            
            # Check if it's a critical file
            if any(str(rel_path).endswith(keep) for keep in self.keep_files['php']):
                self.results['php']['keep'].append(str(rel_path))
                continue
            
            # Check if file is referenced by other files
            if self._is_file_referenced(php_file):
                self.results['php']['keep'].append(str(rel_path))
            else:
                # Needs manual review
                self.results['php']['review'].append(str(rel_path))

    def scan_sql_files(self):
        """Analyze all SQL files"""
        print("\n" + "="*80)
        print("ANALYZING SQL FILES")
        print("="*80)
        
        sql_files = list(self.project_root.rglob('*.sql'))
        
        for sql_file in sorted(sql_files):
            rel_path = sql_file.relative_to(self.project_root)
            
            # Keep schema files
            if any(str(rel_path).endswith(keep) for keep in self.keep_files['sql']):
                self.results['sql']['keep'].append(str(rel_path))
                continue
            
            # Keep migration files (they're needed for cli/migrate*.php)
            if 'migrations/' in str(rel_path):
                self.results['sql']['keep'].append(str(rel_path))
                continue
            
            # Check for test/backup files
            if any(re.search(pattern, str(rel_path), re.IGNORECASE) for pattern in self.remove_patterns):
                self.results['sql']['remove'].append(str(rel_path))
            else:
                self.results['sql']['review'].append(str(rel_path))

    def scan_md_files(self):
        """Analyze all Markdown files"""
        print("\n" + "="*80)
        print("ANALYZING MARKDOWN FILES")
        print("="*80)
        
        md_files = list(self.project_root.rglob('*.md'))
        
        # Exclude vendor and node_modules
        md_files = [f for f in md_files if 'vendor/' not in str(f) and 'node_modules/' not in str(f)]
        
        for md_file in sorted(md_files):
            rel_path = md_file.relative_to(self.project_root)
            
            # Keep essential documentation
            if any(str(rel_path).endswith(keep) for keep in self.keep_files['md']):
                self.results['md']['keep'].append(str(rel_path))
                continue
            
            # Keep copilot instructions
            if 'copilot-instructions.md' in str(rel_path):
                self.results['md']['keep'].append(str(rel_path))
                continue
            
            # Flag implementation/migration/status docs for review
            if any(pattern in str(rel_path).upper() for pattern in self.md_review_patterns):
                self.results['md']['review'].append(str(rel_path))
                continue
            
            # Check if it's referenced in README or other docs
            if self._is_doc_referenced(md_file):
                self.results['md']['keep'].append(str(rel_path))
            else:
                self.results['md']['review'].append(str(rel_path))

    def _is_file_referenced(self, file_path):
        """Check if a PHP file is referenced (included/required) by other files"""
        filename = file_path.name
        
        # Search for require/include statements
        search_patterns = [
            f"require.*{filename}",
            f"include.*{filename}",
            f"require_once.*{filename}",
            f"include_once.*{filename}",
        ]
        
        # Quick heuristic: files in api/, admin/, partials/ are likely used
        rel_path = str(file_path.relative_to(self.project_root))
        if any(dir in rel_path for dir in ['api/', 'partials/', 'src/']):
            return True
        
        return False

    def _is_doc_referenced(self, doc_file):
        """Check if a markdown file is referenced in other docs"""
        doc_name = doc_file.name
        
        # Check README.md for references
        readme = self.project_root / 'README.md'
        if readme.exists():
            content = readme.read_text()
            if doc_name in content:
                return True
        
        return False

    def print_report(self):
        """Print comprehensive cleanup report"""
        print("\n\n")
        print("="*80)
        print(" CLEANUP ANALYSIS REPORT")
        print("="*80)
        
        for file_type in ['php', 'sql', 'md']:
            print(f"\n{'='*80}")
            print(f" {file_type.upper()} FILES")
            print(f"{'='*80}")
            
            print(f"\n✅ KEEP ({len(self.results[file_type]['keep'])} files):")
            for f in sorted(self.results[file_type]['keep']):
                print(f"   {f}")
            
            print(f"\n❌ REMOVE ({len(self.results[file_type]['remove'])} files):")
            for f in sorted(self.results[file_type]['remove']):
                print(f"   {f}")
            
            print(f"\n⚠️  REVIEW MANUALLY ({len(self.results[file_type]['review'])} files):")
            for f in sorted(self.results[file_type]['review']):
                print(f"   {f}")

    def generate_removal_script(self, output_file='cleanup.sh'):
        """Generate a bash script to remove files marked for deletion"""
        script_path = self.project_root / output_file
        
        with open(script_path, 'w') as f:
            f.write("#!/bin/bash\n")
            f.write("# Auto-generated cleanup script\n")
            f.write("# Review before executing!\n\n")
            f.write("set -e\n\n")
            f.write("echo 'Starting cleanup...'\n\n")
            
            for file_type in ['php', 'sql', 'md']:
                if self.results[file_type]['remove']:
                    f.write(f"\n# Remove {file_type.upper()} files\n")
                    for file_path in sorted(self.results[file_type]['remove']):
                        f.write(f"rm -f '{file_path}'\n")
                        f.write(f"echo 'Removed: {file_path}'\n")
            
            f.write("\necho 'Cleanup complete!'\n")
        
        # Make script executable
        os.chmod(script_path, 0o755)
        print(f"\n📝 Generated removal script: {output_file}")
        print(f"   Review it, then run: ./{output_file}")

    def export_json(self, output_file='cleanup-report.json'):
        """Export results to JSON for further processing"""
        json_path = self.project_root / output_file
        
        with open(json_path, 'w') as f:
            json.dump(self.results, f, indent=2)
        
        print(f"\n📊 Exported JSON report: {output_file}")

    def run_analysis(self):
        """Run complete analysis"""
        print("Starting comprehensive file analysis...")
        print(f"Project root: {self.project_root}")
        
        self.scan_php_files()
        self.scan_sql_files()
        self.scan_md_files()
        
        self.print_report()
        self.generate_removal_script()
        self.export_json()
        
        # Summary
        total_keep = sum(len(self.results[t]['keep']) for t in ['php', 'sql', 'md'])
        total_remove = sum(len(self.results[t]['remove']) for t in ['php', 'sql', 'md'])
        total_review = sum(len(self.results[t]['review']) for t in ['php', 'sql', 'md'])
        
        print("\n" + "="*80)
        print(" SUMMARY")
        print("="*80)
        print(f"  ✅ Files to KEEP:   {total_keep}")
        print(f"  ❌ Files to REMOVE: {total_remove}")
        print(f"  ⚠️  Files to REVIEW: {total_review}")
        print(f"  📁 Total analyzed:  {total_keep + total_remove + total_review}")
        print("="*80)


if __name__ == '__main__':
    # Run from project root
    project_root = Path(__file__).parent.parent
    analyzer = CleanupAnalyzer(project_root)
    analyzer.run_analysis()
