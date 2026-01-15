# Package Archive

This directory contains previous versions of packages that have been superseded by newer releases.

## Purpose

- Maintains version history for reference
- Excludes old versions from package discovery to prevent clutter
- Preserves packages for rollback or compatibility needs

## Structure

Archive mirrors the main package structure:

```
archive/
├── operations/
│   └── fleet/
│       └── vehicle-maintenance/  (v1.0.0)
├── finance/
│   └── ...
└── student/
    └── ...
```

## Discovery Behavior

Packages in `archive/` are:
- ✅ Preserved in Git history
- ✅ Accessible via GitHub directly
- ❌ Hidden from TheHub Package Repository discovery
- ❌ Not shown in package installation UI

## When to Archive

Archive a package version when:
1. A new major version is released (v2.0.0 replaces v1.0.0)
2. Package is deprecated but needs to remain available
3. Breaking changes require keeping old version accessible

## Accessing Archived Versions

If you need to install an archived package version:
1. Navigate to the archive folder on GitHub
2. Download the `.hubpkg` file directly
3. Upload manually via TheHub Admin → Packages → Upload Package
