# PWA with Advanced Service Workers - Roadmap

## Executive Summary

**Priority:** HIGH - End users are mobile-first
**Estimated Effort:** 3-4 weeks (phased rollout)
**Dependencies:** Current vanilla JS architecture (no framework lock-in)
**Target:** Offline-capable, app-like experience for mobile users

## Business Case

### Current State
- Mobile users navigate via browser (no install prompt)
- No offline capability (requires constant connectivity)
- No push notifications
- Browser UI chrome reduces screen real estate
- Network-dependent performance

### Target State
- Install prompt on mobile/desktop (add to home screen)
- Offline-first architecture with smart caching
- Background sync for form submissions
- Push notifications for approvals/alerts
- Reduced data usage (cache-first strategies)
- Near-instant load times (service worker cache)

### Value Proposition
**For School Districts:**
- Field staff can work offline (bus yards, remote locations)
- Reduced cellular data costs
- Better user experience = higher adoption
- Modern "app-like" feel without app store deployment

**For End Users:**
- Works like native app (home screen icon, splash screen)
- No app store friction
- Automatic updates (service worker versioning)
- Faster load times (cache-first)
- Offline form entry with background sync

## Technical Architecture

### Phase 1: PWA Foundation (Week 1)
**Goal:** Make The Hub installable as PWA

#### 1.1 Web App Manifest
**File:** `public/manifest.json`

```json
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
      "src": "/assets/icons/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-96x96.png",
      "sizes": "96x96",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-128x128.png",
      "sizes": "128x128",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-144x144.png",
      "sizes": "144x144",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-152x152.png",
      "sizes": "152x152",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-384x384.png",
      "sizes": "384x384",
      "type": "image/png",
      "purpose": "maskable any"
    },
    {
      "src": "/assets/icons/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "maskable any"
    }
  ],
  "categories": ["education", "productivity", "business"],
  "lang": "en-US",
  "dir": "ltr",
  "scope": "/",
  "shortcuts": [
    {
      "name": "Dashboard",
      "short_name": "Home",
      "description": "Go to main dashboard",
      "url": "/dashboard.php",
      "icons": [{ "src": "/assets/icons/home.png", "sizes": "96x96" }]
    },
    {
      "name": "Modules",
      "short_name": "Modules",
      "description": "Access available modules",
      "url": "/modules.php",
      "icons": [{ "src": "/assets/icons/modules.png", "sizes": "96x96" }]
    }
  ]
}
```

**Checklist:**
- [ ] Create icon assets (72x72 → 512x512)
- [ ] Generate maskable icons (safe area padding)
- [ ] Add manifest link to all HTML pages
- [ ] Configure theme colors to match brand
- [ ] Define app shortcuts for key pages

#### 1.2 Service Worker Registration
**File:** `public/assets/js/sw-register.js`

```javascript
// Service Worker registration with update handling
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(registration => {
        console.log('✅ Service Worker registered:', registration.scope);
        
        // Check for updates every 60 seconds
        setInterval(() => {
          registration.update();
        }, 60000);
        
        // Handle updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              // New version available
              showUpdateNotification();
            }
          });
        });
      })
      .catch(err => {
        console.error('❌ Service Worker registration failed:', err);
      });
    
    // Handle controller change (new SW activated)
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      window.location.reload();
    });
  });
}

function showUpdateNotification() {
  if (confirm('A new version of The Hub is available. Reload to update?')) {
    navigator.serviceWorker.getRegistration().then(reg => {
      reg.waiting.postMessage({ type: 'SKIP_WAITING' });
    });
  }
}
```

**Checklist:**
- [ ] Add registration script to bootstrap
- [ ] Implement update notification UI
- [ ] Test update flow (version bumps)
- [ ] Handle offline/online events

#### 1.3 Basic Service Worker
**File:** `public/sw.js`

```javascript
const CACHE_VERSION = 'thehub-v1.0.0';
const CACHE_STATIC = `${CACHE_VERSION}-static`;
const CACHE_DYNAMIC = `${CACHE_VERSION}-dynamic`;
const CACHE_API = `${CACHE_VERSION}-api`;

// Assets to cache immediately on install
const STATIC_ASSETS = [
  '/',
  '/dashboard.php',
  '/modules.php',
  '/assets/css/main.css',
  '/assets/js/main.js',
  '/assets/icons/icon-192x192.png',
  '/manifest.json'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('[SW] Installing service worker...');
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean old caches
self.addEventListener('activate', event => {
  console.log('[SW] Activating service worker...');
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys
          .filter(key => key.startsWith('thehub-') && key !== CACHE_VERSION)
          .map(key => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - network first, fallback to cache
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // API requests - network first
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirstStrategy(request, CACHE_API));
  }
  // Static assets - cache first
  else if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirstStrategy(request, CACHE_STATIC));
  }
  // Pages - network first
  else {
    event.respondWith(networkFirstStrategy(request, CACHE_DYNAMIC));
  }
});

async function networkFirstStrategy(request, cacheName) {
  try {
    const response = await fetch(request);
    const cache = await caches.open(cacheName);
    cache.put(request, response.clone());
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    return cached || new Response('Offline - no cached version available', {
      status: 503,
      statusText: 'Service Unavailable'
    });
  }
}

async function cacheFirstStrategy(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  
  try {
    const response = await fetch(request);
    const cache = await caches.open(cacheName);
    cache.put(request, response.clone());
    return response;
  } catch (error) {
    return new Response('Offline', { status: 503 });
  }
}

function isStaticAsset(pathname) {
  return /\.(css|js|png|jpg|jpeg|svg|woff|woff2|ttf)$/.test(pathname);
}

// Handle messages from clients
self.addEventListener('message', event => {
  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
```

**Checklist:**
- [ ] Test cache-first for static assets
- [ ] Test network-first for dynamic content
- [ ] Verify offline fallback
- [ ] Test cache invalidation on version bump

### Phase 2: Advanced Caching Strategies (Week 2)
**Goal:** Smart caching for optimal offline experience

#### 2.1 Route-Specific Caching
```javascript
// In sw.js - sophisticated routing
const CACHE_STRATEGIES = {
  // Critical pages - always fresh, fallback to cache
  '/dashboard.php': 'network-first',
  '/modules.php': 'network-first',
  
  // Static assets - serve from cache, update in background
  '/assets/': 'cache-first',
  
  // API endpoints - network only (with background sync)
  '/api/': 'network-only-with-sync',
  
  // Images - cache with expiration
  '/uploads/': 'cache-with-expiry',
  
  // Fonts - cache forever
  '/assets/fonts/': 'cache-forever'
};
```

#### 2.2 Background Sync for Forms
**Use Cases:**
- Fuel entry forms (drivers in field)
- Maintenance requests (bus yards)
- Inspection checklists (offline)

**Implementation:**
```javascript
// In form submission handlers
if ('serviceWorker' in navigator && 'SyncManager' in window) {
  // Queue for background sync
  navigator.serviceWorker.ready.then(registration => {
    return registration.sync.register('sync-form-submission');
  });
  
  // Store form data in IndexedDB
  await storeFormData(formData);
  showMessage('Form saved. Will submit when online.', 'info');
} else {
  // Fallback to immediate submission
  await submitForm(formData);
}
```

**Service Worker Sync Handler:**
```javascript
self.addEventListener('sync', event => {
  if (event.tag === 'sync-form-submission') {
    event.waitUntil(syncFormSubmissions());
  }
});

async function syncFormSubmissions() {
  const db = await openIndexedDB();
  const pendingForms = await db.getAll('pending-submissions');
  
  for (const form of pendingForms) {
    try {
      await fetch(form.url, {
        method: 'POST',
        body: JSON.stringify(form.data),
        headers: { 'Content-Type': 'application/json' }
      });
      await db.delete('pending-submissions', form.id);
    } catch (error) {
      console.error('Sync failed for form:', form.id, error);
      // Will retry on next sync
    }
  }
}
```

#### 2.3 IndexedDB Abstraction
**File:** `public/assets/js/db.js`

```javascript
class HubDB {
  constructor() {
    this.dbName = 'thehub-db';
    this.version = 1;
  }
  
  async open() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result);
      
      request.onupgradeneeded = event => {
        const db = event.target.result;
        
        // Store for pending form submissions
        if (!db.objectStoreNames.contains('pending-submissions')) {
          const store = db.createObjectStore('pending-submissions', {
            keyPath: 'id',
            autoIncrement: true
          });
          store.createIndex('timestamp', 'timestamp');
          store.createIndex('type', 'type');
        }
        
        // Store for cached API responses
        if (!db.objectStoreNames.contains('api-cache')) {
          const store = db.createObjectStore('api-cache', { keyPath: 'url' });
          store.createIndex('expiry', 'expiry');
        }
        
        // Store for offline data
        if (!db.objectStoreNames.contains('offline-data')) {
          db.createObjectStore('offline-data', { keyPath: 'key' });
        }
      };
    });
  }
  
  async add(storeName, data) {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const request = store.add(data);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  
  async getAll(storeName) {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const request = store.getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  
  async delete(storeName, key) {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const request = store.delete(key);
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }
}

// Export singleton
window.hubDB = new HubDB();
```

### Phase 3: Push Notifications (Week 3)
**Goal:** Re-engage users with timely notifications

#### 3.1 Push Subscription Management
**Use Cases:**
- Approval requests (pending registrations, module access)
- System alerts (maintenance windows, updates)
- Reminders (inspection due dates, fuel entry)

**Frontend:**
```javascript
// Request notification permission
async function requestNotificationPermission() {
  const permission = await Notification.requestPermission();
  if (permission === 'granted') {
    await subscribeToPush();
  }
  return permission;
}

async function subscribeToPush() {
  const registration = await navigator.serviceWorker.ready;
  
  // Get VAPID public key from server
  const response = await fetch('/api/push/vapid-public-key.php');
  const { publicKey } = await response.json();
  
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(publicKey)
  });
  
  // Send subscription to server
  await fetch('/api/push/subscribe.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(subscription)
  });
  
  return subscription;
}
```

**Backend Schema:**
```sql
-- database/push-notifications-schema.sql
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    user_agent TEXT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    icon VARCHAR(255),
    badge VARCHAR(255),
    url VARCHAR(512),
    data JSON,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    error_message TEXT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Backend Implementation:**
```php
// src/PushNotification.php
namespace Hub;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotification {
    private $db;
    private $webPush;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        $vapidKeys = [
            'subject' => $_ENV['VAPID_SUBJECT'],
            'publicKey' => $_ENV['VAPID_PUBLIC_KEY'],
            'privateKey' => $_ENV['VAPID_PRIVATE_KEY']
        ];
        
        $this->webPush = new WebPush($vapidKeys);
    }
    
    public function sendToUser(int $userId, array $notification): bool {
        // Get user's active subscriptions
        $subscriptions = $this->getUserSubscriptions($userId);
        
        if (empty($subscriptions)) {
            return false;
        }
        
        $payload = json_encode([
            'title' => $notification['title'],
            'body' => $notification['body'],
            'icon' => $notification['icon'] ?? '/assets/icons/icon-192x192.png',
            'badge' => $notification['badge'] ?? '/assets/icons/badge-72x72.png',
            'url' => $notification['url'] ?? '/',
            'data' => $notification['data'] ?? []
        ]);
        
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh_key'],
                    'auth' => $sub['auth_key']
                ]
            ]);
            
            $this->webPush->queueNotification($subscription, $payload);
        }
        
        // Send all queued notifications
        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                // Handle expired subscriptions
                if ($report->isSubscriptionExpired()) {
                    $this->deactivateSubscription($report->getEndpoint());
                }
            }
        }
        
        return true;
    }
    
    private function getUserSubscriptions(int $userId): array {
        return $this->db->fetchAll(
            "SELECT * FROM push_subscriptions 
             WHERE user_id = ? AND is_active = 1",
            [$userId]
        );
    }
}
```

#### 3.2 Service Worker Push Handler
```javascript
// In sw.js
self.addEventListener('push', event => {
  const data = event.data.json();
  
  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    data: {
      url: data.url,
      ...data.data
    },
    actions: [
      { action: 'view', title: 'View' },
      { action: 'dismiss', title: 'Dismiss' }
    ],
    vibrate: [200, 100, 200],
    requireInteraction: false
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view' || !event.action) {
    event.waitUntil(
      clients.openWindow(event.notification.data.url)
    );
  }
});
```

### Phase 4: Offline UI/UX (Week 4)
**Goal:** Graceful offline experience

#### 4.1 Offline Indicator
```javascript
// public/assets/js/offline-indicator.js
class OfflineIndicator {
  constructor() {
    this.createIndicator();
    this.attachListeners();
  }
  
  createIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'offline-indicator';
    indicator.className = 'offline-banner hidden';
    indicator.innerHTML = `
      <div class="offline-content">
        <svg class="offline-icon">...</svg>
        <span>You're offline. Changes will sync when reconnected.</span>
      </div>
    `;
    document.body.prepend(indicator);
    this.indicator = indicator;
  }
  
  attachListeners() {
    window.addEventListener('online', () => this.goOnline());
    window.addEventListener('offline', () => this.goOffline());
    
    // Check initial state
    if (!navigator.onLine) {
      this.goOffline();
    }
  }
  
  goOffline() {
    this.indicator.classList.remove('hidden');
    document.body.classList.add('offline-mode');
    this.showPendingSyncCount();
  }
  
  async goOnline() {
    this.indicator.classList.add('hidden');
    document.body.classList.remove('offline-mode');
    
    // Trigger background sync
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.ready;
      await registration.sync.register('sync-all-pending');
    }
  }
  
  async showPendingSyncCount() {
    const pending = await hubDB.getAll('pending-submissions');
    if (pending.length > 0) {
      this.indicator.querySelector('span').textContent = 
        `You're offline. ${pending.length} change(s) will sync when reconnected.`;
    }
  }
}

// Initialize on load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new OfflineIndicator();
  });
} else {
  new OfflineIndicator();
}
```

#### 4.2 Offline Page
**File:** `public/offline.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - The Hub</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .offline-container {
            text-align: center;
            color: white;
            padding: 2rem;
        }
        .offline-icon {
            width: 120px;
            height: 120px;
            margin-bottom: 2rem;
        }
        h1 { font-size: 2rem; margin-bottom: 1rem; }
        p { font-size: 1.1rem; opacity: 0.9; }
        .retry-btn {
            margin-top: 2rem;
            padding: 0.75rem 2rem;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <svg class="offline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M1 1l22 22M8.5 5C5.5 7 3 9.5 1 12m7-7C11 8 14 11 17 14m-9-4c2 2 4 4 6 6M22 12c-2-2.5-5-5-8.5-7.5"/>
        </svg>
        <h1>You're Offline</h1>
        <p>The Hub needs an internet connection to load this page.</p>
        <p>Check your connection and try again.</p>
        <button class="retry-btn" onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
```

## Implementation Checklist

### Phase 1: PWA Foundation ✓
- [ ] Create web app manifest (`manifest.json`)
- [ ] Generate PWA icons (72x72 → 512x512)
- [ ] Create maskable icons with safe area
- [ ] Add manifest link to all pages
- [ ] Create basic service worker (`sw.js`)
- [ ] Implement SW registration script
- [ ] Add install prompt handling
- [ ] Test on iOS Safari (add to home screen)
- [ ] Test on Android Chrome (install banner)
- [ ] Test on desktop (Chrome/Edge PWA)

### Phase 2: Advanced Caching ✓
- [ ] Implement route-specific caching strategies
- [ ] Create IndexedDB abstraction layer
- [ ] Build background sync for forms
- [ ] Add cache versioning/invalidation
- [ ] Implement stale-while-revalidate for API
- [ ] Create offline data storage
- [ ] Test cache limits (50MB typical)
- [ ] Test cache expiration
- [ ] Measure cache hit rates

### Phase 3: Push Notifications ✓
- [ ] Generate VAPID keys
- [ ] Create push subscriptions table
- [ ] Build subscription management API
- [ ] Install `web-push-php` via Composer
- [ ] Implement PushNotification class
- [ ] Add SW push event handler
- [ ] Create notification UI
- [ ] Test on Android (full support)
- [ ] Test on iOS 16.4+ (partial support)
- [ ] Build notification preferences UI

### Phase 4: Offline UX ✓
- [ ] Create offline indicator component
- [ ] Build offline fallback page
- [ ] Add pending sync counter
- [ ] Implement optimistic UI updates
- [ ] Create conflict resolution UI
- [ ] Add "retry failed syncs" button
- [ ] Show last sync timestamp
- [ ] Handle online/offline transitions
- [ ] Test airplane mode scenarios

## Testing Strategy

### Manual Testing
**Devices:**
- iPhone (iOS 16.4+, Safari)
- Android phone (Chrome)
- Windows desktop (Chrome/Edge)
- macOS desktop (Chrome/Safari)

**Scenarios:**
1. Install PWA from browser
2. Navigate while offline
3. Submit form while offline
4. Go online (verify background sync)
5. Receive push notification
6. Update app (new SW version)
7. Clear cache, verify reinstall
8. Test on slow 3G connection

### Automated Testing
**Lighthouse CI:**
```bash
# Add to .github/workflows/pwa-audit.yml
- name: Lighthouse PWA Audit
  uses: treosh/lighthouse-ci-action@v9
  with:
    urls: |
      https://hub.woodsonisd.net
      https://hub.woodsonisd.net/dashboard.php
    uploadArtifacts: true
    temporaryPublicStorage: true
```

**Target Scores:**
- Performance: 90+
- PWA: 100
- Accessibility: 90+
- Best Practices: 90+
- SEO: 90+

## Security Considerations

### Service Worker Security
- ✅ Serve over HTTPS (already done)
- ✅ Validate all cached URLs (same origin)
- ✅ Sanitize push notification content
- ✅ Rate limit push subscriptions
- ✅ Expire inactive subscriptions (90 days)
- ✅ CSRF tokens in background sync

### Push Notification Security
- ✅ Use VAPID for authentication
- ✅ Never expose private key client-side
- ✅ Validate subscription endpoints
- ✅ Rate limit notifications per user
- ✅ Audit notification sends (AuditLogger)

## Performance Metrics

### Target Metrics
- **Time to Interactive:** <3s on 3G
- **First Contentful Paint:** <1.5s
- **Cache Hit Rate:** >80%
- **Offline Functionality:** 100% for cached routes
- **Background Sync Success:** >95%

### Monitoring
```javascript
// Performance API in service worker
self.addEventListener('fetch', event => {
  const start = performance.now();
  event.respondWith(
    handleFetch(event.request).then(response => {
      const duration = performance.now() - start;
      // Log to analytics
      logPerformance({
        url: event.request.url,
        duration,
        cached: response.headers.get('X-Cache-Hit') === 'true'
      });
      return response;
    })
  );
});
```

## Deployment Plan

### Week 1: Foundation
1. Create PWA assets (manifest, icons)
2. Deploy basic service worker
3. Test install flow on all platforms
4. Monitor analytics for install rate

### Week 2: Caching
1. Deploy advanced caching strategies
2. Add IndexedDB for offline data
3. Implement background sync
4. Monitor cache performance

### Week 3: Notifications
1. Generate VAPID keys (production)
2. Deploy push notification system
3. Add subscription UI
4. Test notification delivery

### Week 4: Polish
1. Deploy offline UI/UX
2. Add analytics/monitoring
3. Performance optimization
4. Documentation and training

## Success Metrics

### Adoption Metrics
- **Install Rate:** >30% of mobile users within 3 months
- **Return Visits:** >50% open from home screen
- **Offline Usage:** >10% of sessions have offline interactions
- **Push Opt-In:** >40% of users enable notifications

### Performance Metrics
- **Load Time:** <2s average (vs 4s before)
- **Data Usage:** -60% after initial install
- **Bounce Rate:** -25% on mobile
- **Session Duration:** +35% on mobile

### Business Metrics
- **Mobile Adoption:** 80%+ of field staff use PWA
- **Form Completion:** +45% in offline scenarios
- **User Satisfaction:** 4.5+ stars (feedback survey)
- **Support Tickets:** -30% connectivity-related issues

## Dependencies

### Browser Support
- ✅ Chrome 90+ (Android/Desktop)
- ✅ Edge 90+
- ✅ Safari 16.4+ (iOS/macOS) - limited push support
- ✅ Firefox 90+ (Android/Desktop)
- ❌ IE 11 (unsupported, graceful degradation)

### PHP Dependencies
```bash
composer require minishlink/web-push
```

### Server Requirements
- HTTPS (already configured)
- HTTP/2 preferred (faster asset loading)
- Service worker MIME type: `application/javascript`

## Fallback Strategy

### No Service Worker Support
- App works normally (server-rendered)
- No offline capability
- No push notifications
- No install prompt

### iOS < 16.4
- PWA installs work (add to home screen)
- No push notifications
- Service worker has limitations
- Consider native app wrapper (future)

## Documentation Needs

### Developer Docs
- [ ] Service worker architecture guide
- [ ] Caching strategy documentation
- [ ] Background sync patterns
- [ ] Push notification API reference
- [ ] Testing procedures

### User Docs
- [ ] How to install The Hub on mobile
- [ ] Using The Hub offline
- [ ] Managing notifications
- [ ] Troubleshooting guide

## Future Enhancements (Phase 5+)

### Advanced Features
- **Web Share API:** Share content from The Hub
- **File System Access:** Save/open files directly
- **Bluetooth API:** Connect to diagnostic tools (buses)
- **Geolocation:** Track mileage automatically
- **Camera API:** Photo uploads (maintenance issues)
- **Periodic Background Sync:** Auto-refresh data

### Performance
- **App Shell Architecture:** Instant loads
- **Predictive Prefetching:** Anticipate navigation
- **Network Information API:** Adapt to connection quality
- **Workbox:** Google's PWA library (if complexity grows)

## Risk Mitigation

### Risk: Service Worker Bugs
**Impact:** App breaks for all users
**Mitigation:**
- Thorough testing in staging
- Gradual rollout (10% → 50% → 100%)
- Emergency SW kill switch
- Version rollback procedure

### Risk: Cache Bloat
**Impact:** Storage quota exceeded
**Mitigation:**
- Cache size monitoring
- Automatic cache pruning (LRU)
- User-visible storage usage
- Clear cache button in settings

### Risk: Sync Conflicts
**Impact:** Data loss or duplicates
**Mitigation:**
- Last-write-wins for simple data
- Conflict resolution UI for critical data
- Audit logs for all syncs
- Server-side validation

### Risk: Low iOS Push Support
**Impact:** iOS users miss notifications
**Mitigation:**
- In-app notification center
- Email fallback for critical alerts
- Transparent about iOS limitations
- Monitor iOS Safari push adoption

## Conclusion

PWA with Advanced Service Workers will transform The Hub into a mobile-first, offline-capable platform that meets modern user expectations. The phased approach minimizes risk while delivering incremental value.

**Estimated ROI:**
- **Development:** 3-4 weeks (1 developer)
- **Maintenance:** ~2 hours/month
- **User Impact:** 80%+ of mobile users benefit
- **Cost Savings:** Reduced support load, no app store fees

**Next Steps:**
1. Review roadmap with stakeholders
2. Prioritize Phase 1 (PWA Foundation)
3. Allocate resources (developer time)
4. Set target dates for each phase

---

**Status:** ROADMAP - Awaiting approval
**Priority:** HIGH - Mobile-first users
**Created:** 2025-11-04
**Est. Start:** TBD
**Est. Complete:** TBD + 4 weeks
