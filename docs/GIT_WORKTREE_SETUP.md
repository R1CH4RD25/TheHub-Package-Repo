# Git Worktree Setup - Team Workflow Guide

## 🎯 Problem Solved

**Issue:** Two teams editing the same files in the same directory caused constant conflicts:
- Changes kept reverting
- CSS builds would overwrite each other's work
- File system race conditions between VS Code instances
- Merge conflicts on every pull

**Solution:** Git Worktrees - separate physical directories for each team/branch.

---

## 📂 Directory Structure

```
/var/www/woodson/
├── thehub → thehub-admin (symlink - points to admin, used by web server)
├── thehub-admin/        [v2.0 branch - ADMIN TEAM]
│   ├── public/admin/    (Admin Dashboard work)
│   ├── src/Components/  (Shared components)
│   └── public/assets/css/admin/ (Admin-specific CSS)
│
└── thehub-mgmt/         [mgmt-console-refactor branch - MANAGEMENT TEAM]
    ├── public/management/ (Management Console work)
    ├── src/Components/    (Shared components)
    └── public/assets/css/mgmt/ (Management-specific CSS)
```

---

## 👥 Team Assignments

### **Admin Team** (User Management, Roles, Settings)
- **Directory:** `/var/www/woodson/thehub-admin`
- **Branch:** `v2.0`
- **Responsibilities:**
  - Admin Dashboard (`public/admin/`)
  - User management features
  - Site settings
  - Admin-specific CSS (`admin/`, `admin-bundle.css`)
  
### **Management Team** (Workflow Console)
- **Directory:** `/var/www/woodson/thehub-mgmt`
- **Branch:** `mgmt-console-refactor`
- **Responsibilities:**
  - Management Console (`public/management/`)
  - Section/module workflows
  - Management-specific CSS (`mgmt/`, `mgmt-bundle.css`)

---

## 🚀 Daily Workflow

### **Admin Team - Starting Work**

```bash
# 1. Navigate to admin worktree
cd /var/www/woodson/thehub-admin

# 2. Pull latest changes
git pull origin v2.0

# 3. Rebuild CSS (in case shared files changed)
./build-css-production.sh

# 4. Open VS Code
code .

# 5. Work on your features...
```

### **Management Team - Starting Work**

```bash
# 1. Navigate to management worktree
cd /var/www/woodson/thehub-mgmt

# 2. Pull latest changes
git pull origin mgmt-console-refactor

# 3. Rebuild CSS (in case shared files changed)
./build-css-production.sh

# 4. Open VS Code
code .

# 5. Work on your features...
```

---

## 💾 Committing and Pushing

### **Admin Team**

```bash
cd /var/www/woodson/thehub-admin

# Stage changes
git add -A

# Commit with descriptive message
git commit -m "🎨 Add user role management feature"

# Push to v2.0 branch
git push origin v2.0
```

### **Management Team**

```bash
cd /var/www/woodson/thehub-mgmt

# Stage changes
git add -A

# Commit with descriptive message
git commit -m "✨ Add workflow approval system"

# Push to mgmt-console-refactor branch
git push origin mgmt-console-refactor
```

---

## 🔄 Pulling Updates from Other Team

### **Admin Team - Getting Management Updates**

```bash
cd /var/www/woodson/thehub-admin

# Pull from v2.0 (which gets merged mgmt changes)
git pull origin v2.0

# If management made shared CSS changes, rebuild
./build-css-production.sh
```

### **Management Team - Getting Admin Updates**

```bash
cd /var/www/woodson/thehub-mgmt

# Pull from your branch
git pull origin mgmt-console-refactor

# If admin made shared CSS changes, rebuild
./build-css-production.sh
```

---

## 🤝 Shared Files - Coordination Required

Both teams can edit these files, but **communicate first**:

### **Shared CSS:**
- ✅ `public/assets/css/shared/enterprise-design-system.css`
- ✅ `public/assets/css/shared/enterprise-components.css`
- ✅ `public/assets/css/shared/enterprise-header-sidebar.css`
- ✅ `public/assets/css/shared/enterprise-footer.css`

### **Shared Components:**
- ✅ `src/Components/EnterpriseHeader.php`
- ✅ `src/Components/EnterpriseSidebar.php`
- ✅ `src/Components/EnterpriseFooter.php`
- ✅ `src/Components/UserProfileDropdown.php`

### **Coordination Protocol:**

1. **Announce in team chat:** "Working on enterprise-header-sidebar.css lines 100-150 (adding dropdown styles)"
2. **Make focused changes:** Edit only what you need
3. **Commit frequently:** Small commits = fewer conflicts
4. **Notify when done:** "Pushed header changes to v2.0, please pull"

---

## 🔀 Merging Branches

When both teams are ready to combine work:

### **Option A: Merge Management into Admin (v2.0)**

```bash
cd /var/www/woodson/thehub-admin
git checkout v2.0
git pull origin v2.0
git merge mgmt-console-refactor -m "Merge management console updates"

# Fix any conflicts
# Test thoroughly

git push origin v2.0
```

### **Option B: Create Pull Request on GitHub**

1. Go to: https://github.com/R1CH4RD25/TheHub/pulls
2. Click "New Pull Request"
3. Base: `v2.0` ← Compare: `mgmt-console-refactor`
4. Review changes, add description
5. Merge when approved

---

## ⚠️ Conflict Resolution

If you get merge conflicts on shared files:

```bash
# 1. See conflicted files
git status

# 2. Open each file, look for:
<<<<<<< HEAD
Your changes
=======
Their changes
>>>>>>> branch-name

# 3. Manually merge, keeping both changes when possible

# 4. Mark resolved
git add <resolved-file>

# 5. Complete merge
git commit
```

---

## 🛠️ Worktree Management Commands

### **Check all worktrees:**
```bash
cd /var/www/woodson/thehub-admin
git worktree list
```

### **Add new worktree:**
```bash
git worktree add ../thehub-feature feature-branch-name
```

### **Remove worktree:**
```bash
git worktree remove ../thehub-feature
# Or if broken:
rm -rf ../thehub-feature
git worktree prune
```

### **Repair broken worktree:**
```bash
cd /var/www/woodson/thehub-admin
git worktree repair
```

---

## 🌐 Web Server Access

The symlink `/var/www/woodson/thehub → thehub-admin` means:

- ✅ **https://hub.woodsonisd.net** serves from `thehub-admin` (admin's v2.0 branch)
- ✅ Both teams can test their work at the same URL
- ✅ To test management branch: temporarily change symlink

```bash
# Test management team's work
cd /var/www/woodson
rm thehub
ln -s thehub-mgmt thehub
# Visit https://hub.woodsonisd.net

# Switch back to admin
rm thehub
ln -s thehub-admin thehub
```

---

## ✅ Benefits Summary

| Before Worktrees | After Worktrees |
|-----------------|-----------------|
| ❌ Constant file conflicts | ✅ Isolated workspaces |
| ❌ Changes overwritten | ✅ No file collisions |
| ❌ CSS builds conflict | ✅ Independent builds |
| ❌ Can't work simultaneously | ✅ Parallel development |
| ❌ Merge hell | ✅ Clean merges |

---

## 📞 Quick Reference

**Admin Team:**
- Work in: `/var/www/woodson/thehub-admin`
- Branch: `v2.0`
- Push to: `origin v2.0`

**Management Team:**
- Work in: `/var/www/woodson/thehub-mgmt`
- Branch: `mgmt-console-refactor`
- Push to: `origin mgmt-console-refactor`

**Both Teams:**
- Communicate before editing shared files
- Pull frequently
- Rebuild CSS after pulling: `./build-css-production.sh`
- Commit small, focused changes
- Use emoji prefixes: 🎨 ✨ 🐛 🔒 📝 ♻️ 🚀

---

## 🆘 Troubleshooting

**Q: I'm in the wrong directory!**
```bash
cd /var/www/woodson/thehub-admin   # Admin team
cd /var/www/woodson/thehub-mgmt    # Management team
```

**Q: Git says "not a git repository"**
```bash
# You're in /var/www/woodson, navigate to worktree:
cd thehub-admin  # or thehub-mgmt
```

**Q: My changes disappeared!**
- Check if you're in the right worktree directory
- The other team's changes are in their worktree
- Run `git worktree list` to see all directories

**Q: Merge conflict on shared CSS**
- Coordinate in team chat
- Usually safe to keep both changes (different selectors)
- Test after merging

**Q: Want to see other team's latest work**
```bash
cd /var/www/woodson/thehub-mgmt    # (or thehub-admin)
git pull
./build-css-production.sh
# Open their files to review
```

---

**Created:** November 19, 2025  
**Last Updated:** November 19, 2025  
**Maintainer:** Admin Team  
**Questions:** Ask in team chat or review this doc
