# Migration Status: maintenance → thehub

**Date:** October 21, 2025  
**Status:** ✅ MIGRATION COMPLETE - DNS & SSL PENDING

---

## ✅ Completed Steps

### 1. Directory Migration
- ✅ Renamed: `/var/www/woodson/maintenance` → `/var/www/woodson/thehub`
- ✅ All files and structure intact
- ✅ Backup created: `.env.backup`

### 2. Apache Configuration
- ✅ Old sites disabled: `maintenance.woodsonisd.net.conf` & SSL config
- ✅ New site enabled: `hub.woodsonisd.net.conf`
- ✅ Apache reloaded successfully
- ✅ Configuration test passed (Syntax OK)
- ✅ Port 80 active for hub.woodsonisd.net

### 3. Application Configuration
- ✅ `.env` file updated with new domain:
  - `APP_URL=https://hub.woodsonisd.net`
  - `GOOGLE_REDIRECT_URI=https://hub.woodsonisd.net/google_login.php`
  - `MAIL_FROM_ADDRESS=hub.woodsonisd.net`
  - `MAIL_FROM_NAME="Woodson ISD Hub"`
  - Service account path: `/var/www/woodson/thehub/config/...`

### 4. Code Updates (Prior to Migration)
- ✅ Fixed hub.php 500 error (Database class method calls)
- ✅ Updated all navigation links from `/sections.php` → `/hub.php`
- ✅ Added redirect in sections.php for backward compatibility
- ✅ Fixed MIME type errors (sections.php references)

---

## ⏳ PENDING - Manual Steps Required

### 1. 🌐 DNS Configuration (CRITICAL - DO FIRST)
**Action:** Update DNS A record  
**Domain:** `hub.woodsonisd.net`  
**IP:** `10.49.4.10` (your server)

**Verify DNS propagation:**
```bash
nslookup hub.woodsonisd.net
ping hub.woodsonisd.net
```

### 2. 🔒 SSL Certificate (DO AFTER DNS)
**Run after DNS is active:**
```bash
sudo certbot --apache -d hub.woodsonisd.net
```

**Verify:**
```bash
curl -I https://hub.woodsonisd.net
```

**Test auto-renewal:**
```bash
sudo certbot renew --dry-run
```

### 3. 🔑 Google OAuth Console Update
**URL:** https://console.cloud.google.com/apis/credentials

**Steps:**
1. Navigate to OAuth 2.0 Client IDs
2. Find your client ID: `11429203657-79r4fbu4ujsd5e5d1a2g6pfnf7l8a1tg`
3. Add **Authorized redirect URI:**
   ```
   https://hub.woodsonisd.net/google_login.php
   ```
4. **Keep old URI active** until testing complete:
   ```
   https://maintenance.woodsonisd.net/google_login.php
   ```
5. After testing, remove old URI

### 4. 📧 Email Configuration (Optional)
If you want a dedicated email:
- Create `hub@woodsonisd.net` in Google Workspace
- Or keep as alias/forwarding address

---

## 🏗️ System Architecture (Current State)

### Hub System
- **Unified Landing Page:** `/public/hub.php`
- **Dynamic Title:** "Staff Resources Hub" or "Student Hub" based on `user_group` column
- **User Classification:** 
  - Priority: `users.user_group` → `users.role` → default 'staff'
  - Student detection: `user_group = 'student'` OR `role = 'student'`

### Roles System
- **Centralized:** `src/Roles.php` (single source of truth)
- **13 Roles Total:** super_admin, admin, principal, counselor, substitute_manager, **business_manager** (new), maintenance_director, custodial_manager, maintenance_staff, custodial, cafeteria, student, staff
- **Role Management UI:** Admin dashboard → Role Management tab
  - Active/inactive toggles
  - Role settings stored in `role_settings` table
  - Protected roles: super_admin, staff (cannot disable)

### Database
- **Name:** `woodson_maintenance` (unchanged - no need to rename)
- **Host:** localhost
- **User:** WISDAdmin
- **Tables:**
  - `users` - Added `user_group` ENUM column (staff, student, maintenance, custodial, cafeteria, other)
  - `role_settings` - New table for active/inactive role management
  - `sections` - 7 active sections (student-hub/staff-hub deleted)
  - `section_role_access` - Role-based section visibility
  - All tables updated with `business_manager` role

### Sections Status
**Active Sections (7):**
1. maintenance-fuel-travel (recording)
2. vehicle-maintenance (recording)
3. reimbursement-request (request_form)
4. bullying-report (request_form)
5. counselor-request (request_form)
6. substitute-request (request_form - inactive)
7. travel-request (request_form - inactive)

**Deleted:** student-hub, staff-hub (now unified in hub.php)

### Authentication
- **Google OAuth:** Active
- **Redirect URI:** https://hub.woodsonisd.net/google_login.php
- **Auto-approval:** Staff group email verified
- **Session lifetime:** 7200 seconds (2 hours)

---

## 📂 Key Files & Locations

### Application Root
```
/var/www/woodson/thehub/
├── public/
│   ├── hub.php              # Main landing page (unified hub)
│   ├── sections.php         # Redirects to hub.php
│   ├── index.php            # Redirects to hub.php
│   ├── admin/               # Admin dashboard
│   ├── modules/             # Section modules
│   └── api/                 # API endpoints
├── src/
│   ├── Roles.php            # Centralized role definitions
│   ├── Database.php         # Database wrapper
│   ├── Auth.php             # Authentication
│   └── SectionAccess.php    # Section permission logic
├── .env                     # Configuration (updated)
├── .env.backup              # Backup of old config
└── apache/
    └── hub.woodsonisd.net.conf  # Apache config
```

### Apache Configuration
- **Active config:** `/etc/apache2/sites-available/hub.woodsonisd.net.conf`
- **Enabled:** `/etc/apache2/sites-enabled/hub.woodsonisd.net.conf`
- **Document root:** `/var/www/woodson/thehub/public`

### Logs
- **Apache error:** `/var/log/apache2/hub.woodsonisd.net-error.log`
- **Apache access:** `/var/log/apache2/hub.woodsonisd.net-access.log`
- **Old logs:** `/var/log/apache2/maintenance.woodsonisd.net-*.log` (still exist)

### SSL Certificates (After Certbot)
Will be at: `/etc/letsencrypt/live/hub.woodsonisd.net/`

---

## 🧪 Testing Checklist

After DNS + SSL + OAuth are configured:

- [ ] Visit https://hub.woodsonisd.net
- [ ] Test Google OAuth login
- [ ] Verify correct hub displays (Staff vs Student)
- [ ] Test section access (click on cards)
- [ ] Check admin dashboard works
- [ ] Test role management UI
- [ ] Verify form submissions work
- [ ] Check email notifications (if configured)
- [ ] Test logout/login flow

---

## 🔄 Rollback Plan

If something goes wrong:

```bash
# Disable new site
sudo a2dissite hub.woodsonisd.net.conf

# Rename back
sudo mv /var/www/woodson/thehub /var/www/woodson/maintenance

# Restore .env
sudo cp /var/www/woodson/maintenance/.env.backup /var/www/woodson/maintenance/.env

# Re-enable old sites
sudo a2ensite maintenance.woodsonisd.net.conf
sudo a2ensite maintenance.woodsonisd.net-le-ssl.conf

# Reload Apache
sudo systemctl reload apache2
```

---

## 🎯 Immediate Next Action

**DO THIS NOW:**
1. Update DNS: `hub.woodsonisd.net` → `10.49.4.10`
2. Wait for DNS propagation (5-30 minutes typically)
3. Run: `sudo certbot --apache -d hub.woodsonisd.net`
4. Update Google OAuth redirect URI
5. Test login at https://hub.woodsonisd.net

---

## 📞 Important Information

### Database Credentials
- Host: localhost
- Database: woodson_maintenance
- User: WISDAdmin
- Password: (in .env file)

### Super Admin
- Email: richard.sullivan@woodsonisd.net
- Has full access to all features

### Google OAuth
- Client ID: 11429203657-79r4fbu4ujsd5e5d1a2g6pfnf7l8a1tg
- Console: https://console.cloud.google.com/apis/credentials

### Server Info
- IP: 10.49.4.10
- OS: Linux (Ubuntu/Debian based)
- Web Server: Apache 2.4
- PHP: 8.3.6
- Database: MariaDB

---

## 🚀 Recent Work Completed

1. **Hub System Refactor** - Unified student-hub and staff-hub into single dynamic landing page
2. **Role Management System** - Built complete UI for enabling/disabling roles
3. **Business Manager Role** - Added new role with hierarchy 55
4. **User Group System** - Added `user_group` column for student/staff classification
5. **Navigation Updates** - Fixed all links to point to /hub.php
6. **Database Method Fix** - Corrected hub.php to use Database wrapper methods
7. **Migration to thehub** - Renamed directory and updated all configurations

---

## 📝 Notes for New Copilot Session

- Project is fully functional, just needs DNS + SSL + OAuth update
- All code is working (tested locally)
- Database is intact and unchanged
- Main pending work: student auto-detection logic (email patterns, Google Groups)
- Future enhancements: Complete inactive form modules, notification system
- No critical issues or blockers

**STATUS:** Ready for production once DNS/SSL/OAuth are configured! 🎉
