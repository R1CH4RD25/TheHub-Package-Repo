# Contributing to The Hub Package Repository

Thank you for your interest in contributing packages to The Hub ecosystem! This guide will help you submit high-quality packages that benefit the entire community.

## 📋 Before You Submit

### Pre-Submission Checklist

- [ ] Package passes all validation checks in The Hub
- [ ] Package has been tested in a production-like environment
- [ ] All fields have appropriate validation rules
- [ ] Permissions are properly configured
- [ ] Package follows semantic versioning
- [ ] README.md is complete with screenshots
- [ ] CHANGELOG.md documents all features
- [ ] No sensitive data (API keys, passwords, etc.) in package
- [ ] Package ID follows naming convention: `category-package-name`

## 🎯 Package Naming Convention

**Package ID Format:** `category-descriptive-name`

Examples:
- ✅ `bullying-report`
- ✅ `employee-evaluation`
- ✅ `maintenance-request`
- ❌ `MyPackage` (not descriptive)
- ❌ `bullying_report` (use hyphens, not underscores)
- ❌ `report` (too generic)

## 📦 Submission Process

### 1. Fork the Repository
```bash
git clone https://github.com/woodsonisd/TheHub-Package-Repo.git
cd TheHub-Package-Repo
git checkout -b add-your-package-name
```

### 2. Create Package Directory
```bash
mkdir -p packages/[category]/[package-id]/
cd packages/[category]/[package-id]/
```

### 3. Add Required Files

```
packages/[category]/[package-id]/
├── [package-id]_[version].hubpkg     # The package file
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
└── screenshots/                       # At least 2 screenshots
    ├── main-view.png
    └── admin-view.png
```

### 4. Update Main README.md

Add your package to the "Available Packages" table in the main README.md:

```markdown
| **Your Package** | 1.0.0 | Brief description | [Download](packages/category/package-id/package-id_1.0.0.hubpkg) |
```

### 5. Submit Pull Request

```bash
git add .
git commit -m "Add [Package Name] v[version]"
git push origin add-your-package-name
```

Then open a pull request with:
- Clear title: "Add [Package Name] v[version]"
- Description of what the package does
- Screenshots demonstrating functionality
- Any special installation notes

## ✅ Package Quality Standards

### Required Documentation

#### README.md Must Include:
1. **Package name and version**
2. **Clear description** (2-3 sentences)
3. **Feature list** (bullet points)
4. **System requirements**
5. **Installation instructions**
6. **Permission requirements**
7. **Field definitions** (all fields explained)
8. **Screenshots** (minimum 2)
9. **Configuration steps** (if applicable)
10. **Support information**

#### CHANGELOG.md Must Include:
1. Version number and date
2. **Added** - New features
3. **Changed** - Changes in existing functionality
4. **Deprecated** - Soon-to-be removed features
5. **Removed** - Removed features
6. **Fixed** - Bug fixes
7. **Security** - Vulnerability fixes

### Code Quality

#### Security Requirements
- ✅ All user input must be validated
- ✅ No SQL injection vulnerabilities (use prepared statements)
- ✅ XSS protection on all text fields
- ✅ File uploads must validate type and size
- ✅ No hardcoded credentials or API keys
- ✅ Sensitive data must use proper encryption

#### Field Validation
- ✅ All required fields marked as `required: true`
- ✅ Text fields have `max_length` limits
- ✅ Email fields use `type: "email"`
- ✅ Dates use `type: "date"` or `type: "datetime"`
- ✅ Numbers have `min_value` and `max_value` when applicable
- ✅ File uploads have `allowed_extensions` and `max_file_size`

#### Permission Best Practices
- ✅ Use principle of least privilege
- ✅ Separate view/create/edit/delete permissions
- ✅ Document which roles should have access
- ✅ Consider anonymous access only when necessary

### User Experience

#### Form Design
- Clear, descriptive field labels
- Helpful `help_text` for complex fields
- Logical field ordering (related fields grouped)
- Required fields clearly marked
- Validation errors provide actionable feedback

#### Screenshots
- High resolution (at least 1280x720)
- Show actual usage, not just empty forms
- Include both user and admin views
- Annotate if helpful (arrows, highlights)
- PNG format preferred

## 🔍 Review Process

### What We Check

1. **Security Review**
   - No vulnerabilities detected
   - Input validation proper
   - File upload restrictions appropriate

2. **Code Quality**
   - Package validates in The Hub
   - All required fields present
   - Semantic versioning followed
   - No conflicts with existing packages

3. **Documentation**
   - README complete and clear
   - CHANGELOG follows format
   - Screenshots demonstrate functionality
   - Installation steps accurate

4. **Testing**
   - Package installs successfully
   - All fields function as documented
   - Permissions work correctly
   - No errors in browser console

### Review Timeline

- Initial review within 3 business days
- Feedback provided via pull request comments
- Approval/merge within 1 week for compliant submissions

## 🐛 Reporting Issues

### Found a Bug in a Package?

Open an issue with:
- **Package name and version**
- **Expected behavior**
- **Actual behavior**
- **Steps to reproduce**
- **The Hub version**
- **PHP/MySQL versions**
- **Error messages** (if any)

### Security Vulnerabilities

**DO NOT** open public issues for security vulnerabilities. Email security@woodsonisd.net with:
- Package name and version
- Vulnerability description
- Proof of concept (if possible)
- Suggested fix (if known)

## 🆕 Updating Existing Packages

### Version Guidelines

**MAJOR** version (x.0.0):
- Incompatible field changes (renamed/removed fields)
- Breaking permission changes
- Requires manual migration

**MINOR** version (0.x.0):
- New fields added
- New features
- Enhanced functionality
- Backwards-compatible

**PATCH** version (0.0.x):
- Bug fixes
- Documentation updates
- Performance improvements
- No new features

### Update Process

1. Test update path in The Hub (upgrade from previous version)
2. Update version in package file
3. Add new `.hubpkg` file to package directory
4. Update CHANGELOG.md
5. Update README.md version references
6. Update main README.md table
7. Submit pull request titled "Update [Package Name] to v[version]"

## 📜 License

By submitting a package, you agree to:
- License your package under MIT License (or compatible)
- Grant permission for The Hub instances to use/modify your package
- Provide ongoing support (bug fixes, security updates)
- Allow others to fork and extend your package

## 🤝 Community

### Get Help

- **Questions?** Open a discussion in the repository
- **Need guidance?** Review existing packages as examples
- **Technical issues?** Check The Hub documentation

### Package Ideas

Not sure what to build? Check the "Requested Packages" discussion for community needs.

## 📞 Contact

- **General questions:** Open a discussion
- **Bug reports:** Open an issue
- **Security concerns:** security@woodsonisd.net
- **Package submissions:** Pull request

---

Thank you for contributing to The Hub ecosystem! 🎉
