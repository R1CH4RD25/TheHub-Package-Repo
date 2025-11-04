# PWA Implementation Quick Start

## When You're Ready to Begin

This guide provides the fastest path to get PWA features live. Full details in [PWA_ROADMAP.md](PWA_ROADMAP.md).

## Phase 1: Make The Hub Installable (2-4 hours)

### Step 1: Create Manifest (15 min)

```bash
# Create manifest file
cat > public/manifest.json << 'EOF'
{
  "name": "The Hub - Woodson ISD",
  "short_name": "The Hub",
  "description": "Unified portal for Woodson ISD operations",
  "start_url": "/",
  "display": "standalone",
  "orientation": "portrait-primary",
  "theme_color": "#1e40af",
  "background_color": "#ffffff",
  "icons": [
    {
      "src": "/assets/icons/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/assets/icons/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "maskable"
    }
  ]
}
EOF
```

### Step 2: Generate Icons (30 min)

**Option A: Use Online Tool**
1. Go to https://realfavicongenerator.net/ or https://www.pwabuilder.com/imageGenerator
2. Upload The Hub logo (high res, square)
3. Download generated icons
4. Extract to `public/assets/icons/`

**Option B: Use ImageMagick**
```bash
# Install ImageMagick if needed
sudo apt-get install imagemagick

# Convert logo to all sizes
cd public/assets/icons/
for size in 72 96 128 144 152 192 384 512; do
  convert logo.png -resize ${size}x${size} icon-${size}x${size}.png
done

# Create maskable icons (with padding for safe area)
for size in 192 512; do
  convert logo.png -resize $((size-80))x$((size-80)) \
    -background white -gravity center \
    -extent ${size}x${size} \
    icon-${size}x${size}-maskable.png
done
```

### Step 3: Add Manifest to All Pages (10 min)

```php
// In src/bootstrap.php or header template, add after <head>:
?>
<!-- PWA Manifest -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1e40af">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="The Hub">
<link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
<?php
```

### Step 4: Create Basic Service Worker (30 min)

```javascript
// public/sw.js
const CACHE_VERSION = 'thehub-v1.0.0';
const CACHE_NAME = `${CACHE_VERSION}-static`;

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/dashboard.php',
  '/modules.php',
  '/assets/css/main.css',
  '/assets/js/main.js',
  '/assets/icons/icon-192x192.png'
];

// Install - cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate - clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => {
        return Promise.all(
          keys
            .filter(key => key.startsWith('thehub-') && key !== CACHE_VERSION)
            .map(key => caches.delete(key))
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Clone response and cache it
        const responseClone = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseClone);
        });
        return response;
      })
      .catch(() => {
        // Network failed, try cache
        return caches.match(event.request);
      })
  );
});
```

### Step 5: Register Service Worker (15 min)

```javascript
// public/assets/js/sw-register.js
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('✅ Service Worker registered');
        
        // Check for updates every minute
        setInterval(() => registration.update(), 60000);
      })
      .catch(err => console.error('❌ SW registration failed:', err));
  });
}
```

```php
// Add to footer template or bootstrap.php:
?>
<script src="/assets/js/sw-register.js"></script>
<?php
```

### Step 6: Test Installation (30 min)

**Android Chrome:**
1. Open site in Chrome
2. Look for "Install" banner at bottom
3. Tap "Install" → app appears on home screen
4. Open app → should launch in standalone mode

**iOS Safari:**
1. Open site in Safari
2. Tap Share button
3. Scroll down → "Add to Home Screen"
4. Tap → app appears on home screen
5. Open app → should launch without Safari UI

**Desktop Chrome:**
1. Open site in Chrome
2. Look for install icon in address bar
3. Click → "Install The Hub"
4. App opens in standalone window

**Verify:**
- App icon on home screen/taskbar
- No browser UI when launched
- Theme color applied
- Splash screen shows (Android)

## Phase 2: Add Offline Support (4-6 hours)

### Step 1: Create Offline Page (15 min)

```html
<!-- public/offline.html -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - The Hub</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        h1 { font-size: 2rem; margin-bottom: 1rem; }
        button {
            margin-top: 2rem;
            padding: 0.75rem 2rem;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div>
        <h1>🔌 You're Offline</h1>
        <p>The Hub needs an internet connection to load this page.</p>
        <button onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
```

### Step 2: Update Service Worker (30 min)

```javascript
// Add to sw.js install event
const STATIC_ASSETS = [
  // ... existing assets
  '/offline.html'
];

// Update fetch handler for better offline support
self.addEventListener('fetch', event => {
  const { request } = event;
  
  // Skip non-GET requests
  if (request.method !== 'GET') return;
  
  // For HTML pages
  if (request.headers.get('Accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
          return response;
        })
        .catch(() => {
          return caches.match(request)
            .then(cached => cached || caches.match('/offline.html'));
        })
    );
  }
  // For other assets (CSS, JS, images)
  else {
    event.respondWith(
      caches.match(request)
        .then(cached => cached || fetch(request)
          .then(response => {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
            return response;
          })
        )
    );
  }
});
```

### Step 3: Add Offline Indicator (1 hour)

```javascript
// public/assets/js/offline-indicator.js
(function() {
  const style = document.createElement('style');
  style.textContent = `
    .offline-banner {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: #f59e0b;
      color: white;
      padding: 0.75rem;
      text-align: center;
      font-size: 0.875rem;
      font-weight: 600;
      z-index: 9999;
      transform: translateY(-100%);
      transition: transform 0.3s ease;
    }
    .offline-banner.show {
      transform: translateY(0);
    }
  `;
  document.head.appendChild(style);
  
  const banner = document.createElement('div');
  banner.className = 'offline-banner';
  banner.textContent = '⚠️ You\'re offline. Changes will sync when reconnected.';
  document.body.prepend(banner);
  
  function updateStatus() {
    if (navigator.onLine) {
      banner.classList.remove('show');
    } else {
      banner.classList.add('show');
    }
  }
  
  window.addEventListener('online', updateStatus);
  window.addEventListener('offline', updateStatus);
  updateStatus();
})();
```

```php
// Add to footer:
?>
<script src="/assets/js/offline-indicator.js"></script>
<?php
```

### Step 4: Test Offline (30 min)

1. Open DevTools → Network → Throttling → "Offline"
2. Navigate site → should see cached pages
3. Try uncached page → should see offline.html
4. Go back online → banner disappears
5. Test on real device with airplane mode

## Phase 3: Background Sync (Optional, 6-8 hours)

See full roadmap for IndexedDB setup and background sync implementation.

## Phase 4: Push Notifications (Optional, 6-8 hours)

See full roadmap for VAPID keys, subscription management, and push handler.

## Quick Deployment Checklist

- [ ] Manifest created and linked
- [ ] Icons generated (192x192, 512x512 minimum)
- [ ] Service worker created and registered
- [ ] Offline page created
- [ ] Offline indicator added
- [ ] Tested on iOS Safari
- [ ] Tested on Android Chrome
- [ ] Tested on desktop Chrome
- [ ] Tested airplane mode
- [ ] Lighthouse PWA audit passes (score 100)

## Lighthouse PWA Audit

```bash
# Install Lighthouse CLI
npm install -g lighthouse

# Run PWA audit
lighthouse https://hub.woodsonisd.net \
  --only-categories=pwa \
  --output=html \
  --output-path=./pwa-audit.html

# Open report
open pwa-audit.html
```

**Target Score: 100/100**

## Common Issues

### "Service worker not found"
- Check `/sw.js` is accessible (not blocked by .htaccess)
- Verify MIME type is `application/javascript`
- Check HTTPS is enabled

### "Failed to register service worker"
- Check console for specific error
- Verify scope is correct (`/`)
- Check for syntax errors in sw.js

### "Install prompt not showing"
- Ensure HTTPS is enabled
- Verify manifest is valid (use DevTools → Application → Manifest)
- Check all required icons are present
- Try after second visit (Chrome requirement)
- Clear cache and try again

### "iOS not installing"
- Ensure apple-touch-icon is present
- Check manifest is linked
- Verify HTTPS is enabled
- Try manual "Add to Home Screen" from Share menu

## Performance Tips

### Minimize Service Worker Scope
Only cache what you need. Start small, expand as needed.

### Use Cache Expiration
Don't cache forever. Implement TTL for dynamic content.

### Prefetch Critical Resources
Add frequently used pages to STATIC_ASSETS.

### Monitor Cache Size
Typical quota: 50-100MB. Stay under 10MB to start.

## Next Steps After Phase 1

Once basic PWA is working:

1. **Measure** - Add analytics for install rate, offline usage
2. **Monitor** - Track service worker errors, cache hit rates
3. **Optimize** - Profile cache strategies, reduce bundle size
4. **Expand** - Add background sync (Phase 2), push (Phase 3)

## Resources

- [MDN PWA Guide](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Web.dev PWA](https://web.dev/progressive-web-apps/)
- [PWA Builder](https://www.pwabuilder.com/)
- [Workbox (Google's PWA toolkit)](https://developers.google.com/web/tools/workbox)
- [Can I Use: Service Workers](https://caniuse.com/serviceworkers)

---

**Estimated Time: 2-4 hours for basic installable PWA**  
**Next Level: 6-8 hours for offline + background sync**  
**Full Featured: 16-20 hours for all phases**

Ready to start? Begin with Phase 1 and test on real devices early and often! 📱✨
