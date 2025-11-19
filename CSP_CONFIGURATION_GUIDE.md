# Content Security Policy (CSP) Configuration Guide

## 🛡️ Overview

Content Security Policy (CSP) is an added layer of security that helps detect and mitigate certain types of attacks, including Cross-Site Scripting (XSS) and data injection attacks.

**Status:** ✅ Phase 2 Complete - Nonces Applied  
**Production Ready:** Staged (headers not enforced yet)

---

## ✅ What's Implemented

### 1. CSP Nonce Generation
- **Location:** `src/bootstrap.php`
- **Function:** `getCspNonce()`
- **Constant:** `CSP_NONCE` (globally available)
- **Storage:** Session-based, regenerated per session
- **Format:** Base64-encoded 16-byte random value

### 2. Nonce Applied To
- ✅ `src/Layout.php` - Header scripts (2 inline blocks)
- ✅ `public/admin/section-config-tab.php`
- ✅ `public/admin/index.php`
- ✅ `public/hub.php` (3 inline scripts)
- ✅ `public/profile.php`
- ✅ `public/login.php`
- ✅ `public/command/submission.php`
- ✅ `public/command/section.php`

### 3. Meta Tag
- Added to `Layout::renderHead()`: `<meta name="csp-nonce" content="<?php echo CSP_NONCE; ?>">`
- Allows JavaScript to read nonce if needed: `document.querySelector('meta[name="csp-nonce"]').content`

---

## 🚀 Enabling CSP Headers (When Ready)

### Option 1: PHP Headers (Recommended for Dynamic)

Add to `src/bootstrap.php` (after nonce generation):

```php
// Content Security Policy Headers (uncomment to enable)
if (($_ENV['CSP_ENABLED'] ?? 'false') === 'true') {
    $nonce = CSP_NONCE;
    
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://unpkg.com https://code.jquery.com https://cdn.datatables.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https: http:",
        "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "connect-src 'self' https://www.googleapis.com https://oauth2.googleapis.com",
        "frame-src 'self' https://accounts.google.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "upgrade-insecure-requests"
    ];
    
    header("Content-Security-Policy: " . implode('; ', $csp));
}
```

Then add to `.env`:
```bash
CSP_ENABLED=true
```

---

### Option 2: Apache .htaccess (Static Config)

Add to `public/.htaccess`:

```apache
# Content Security Policy (CSP)
# Note: This won't include dynamic nonce - use PHP method instead
<IfModule mod_headers.c>
    # Report-Only mode first (for testing)
    Header set Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; report-uri /csp-report"
    
    # After testing, switch to enforcing:
    # Header set Content-Security-Policy "..."
</IfModule>
```

**⚠️ Warning:** Apache config can't use dynamic nonces. Use PHP method for nonce-based CSP.

---

## 📋 Deployment Checklist

### Phase 1: Testing (Report-Only Mode)
```bash
# 1. Enable CSP in report-only mode
CSP_ENABLED=true
CSP_REPORT_ONLY=true  # Add this option

# 2. Monitor browser console for violations
# 3. Check /logs/csp-violations.log (if logging enabled)
# 4. Fix any legitimate resources being blocked
```

### Phase 2: Enforcement
```bash
# 1. After 1-2 weeks of report-only testing
# 2. Remove CSP_REPORT_ONLY or set to false
CSP_ENABLED=true
CSP_REPORT_ONLY=false

# 3. Deploy to production
# 4. Monitor for user reports of broken functionality
```

---

## 🧪 Testing CSP

### 1. Check Nonce Generation
```bash
php -r "
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();
session_start();
require_once 'src/bootstrap.php';
echo 'CSP Nonce: ' . CSP_NONCE . PHP_EOL;
echo 'Length: ' . strlen(CSP_NONCE) . PHP_EOL;
"
```

Expected output:
```
CSP Nonce: [base64 string ~24 chars]
Length: 24
```

### 2. Check Inline Scripts Have Nonce
```bash
# Search for inline scripts without nonce
grep -r '<script>' public/ --include="*.php" | grep -v 'nonce='

# Should return no results (or only external scripts)
```

### 3. Browser DevTools Test
1. Enable CSP headers
2. Open Chrome DevTools → Console
3. Look for CSP violations (red text)
4. Fix any legitimate resources being blocked

### 4. Online CSP Evaluator
Visit: https://csp-evaluator.withgoogle.com/
Paste your CSP policy for security analysis

---

## 🔍 Common Issues & Solutions

### Issue 1: "Refused to execute inline script"
**Cause:** Script tag missing nonce  
**Fix:** Run `python3 tests/apply-csp-nonce.py` again

### Issue 2: "Refused to load script from CDN"
**Cause:** CDN domain not in script-src  
**Fix:** Add domain to CSP policy:
```php
"script-src 'self' 'nonce-{$nonce}' https://new-cdn-domain.com"
```

### Issue 3: Google OAuth breaks
**Cause:** Missing frame-src or connect-src for Google  
**Fix:** Ensure these directives are present:
```php
"connect-src 'self' https://www.googleapis.com https://oauth2.googleapis.com",
"frame-src 'self' https://accounts.google.com"
```

### Issue 4: Inline styles break
**Cause:** style-src too restrictive  
**Fix:** Allow unsafe-inline for styles (lower risk than scripts):
```php
"style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net"
```

### Issue 5: Data URIs blocked for images
**Cause:** img-src missing data:  
**Fix:**
```php
"img-src 'self' data: https: http:"
```

---

## 📊 CSP Policy Breakdown

| Directive | Value | Purpose |
|-----------|-------|---------|
| `default-src` | `'self'` | Default fallback for all directives |
| `script-src` | `'self' 'nonce-{nonce}' [CDNs]` | JavaScript sources |
| `style-src` | `'self' 'unsafe-inline' [CDNs]` | CSS sources |
| `img-src` | `'self' data: https: http:` | Image sources |
| `font-src` | `'self' [CDN fonts]` | Web font sources |
| `connect-src` | `'self' [APIs]` | AJAX/WebSocket sources |
| `frame-src` | `'self' [OAuth]` | iframe sources |
| `object-src` | `'none'` | Flash/plugins (disabled) |
| `base-uri` | `'self'` | Base tag restrictions |
| `form-action` | `'self'` | Form submission targets |
| `frame-ancestors` | `'none'` | Prevents clickjacking |
| `upgrade-insecure-requests` | (enabled) | Forces HTTPS |

---

## 🎯 Security Benefits

### With CSP Enabled
- ✅ Blocks inline script injection (XSS)
- ✅ Blocks unauthorized external scripts
- ✅ Prevents clickjacking (frame-ancestors)
- ✅ Enforces HTTPS (upgrade-insecure-requests)
- ✅ Disables dangerous plugins (object-src)
- ✅ Restricts form targets (form-action)

### Nonce Benefits Over 'unsafe-inline'
- ✅ Allows legitimate inline scripts (with nonce)
- ✅ Blocks injected inline scripts (no nonce)
- ✅ Better security than blanket 'unsafe-inline'
- ✅ No need to externalize all scripts

---

## 📚 CDN Whitelist

Current whitelisted CDNs (adjust as needed):

```
https://cdn.jsdelivr.net          # Bootstrap, libraries
https://unpkg.com                 # NPM packages
https://code.jquery.com           # jQuery
https://cdn.datatables.net        # DataTables
https://cdnjs.cloudflare.com      # Cloudflare CDN
https://www.googleapis.com        # Google APIs
https://oauth2.googleapis.com     # Google OAuth
https://accounts.google.com       # Google Login iframe
```

---

## 🔄 Rollback Plan

If CSP causes issues in production:

### Quick Disable
```bash
# .env
CSP_ENABLED=false
```

### Revert to Report-Only
```bash
# .env
CSP_ENABLED=true
CSP_REPORT_ONLY=true
```

### Remove Headers Entirely
Comment out CSP header code in `src/bootstrap.php`

---

## 📝 Migration Notes

### From Phase 1 (No CSP) → Phase 2 (Nonce Ready)
- ✅ All inline scripts have nonces
- ✅ CSP_NONCE constant available globally
- ✅ Meta tag provides nonce to JavaScript
- ⏳ Headers not enforced yet (staged for deployment)

### Phase 2 → Phase 3 (Enforced)
1. Enable CSP_REPORT_ONLY first (2 weeks testing)
2. Monitor for violations
3. Whitelist any missing legitimate domains
4. Enable enforcement (CSP_ENABLED=true, CSP_REPORT_ONLY=false)

---

## 🎓 Best Practices

1. **Start with Report-Only** - Never enable enforcement without testing
2. **Monitor Console** - Check browser console for CSP violations
3. **Whitelist Carefully** - Only add domains you control or trust
4. **Keep Nonces Fresh** - Session-based nonces are regenerated automatically
5. **Document Changes** - Track CSP policy changes in version control
6. **Test OAuth** - Google login is most likely to break
7. **Test Admin Panel** - Most complex page with many libraries

---

## 🚀 Production Deployment

### Step 1: Enable Report-Only (Week 1-2)
```bash
# .env
CSP_ENABLED=true
CSP_REPORT_ONLY=true
```

### Step 2: Monitor & Fix (Week 2-3)
- Check browser console daily
- Fix any legitimate resources being blocked
- Update CSP policy as needed

### Step 3: Enable Enforcement (Week 3)
```bash
# .env
CSP_ENABLED=true
CSP_REPORT_ONLY=false
```

### Step 4: Monitor Production (Week 3-4)
- Watch for user reports
- Check error logs
- Be ready to rollback if needed

---

**Status:** ✅ Ready for Report-Only Testing  
**Next Step:** Enable `CSP_ENABLED=true` and `CSP_REPORT_ONLY=true` in .env  
**Timeline:** 2-3 weeks testing before enforcement

**Updated:** November 19, 2025  
**Version:** Phase 2 Complete
