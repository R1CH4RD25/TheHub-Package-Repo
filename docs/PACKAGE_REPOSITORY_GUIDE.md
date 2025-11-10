# Package Repository Guide

## Overview

The Hub uses GitHub as a package repository for discovering and installing community packages. The system searches for `.hubpkg` files in the configured repository and allows one-click installation.

---

## Current Repository

**Location:** https://github.com/R1CH4RD25/TheHub-Package-Repo  
**Status:** 🟡 Empty (contains directory structure only)  
**Access:** Public (anyone can view, install requires admin role)

---

## How It Works

### 1. **Package Discovery**
When users click "Browse Available Packages":
- System calls `/api/package-discovery.php`
- PHP searches GitHub repo using GitHub API:
  - Root directory
  - `/packages/` subdirectory (recursive)
- Looks for files ending in `.hubpkg`
- Returns list with metadata (name, version, size, download URL)

### 2. **Package Installation**
When admin clicks "Install Package":
- Downloads `.hubpkg` file from GitHub raw URL
- Saves to `/uploads/` directory
- Validates package structure (ZIP format check)
- Adds to `section_packages` table
- Runs standard validation process
- Makes available for installation

### 3. **Version Management**
Filename convention for automatic version detection:
```
package-name-v1.0.0.hubpkg
analytics-v2.1.3.hubpkg
custom-forms-v1.5.0.hubpkg
```

System extracts:
- **Package ID:** `analytics`
- **Display Name:** `analytics`
- **Version:** `2.1.3`

---

## Repository Structure

### Recommended Layout
```
TheHub-Package-Repo/
├── README.md                              # Repository documentation
├── CONTRIBUTING.md                        # How to contribute packages
├── LICENSE                                # Package licensing
├── packages/                              # All packages go here
│   ├── analytics/                         # Category folders
│   │   ├── basic-analytics-v1.0.0.hubpkg
│   │   ├── advanced-analytics-v2.0.0.hubpkg
│   │   └── README.md                      # Package descriptions
│   ├── forms/
│   │   ├── custom-forms-v1.0.0.hubpkg
│   │   ├── survey-builder-v1.5.0.hubpkg
│   │   └── README.md
│   ├── workflows/
│   │   ├── approval-workflow-v1.0.0.hubpkg
│   │   ├── ticketing-system-v1.2.0.hubpkg
│   │   └── README.md
│   ├── integrations/
│   │   ├── google-drive-sync-v1.0.0.hubpkg
│   │   ├── microsoft-teams-v1.1.0.hubpkg
│   │   └── README.md
│   └── reporting/
│       ├── executive-dashboard-v1.0.0.hubpkg
│       ├── data-export-v1.0.0.hubpkg
│       └── README.md
└── .github/
    └── workflows/
        └── validate-packages.yml          # Automated package validation
```

---

## Creating .hubpkg Files

### Prerequisites
1. Functional module/section in The Hub
2. Tested and validated
3. Documented configuration

### Export Process

#### Option 1: Using Package Manager UI
1. Navigate to **Admin Dashboard → Packages**
2. Click existing package → **Export**
3. Downloads `.hubpkg` file

#### Option 2: Manual ZIP Creation
```bash
# Create package directory structure
mkdir my-package
cd my-package

# Add required files
echo '{"package_id":"my-package","version":"1.0.0"}' > package.json
cp /path/to/module.php .
cp /path/to/renderer.php .
cp -r /path/to/assets/ .

# Create .hubpkg file (it's just a ZIP)
zip -r ../my-package-v1.0.0.hubpkg .
```

### Required Package Structure
```
package-name.hubpkg (ZIP file containing:)
├── package.json           # Metadata
├── module.php            # Module definition
├── renderer.php          # Renderer class
├── install.sql           # Database schema
├── config.json           # Default configuration
├── README.md             # Documentation
└── assets/               # Optional assets
    ├── js/
    ├── css/
    └── images/
```

### package.json Example
```json
{
  "package_id": "custom-forms",
  "version": "1.0.0",
  "display_name": "Custom Forms Builder",
  "description": "Create and manage custom forms with drag-and-drop interface",
  "author": "Your Name",
  "license": "MIT",
  "hub_version": "1.3+",
  "dependencies": [],
  "capabilities": [
    "forms",
    "submissions",
    "reporting"
  ],
  "section_type": "form",
  "requires_auth": true,
  "minimum_role": "user"
}
```

---

## Publishing Packages

### To GitHub Repository

1. **Fork the Repository**
   ```bash
   # Visit: https://github.com/R1CH4RD25/TheHub-Package-Repo
   # Click "Fork" button
   ```

2. **Clone Your Fork**
   ```bash
   git clone https://github.com/YOUR_USERNAME/TheHub-Package-Repo.git
   cd TheHub-Package-Repo
   ```

3. **Add Your Package**
   ```bash
   # Choose appropriate category
   cp ~/my-package-v1.0.0.hubpkg packages/forms/
   
   # Update category README
   echo "## My Package\nDescription here" >> packages/forms/README.md
   ```

4. **Commit and Push**
   ```bash
   git add packages/forms/my-package-v1.0.0.hubpkg
   git commit -m "Add My Package v1.0.0"
   git push origin main
   ```

5. **Create Pull Request**
   - Visit your fork on GitHub
   - Click "Pull Request"
   - Describe your package
   - Wait for review and merge

### Package Review Criteria
- ✅ Valid `.hubpkg` format (ZIP file)
- ✅ Contains `package.json`
- ✅ No malicious code
- ✅ Follows naming convention
- ✅ Documented in category README
- ✅ Version number is unique

---

## Alternative Hosting Options

### Option 1: GitHub (Current - Recommended)
**Pros:**
- Free hosting
- Built-in version control
- Community contributions
- GitHub Actions for validation
- Global CDN

**Cons:**
- Public repository (packages visible to all)
- Requires internet connectivity

### Option 2: Self-Hosted Repository
**Setup:**
1. Create packages directory on your server:
   ```bash
   mkdir -p /var/www/hub-packages/packages
   ```

2. Update admin.js repository URL:
   ```javascript
   const repositoryUrl = 'https://hub.woodsonisd.net/packages';
   ```

3. Configure web server to serve packages:
   ```apache
   <Directory /var/www/hub-packages>
       Options +Indexes +FollowSymLinks
       AllowOverride None
       Require all granted
   </Directory>
   ```

**Pros:**
- Full control
- Private packages
- No external dependencies

**Cons:**
- Requires server maintenance
- Bandwidth costs
- Manual deployment

### Option 3: Hybrid Approach
- Keep GitHub for public packages
- Add private repository URL config
- Search both sources
- UI lets admins choose source

---

## Configuration

### Current Settings
Located in `/public/assets/js/admin.js`:

```javascript
async function searchPackages() {
    const repositoryUrl = 'https://github.com/R1CH4RD25/TheHub-Package-Repo';
    const owner = 'R1CH4RD25';
    const repo = 'TheHub-Package-Repo';
    // ...
}
```

### Making It Configurable
To allow admins to change repository:

1. Add to `site_settings` table:
   ```sql
   INSERT INTO site_settings (setting_key, setting_value, setting_type, category)
   VALUES ('package_repository_url', 'https://github.com/R1CH4RD25/TheHub-Package-Repo', 'text', 'packages');
   ```

2. Update JavaScript to fetch from settings
3. Add UI in Admin → Settings → Packages

---

## Troubleshooting

### "No packages available"
**Cause:** Repository is empty or unreachable  
**Solution:**
- Check repository exists: https://github.com/R1CH4RD25/TheHub-Package-Repo
- Verify `.hubpkg` files are present
- Check server can access GitHub (firewall/proxy)
- View browser console for API errors

### "Failed to download package"
**Cause:** Invalid download URL or network issue  
**Solution:**
- Check file exists in GitHub repo
- Verify `download_url` field in API response
- Check `/uploads/` directory is writable
- Review PHP error logs

### "Invalid package format"
**Cause:** File is not a valid ZIP  
**Solution:**
- Verify `.hubpkg` file is actually a ZIP
- Test with: `unzip -t package.hubpkg`
- Re-export package if corrupted

### GitHub API Rate Limiting
**Cause:** Too many requests (60/hour unauthenticated)  
**Solution:**
- Add GitHub personal access token
- Update `package-discovery.php` headers:
  ```php
  'Authorization: token YOUR_GITHUB_TOKEN'
  ```
- Implement caching for package list

---

## Security Considerations

### Package Validation
The Hub validates packages before installation:
1. ✅ ZIP format check
2. ✅ `package.json` required
3. ✅ SQL injection prevention
4. ✅ File path traversal prevention
5. ✅ Pending validation status by default

### Best Practices
- Never auto-install packages
- Review package code before installation
- Use version pinning
- Keep packages updated
- Monitor audit logs for package installs

---

## Next Steps

### For Repository Maintainers
1. **Create initial packages:**
   - Export existing well-tested modules
   - Document each package
   - Add to appropriate categories

2. **Set up validation:**
   - GitHub Actions workflow
   - Automated security scanning
   - Version conflict checking

3. **Documentation:**
   - Package catalog in README
   - Installation guide
   - Troubleshooting tips

### For Package Developers
1. **Read CONTRIBUTING.md** in repository
2. **Test thoroughly** before publishing
3. **Follow naming conventions**
4. **Document dependencies**
5. **Version appropriately** (semver)

---

## API Reference

### Search Packages
```javascript
POST /api/package-discovery.php
Content-Type: application/json

{
  "action": "search",
  "repository_url": "https://github.com/R1CH4RD25/TheHub-Package-Repo",
  "owner": "R1CH4RD25",
  "repo": "TheHub-Package-Repo",
  "csrf_token": "..."
}

// Response:
{
  "success": true,
  "packages": [
    {
      "id": "analytics",
      "name": "analytics",
      "filename": "analytics-v2.0.0.hubpkg",
      "download_url": "https://raw.githubusercontent.com/...",
      "size": 123456,
      "version": "2.0.0",
      "description": "Package from R1CH4RD25/TheHub-Package-Repo",
      "path": "packages/analytics/analytics-v2.0.0.hubpkg",
      "is_installed": false
    }
  ]
}
```

### Download Package
```javascript
POST /api/package-discovery.php
Content-Type: application/json

{
  "action": "download",
  "download_url": "https://raw.githubusercontent.com/...",
  "package_name": "analytics-v2.0.0",
  "csrf_token": "..."
}

// Response:
{
  "success": true,
  "message": "Package downloaded successfully",
  "package": {
    "id": 123,
    "package_id": "analytics-v2_0_0",
    "display_name": "analytics-v2.0.0",
    "file_path": "/uploads/analytics-v2_0_0.hubpkg",
    "file_size": 123456
  }
}
```

---

## Resources

- **Repository:** https://github.com/R1CH4RD25/TheHub-Package-Repo
- **Package Manager Code:** `/public/api/package-discovery.php`
- **UI Code:** `/public/assets/js/admin.js` (line 4448+)
- **Package Validation:** `/src/PackageValidator.php`
- **Architecture Docs:** `/docs/MODULAR_ARCHITECTURE.md`

---

**Last Updated:** November 10, 2025  
**Status:** Repository exists but empty - needs packages
