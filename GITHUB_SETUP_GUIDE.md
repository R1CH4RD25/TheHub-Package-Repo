# GitHub Repository Setup Guide
## The Hub Package Repository

**Date:** October 22, 2025  
**Repository Name:** `TheHub-Package-Repo`  
**Purpose:** Official package repository for The Hub dynamic sections

---

## 📝 GitHub Web Interface Setup

### Step 1: Create Repository

**Owner:** `woodsonisd` (or your organization)  
**Repository name:** `TheHub-Package-Repo`  
**Description:**
```
Official package repository for The Hub dynamic sections. Browse, download, and share validated .hubpkg packages for custom forms, reporting tools, and workflows.
```

**Visibility:** ✅ Public  
**Add README file:** ✅ Yes  
**Add .gitignore:** ✅ Yes (select "Composer" template)  
**Choose a license:** ✅ MIT License  

**Copilot jumpstart prompt:** (Leave empty - we'll set up manually)

### Step 2: Initial Files

After creating the repository, add these files:

#### 1. Replace README.md
Use the content from: `/var/www/woodson/thehub/temp/PACKAGE_REPO_README.md`

#### 2. Add CONTRIBUTING.md
Use the content from: `/var/www/woodson/thehub/temp/CONTRIBUTING.md`

#### 3. Create Directory Structure
```bash
packages/
├── reporting/
├── forms/
├── workflows/
├── analytics/
└── integrations/

docs/
└── (documentation files)

.github/
└── ISSUE_TEMPLATE/
    └── package_submission.md
```

#### 4. Add .gitignore
```gitignore
# Editor files
.vscode/
.idea/
*.swp
*.swo
*~

# OS files
.DS_Store
Thumbs.db

# Temporary files
*.tmp
*.bak
temp/
tmp/

# Logs
*.log

# Package development
*.hubpkg.draft
*.hubpkg.test
```

---

## 📦 Adding Bullying Report Package

### Directory Structure
```bash
packages/reporting/bullying-report/
├── bullying-report_1.0.0.hubpkg
├── README.md
├── CHANGELOG.md
└── screenshots/
    ├── form-view.png
    ├── list-view.png
    └── admin-view.png
```

### Files to Upload

1. **Package File:** `/var/www/woodson/thehub/packages/local/bullying-report_1.0.0.hubpkg`
2. **README.md:** Use template from `PACKAGE_REPO_STRUCTURE.md`
3. **CHANGELOG.md:** Use template from `PACKAGE_REPO_STRUCTURE.md`
4. **Screenshots:** Take screenshots from The Hub admin interface

---

## 🚀 Command Line Setup (Alternative)

If you prefer command line setup:

```bash
# Clone the new empty repository
git clone https://github.com/woodsonisd/TheHub-Package-Repo.git
cd TheHub-Package-Repo

# Create directory structure
mkdir -p packages/{reporting,forms,workflows,analytics,integrations}
mkdir -p docs
mkdir -p .github/ISSUE_TEMPLATE

# Copy README and CONTRIBUTING files
cp /var/www/woodson/thehub/temp/PACKAGE_REPO_README.md README.md
cp /var/www/woodson/thehub/temp/CONTRIBUTING.md CONTRIBUTING.md
cp /var/www/woodson/thehub/temp/PACKAGE_SUBMISSION_TEMPLATE.md .github/ISSUE_TEMPLATE/package_submission.md

# Create .gitignore
cat > .gitignore << 'EOF'
# Editor files
.vscode/
.idea/
*.swp
*.swo
*~

# OS files
.DS_Store
Thumbs.db

# Temporary files
*.tmp
*.bak
temp/
tmp/

# Logs
*.log

# Package development
*.hubpkg.draft
*.hubpkg.test
EOF

# Add bullying report package
mkdir -p packages/reporting/bullying-report/screenshots
cp /var/www/woodson/thehub/packages/local/bullying-report_1.0.0.hubpkg \
   packages/reporting/bullying-report/

# Create package README
cat > packages/reporting/bullying-report/README.md << 'EOF'
# Bullying Report Package
(Use template from PACKAGE_REPO_STRUCTURE.md)
EOF

# Create package CHANGELOG
cat > packages/reporting/bullying-report/CHANGELOG.md << 'EOF'
# Changelog - Bullying Report Package
(Use template from PACKAGE_REPO_STRUCTURE.md)
EOF

# Commit and push
git add .
git commit -m "Initial repository setup with bullying-report v1.0.0"
git push origin main
```

---

## 🔄 Next Steps: Auto-Update Integration

### Phase 2: Repository Manager Class

Create `/var/www/woodson/thehub/src/RepositoryManager.php`:

**Features:**
- Fetch package list from GitHub API
- Check for updates (compare versions)
- Download `.hubpkg` files
- Verify package integrity (checksums)
- Display in Updates tab

**GitHub API Endpoints:**
```
GET https://api.github.com/repos/woodsonisd/TheHub-Package-Repo/contents/packages
GET https://api.github.com/repos/woodsonisd/TheHub-Package-Repo/releases
```

### Phase 3: Update Checker UI

**Updates Tab Enhancement:**
```javascript
async function checkForUpdates() {
    const response = await fetch('/api/packages.php?action=check_updates');
    const result = await response.json();
    
    if (result.updates_available > 0) {
        // Show notification badge
        // Display available updates in table
    }
}
```

---

## 📋 Repository Configuration

### Settings → General

**Features:**
- ✅ Issues (for bug reports and package requests)
- ✅ Discussions (for community Q&A)
- ✅ Projects (optional - for roadmap tracking)
- ❌ Wiki (use docs/ folder instead)

### Settings → Branches

**Branch Protection Rules for `main`:**
- ✅ Require pull request before merging
- ✅ Require approvals (1 reviewer)
- ✅ Require status checks to pass
- ✅ Do not allow bypassing

### Settings → Pages (Optional)

Enable GitHub Pages to host package documentation website:
- Source: `Deploy from main branch`
- Folder: `/docs`
- Custom domain: `packages.thehub.woodsonisd.net` (optional)

---

## 🏷️ GitHub Labels

Create these labels for issue management:

| Label | Color | Description |
|-------|-------|-------------|
| `package-submission` | `#0366d6` | New package submission |
| `needs-review` | `#fbca04` | Awaiting review |
| `approved` | `#28a745` | Ready to merge |
| `changes-requested` | `#d93f0b` | Needs modifications |
| `security` | `#d73a4a` | Security vulnerability |
| `bug` | `#d73a4a` | Bug in existing package |
| `enhancement` | `#a2eeef` | Feature request |
| `documentation` | `#0075ca` | Documentation update |

---

## 📖 Additional Documentation Files

### docs/SECURITY.md
```markdown
# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in any package:

1. **DO NOT** open a public issue
2. Email security@woodsonisd.net with:
   - Package name and version
   - Description of vulnerability
   - Proof of concept (if possible)
   - Suggested fix (if known)

3. We will respond within 48 hours
4. Fix will be developed privately
5. Security advisory published after fix deployed

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | ✅ Yes    |
| < 1.0   | ❌ No     |

## Security Best Practices

All packages must follow these security guidelines:
- Input validation on all user-provided data
- Prepared statements for database queries
- XSS protection on output
- File upload restrictions
- No hardcoded credentials
- Audit logging for sensitive actions
```

### docs/PACKAGE_SPECIFICATION.md
(Complete technical specification of .hubpkg format)

---

## 🎯 Success Metrics

Track these metrics in repository:

1. **Total Packages:** Target 10+ by end of year
2. **Downloads:** Track via GitHub releases
3. **Issues Resolved:** Response time < 48 hours
4. **Active Contributors:** Goal of 3+ package authors
5. **Package Quality:** 100% pass all validation checks

---

## 📞 Support Channels

1. **GitHub Issues:** Bug reports, feature requests
2. **GitHub Discussions:** Q&A, package ideas
3. **Email:** security@woodsonisd.net (security only)
4. **Documentation:** README, CONTRIBUTING, docs/

---

## ✅ Launch Checklist

Before announcing repository publicly:

- [ ] Repository created with correct visibility (Public)
- [ ] README.md complete with bullying-report entry
- [ ] CONTRIBUTING.md uploaded
- [ ] .gitignore configured
- [ ] MIT License added
- [ ] Issue templates created
- [ ] Labels configured
- [ ] Branch protection enabled
- [ ] Bullying report package uploaded with screenshots
- [ ] Package README and CHANGELOG complete
- [ ] All links in README tested
- [ ] Repository description clear and concise

---

**Repository URL (after creation):**  
`https://github.com/woodsonisd/TheHub-Package-Repo`

**Clone URL:**  
`git clone https://github.com/woodsonisd/TheHub-Package-Repo.git`

**Package Browser URL:**  
`https://github.com/woodsonisd/TheHub-Package-Repo/tree/main/packages`
