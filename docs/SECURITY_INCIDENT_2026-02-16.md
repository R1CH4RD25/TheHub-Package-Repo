# Security Incident Report: .env Probe Attack

**Incident ID:** SEC-2026-0216
**Date of Attack:** February 16, 2026, 23:14–23:15 UTC
**Date of Report:** February 17, 2026
**Target:** golfstax.com (GolfStax)
**Attacker IP:** `185.177.72.60`
**Origin:** AS211590 Bucklog SARL — Paris, France
**Classification:** Automated bulk scanner — not a targeted attack

---

## Executive Summary

On February 16, 2026 at 23:14 UTC, an automated scanner from IP `185.177.72.60` (Paris, France) systematically probed golfstax.com for exposed `.env` files. The scanner sent 38 total requests over approximately 90 seconds, probing 13 different `.env` paths.

**One file was exposed:** `/backend/.env` (459 bytes) containing Flask secrets and a Google OAuth client secret. All other `.env` paths returned the SPA's `index.html` (false positives). TheHub (hub.woodsonisd.net) was **not targeted** by this scanner and already had dotfile protection in place.

Remediation was completed within ~3 hours of the attack.

---

## Attack Timeline

| Time (UTC) | Request | HTTP Code | Response Size | Verdict |
|---|---|---|---|---|
| 23:14:07 | `GET /` | 200 | 24,720 | Homepage (normal recon) |
| 23:14:31 | `GET /index.html` | 200 | 24,722 | Homepage (normal recon) |
| 23:14:39 | `GET /checkout` | 200 | 22,136 | SPA fallback |
| 23:14:51 | `GET /robots.txt` | 200 | 24,722 | SPA fallback (no robots.txt exists) |
| **23:14:58** | **`GET /.env`** | **200** | **24,720** | **SPA fallback (index.html served, NOT real .env)** |
| 23:15:02 | `GET /api/.env` | 404 | 563 | Blocked — Flask reverse proxy returned 404 |
| 23:15:03 | `GET /.env.vite` | 200 | 22,136 | SPA fallback |
| **23:15:16** | **`GET /backend/.env`** | **200** | **1,241** | **REAL FILE SERVED — DATA BREACH** |
| 23:15:16 | `GET /laravel/.env` | 200 | 22,136 | SPA fallback |
| 23:15:18 | `GET /payment/.env` | 200 | 22,136 | SPA fallback |
| 23:15:18 | `GET /admin/.env` | 200 | 22,136 | SPA fallback |
| 23:15:19 | `GET /.env.example` | 200 | 22,136 | SPA fallback |
| 23:15:19 | `GET /core/.env` | 200 | 22,136 | SPA fallback |
| 23:15:20 | `GET /env` | 200 | 22,136 | SPA fallback |
| 23:15:32 | `GET /stripe/.env` | 200 | 22,471 | SPA fallback |
| 23:15:39 | `GET /stripe/.env` | 200 | 22,471 | SPA fallback (retry) |
| 23:15:49 | `GET /.env.production` | 200 | 22,136 | SPA fallback |

**Additional requests:** 21 requests for `app.js` and `app.js.map` — the SPA's JavaScript loaded automatically when the scanner received HTML responses, inflating the request count.

**Scanner User-Agent:** `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36`

---

## False Positive Analysis

Most routes returned HTTP 200 with ~22KB response bodies. This was **not** actual `.env` content — it was the SPA catch-all rewrite rule serving `index.html` (148,530 bytes on disk, ~22KB compressed via mod_deflate) for any path that didn't match a real file.

**How to distinguish real exposure from SPA fallback:**

| Path | Response Size | Real File? | Exposed? |
|---|---|---|---|
| `/.env` | 24,720 | No file at `/var/www/woodson/golf/.env` | No — SPA fallback |
| `/backend/.env` | 1,241 | Yes — 459 bytes on disk | **YES — real content** |
| `/laravel/.env` | 22,136 | No `/laravel/` directory | No — SPA fallback |
| `/stripe/.env` | 22,471 | No `/stripe/` directory | No — SPA fallback |

The 1,241-byte response for `/backend/.env` is the only anomaly — it's too small to be `index.html` and matches the actual file content (459 bytes + HTTP headers).

---

## Exposed Credentials

The `/backend/.env` file contained the following secrets at the time of exposure:

| Variable | Type | Risk Level |
|---|---|---|
| `SECRET_KEY` | Flask application secret key | **HIGH** — allows session forgery |
| `JWT_SECRET_KEY` | JWT signing key | **HIGH** — allows token forgery |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | **MEDIUM** — requires client ID to exploit |

**Note:** No database passwords, Stripe keys, or other payment-related secrets were in this file.

---

## Impact Assessment by Site

| Site | Domain | .env Location | In DocumentRoot? | Protection | Attacked? | Status |
|---|---|---|---|---|---|---|
| **GolfStax** | golfstax.com | `/var/www/woodson/golf/backend/.env` | Yes (subdirectory of DocRoot) | None at time of attack | **YES** | **BREACHED — 1 file** |
| **TheHub** | hub.woodsonisd.net | `/var/www/woodson/thehub/.env` | **No** (outside `/public/` DocRoot) | `<FilesMatch "^\.">` in .htaccess | Not targeted | **SAFE** |
| **Staff Portal** | staff.woodsonisd.net | `/var/www/woodson/staff/student_data/.env` | Behind Node.js proxy | ProxyPass-only access | Not targeted | **SAFE** |

### Why TheHub Was Safe

1. The `.env` file is stored at `/var/www/woodson/thehub/.env`, which is **one level above** the DocumentRoot (`/var/www/woodson/thehub/public/`). Apache cannot serve files outside DocumentRoot.
2. The `.htaccess` in `public/` has an explicit `<FilesMatch "^\.">` rule that blocks all dotfiles.
3. The attacker never sent any requests to hub.woodsonisd.net (zero log entries from this IP).

### Why GolfStax Was Vulnerable

1. GolfStax is a static SPA — the DocumentRoot is `/var/www/woodson/golf/`, and `backend/` is a subdirectory containing the Flask API source.
2. The `backend/` directory had **no `.htaccess`** blocking direct access at the time of the attack.
3. The root `.htaccess` only had SPA rewrite rules — no dotfile protection.
4. Apache served `backend/.env` as a static file because `AllowOverride All` was set and no deny rule existed.

---

## Root Cause

The GolfStax Apache configuration at `/etc/apache2/sites-available/golfstax.com-le-ssl.conf` sets:

```apache
<Directory /var/www/woodson/golf>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

This grants access to **all files** under the DocumentRoot, relying entirely on `.htaccess` rules for access control. The `.htaccess` had SPA routing but **no security directives** to block dotfiles or sensitive directories.

---

## Remediation Actions

| # | Action | Status | Timestamp | Details |
|---|---|---|---|---|
| 1 | Block `backend/` directory access | **DONE** | Feb 17, 01:57 UTC | Added `backend/.htaccess` with `Require all denied` |
| 2 | Rotate exposed credentials | **DONE** | Feb 17, 01:58 UTC | `backend/.env` regenerated (1,241→459 bytes) |
| 3 | Add root-level dotfile blocking | **DONE** | Feb 17, ~05:00 UTC | Added `<FilesMatch "^\.">` to `/var/www/woodson/golf/.htaccess` |
| 4 | Verify protections | **DONE** | Feb 17, ~05:00 UTC | `curl` confirms 403 for `/.env` and `/backend/.env` |

### Post-Remediation Verification

```
$ curl -s -o /dev/null -w "%{http_code}" https://golfstax.com/.env
403

$ curl -s -o /dev/null -w "%{http_code}" https://golfstax.com/backend/.env
403

$ curl -s -o /dev/null -w "%{http_code}" https://hub.woodsonisd.net/.env
403
```

---

## Remaining Recommendations

### Critical (Do Immediately)

1. **Verify credential rotation** — Confirm `SECRET_KEY`, `JWT_SECRET_KEY`, and `GOOGLE_CLIENT_SECRET` were actually changed to new values (not just the file reformatted). Invalidate any active JWT tokens by restarting the Flask backend.
2. **Revoke Google OAuth client secret** — Go to Google Cloud Console → APIs & Credentials → OAuth 2.0 Client IDs → generate a new client secret and update `backend/.env`.
3. **Review Flask sessions** — Any sessions signed with the old `SECRET_KEY` should be considered compromised. Force re-authentication for all GolfStax users.

### Important (This Week)

4. **Apache global hardening** — `/etc/apache2/conf-enabled/security.conf` has several commented-out protections:
   - `#RedirectMatch 404 /\.git` — Uncomment to block `.git` directory access server-wide
   - `ServerTokens OS` → Change to `ServerTokens Prod` (hides OS version)
   - `ServerSignature On` → Change to `ServerSignature Off` (hides Apache version in error pages)
5. **Block attacker IP** — Add `185.177.72.60` to iptables/ufw deny list or fail2ban
6. **Audit all sites** — Run `find /var/www -name ".env" -type f` and ensure every `.env` file is either outside DocumentRoot or blocked by a deny rule

### Long-Term

7. **Set up fail2ban** — Configure a jail that detects `.env` probing patterns and auto-bans IPs
8. **Move sensitive files** — GolfStax's `backend/.env` should ideally be outside the DocumentRoot entirely, similar to TheHub's architecture
9. **Web Application Firewall** — Consider ModSecurity with OWASP CRS rules to block common scanner patterns
10. **Scheduled security scans** — Run a monthly `nikto` or `nuclei` scan against all domains to catch misconfigurations proactively

---

## Attacker Intelligence

| Field | Value |
|---|---|
| IP Address | `185.177.72.60` |
| ASN | AS211590 |
| Organization | Bucklog SARL |
| Location | Paris, Île-de-France, France |
| Type | Automated bulk scanner |
| Behavior | Systematic `.env` enumeration (13 paths in ~90 seconds) |
| User-Agent | Chrome 120 spoof (Windows 10) |
| Other sites hit | Only golfstax.com on this server |

---

## Appendix: GolfStax .htaccess (Post-Fix)

```apache
RewriteEngine On

# ==============================================
# SECURITY: Block all dotfiles (.env, .git, etc.)
# ==============================================
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Clean URL for scorecard
RewriteRule ^scorecard$ /scorecard.html [L]

# ... (remaining SPA routes)

# SPA catch-all
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ /index.html [L]
```

## Appendix: GolfStax backend/.htaccess (Post-Fix)

```apache
# Block ALL direct web access to the backend directory
# The Flask API is accessed through Apache's reverse proxy (ProxyPass /api)
# No files in this directory should be served directly

# Deny all access
Require all denied

# Alternative syntax for older Apache versions
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```
