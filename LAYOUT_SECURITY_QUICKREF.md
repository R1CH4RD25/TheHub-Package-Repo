# Layout Security Quick Reference

## 🛡️ Security Helpers Usage

### Avatar URLs (Block XSS)
```php
// ❌ OLD - Vulnerable
<img src="<?php echo e($user['picture']); ?>">

// ✅ NEW - Protected
<img src="<?php echo \Hub\Helpers::safeAvatarUrl($user['picture'] ?? null); ?>">
```

**Blocks:** `javascript:`, `vbscript:`, unsafe `data:` URIs  
**Allows:** `https://`, `http://`, `/relative`, `data:image/`

---

### Icon Classes (Block Injection)
```php
// ❌ OLD - Vulnerable
<i class="<?php echo e($iconClass); ?>"></i>

// ✅ NEW - Protected
<i class="<?php echo \Hub\Helpers::safeIconClass($iconClass); ?>"></i>
```

**Validates:** `bi-*`, `fa-*`, `fas-*`, `far-*`, `fal-*`, `fab-*`  
**Blocks:** Special chars, spaces, injection attempts

---

### Mobile Menu Body Lock
```css
/* CSS (header.css) */
body.nav-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
}
```

```javascript
// ❌ OLD - Can break
document.body.style.overflow = 'hidden';

// ✅ NEW - Error-safe
document.body.classList.add('nav-open');
```

---

### Event Handlers
```php
<!-- ❌ OLD - Inline onclick -->
<button onclick="toggleMenu()">Menu</button>

<!-- ✅ NEW - ID for addEventListener -->
<button id="menuToggle">Menu</button>
```

```javascript
// JS
document.getElementById('menuToggle').addEventListener('click', function() {
    // Safe event handling
});
```

---

### ARIA Attributes
```php
<!-- Accessible dropdown -->
<button id="dropdownTrigger"
        aria-expanded="false"
        aria-haspopup="true"
        aria-controls="dropdownMenu">
    Menu
</button>

<div id="dropdownMenu" 
     role="menu"
     aria-labelledby="dropdownTrigger">
    <a href="#" role="menuitem">Item</a>
</div>
```

```javascript
// Sync ARIA state
trigger.addEventListener('click', function() {
    const isExpanded = menu.classList.toggle('show');
    trigger.setAttribute('aria-expanded', isExpanded);
});
```

---

## 🧪 Testing Commands

```bash
# XSS Prevention
php tests/security-helpers-test.php

# Mobile Menu Interactive Test
php -S localhost:8001 tests/
# Visit: http://localhost:8001/mobile-menu-test.html

# Syntax Check
php -l src/Layout.php
php -l src/Helpers.php
```

---

## 📋 Checklist for New Code

- [ ] User-controlled URLs? → Use `Helpers::safeAvatarUrl()`
- [ ] Database icon class? → Use `Helpers::safeIconClass()`
- [ ] Body scroll lock? → Use CSS class `body.nav-open`
- [ ] Click handlers? → Use `addEventListener`, not `onclick`
- [ ] Interactive element? → Add ARIA attributes

---

## 🚨 Common Mistakes to Avoid

### ❌ Don't
```php
// Direct echo of user data
<img src="<?php echo $user['picture']; ?>">

// Inline onclick
<button onclick="doSomething()">

// Inline style manipulation
body.style.overflow = 'hidden';
```

### ✅ Do
```php
// Validated helper
<img src="<?php echo \Hub\Helpers::safeAvatarUrl($user['picture']); ?>">

// Event delegation
<button id="myButton">
document.getElementById('myButton').addEventListener('click', fn);

// CSS class toggle
body.classList.add('nav-open');
```

---

## 📚 Files Reference

| File | Purpose |
|------|---------|
| `src/Helpers.php` | Security validation functions |
| `src/Layout.php` | Header/footer with security fixes |
| `public/assets/css/header.css` | Contains `.nav-open` class |
| `tests/security-helpers-test.php` | Validation test suite |
| `tests/mobile-menu-test.html` | Interactive mobile test |

---

## 🔄 Migration Pattern

When updating existing code:

1. **Search** for vulnerable pattern: `grep -r "user\['picture'\]" .`
2. **Replace** with safe helper: `Helpers::safeAvatarUrl()`
3. **Test** with injection attempts
4. **Verify** syntax: `php -l file.php`
5. **Commit** with descriptive message

---

**Updated:** November 19, 2025  
**Version:** Phase 1 Complete  
**Status:** Production Ready ✅
