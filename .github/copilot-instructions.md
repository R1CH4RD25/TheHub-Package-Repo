# Copilot Code Review Instructions for Package Repository

## Auto-Approval Criteria

You are reviewing a package repository for The Hub system. Auto-approve pull requests that meet these criteria:

### ✅ Always Approve:
1. **Package reorganization** - Moving .hubpkg files between directories
2. **Documentation updates** - README.md, CHANGELOG.md additions/edits
3. **New package additions** - Adding new .hubpkg files with proper structure
4. **Metadata updates** - Changes to package metadata within .hubpkg files

### ⚠️ Review Carefully (but still approve if valid):
1. **Version bumps** - Ensure semantic versioning is correct
2. **Breaking changes** - Check CHANGELOG documents the changes
3. **Schema changes** - Verify field definitions are valid

### ❌ Flag for Manual Review:
1. **Deleting packages** - Require manual confirmation
2. **Security-related changes** - Authentication, permissions, or access control
3. **Malformed JSON** - Invalid .hubpkg structure

## Review Process

For each PR:
1. ✅ Verify files changed are within `packages/` directory
2. ✅ Check .hubpkg files have valid JSON structure
3. ✅ Confirm README/documentation is clear
4. ✅ Validate directory structure follows tagging conventions
5. ✅ **AUTO-APPROVE** if all criteria met

## Package Repository Context

- This is a package distribution repository (not application code)
- Packages are JSON configuration files (.hubpkg format)
- Directory structure determines automatic tagging
- Breaking changes are acceptable with proper documentation
- Owner is sole maintainer - trust their judgment

## Response Format

When approving:
```
✅ APPROVED - [Reason]
- Valid package structure
- Documentation included
- Follows tagging conventions
```

When flagging:
```
⚠️ NEEDS REVIEW - [Concern]
- [Specific issue to check]
```

## Default Action
**When in doubt: APPROVE.** This is a configuration repository, not production code. The owner can always revert if needed.
