# GitHub API Rate Limiting & Scaling Solutions

## Current Situation
- **Unauthenticated requests**: 60 requests/hour per IP address
- **Your usage**: Package discovery makes ~10+ API calls per search (recursive directory listing)
- **Problem**: If this goes viral, you'll hit limits quickly

---

## ✅ Solution 1: GitHub Personal Access Token (RECOMMENDED)
**Rate limit: 5,000 requests/hour**

### Setup:
1. Go to: https://github.com/settings/tokens
2. Click "Generate new token (classic)"
3. Name it: "TheHub Package Discovery"
4. Select scopes: **ONLY `public_repo`** (read-only access to public repos)
5. Generate token and copy it

### Implementation:
Add to `.env`:
```bash
GITHUB_API_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
```

Update `package-discovery.php` to use token:
```php
$headers = [
    'User-Agent: Hub-Package-Manager/1.0',
    'Accept: application/vnd.github.v3+json'
];

// Add authentication if token exists
if (!empty($_ENV['GITHUB_API_TOKEN'])) {
    $headers[] = 'Authorization: Bearer ' . $_ENV['GITHUB_API_TOKEN'];
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => $headers,
        'timeout' => 30
    ]
]);
```

**Pros:**
- ✅ 5,000 requests/hour (83x increase!)
- ✅ Free
- ✅ Easy to implement
- ✅ No code changes needed for users

**Cons:**
- ⚠️ Token must be kept secret
- ⚠️ Single point of failure if token is revoked

---

## ✅ Solution 2: GitHub App (ENTERPRISE SCALE)
**Rate limit: 5,000 requests/hour per installation**

### Setup:
1. Create GitHub App: https://github.com/settings/apps
2. Configure webhook URL (optional)
3. Install app on your package repository
4. Use JWT authentication

**Pros:**
- ✅ 5,000 requests/hour per installation
- ✅ Multiple installations = multiple rate limits
- ✅ More secure (short-lived tokens)
- ✅ Better audit trail

**Cons:**
- ⚠️ More complex to implement
- ⚠️ Requires JWT library
- ⚠️ Overkill for current scale

---

## ✅ Solution 3: Caching Strategy (IMMEDIATE FIX)
**Reduce API calls by 90%+**

### Implementation:
Cache package discovery results for 1 hour:

```php
function searchGitHubPackages($owner, $repo) {
    $cacheKey = "github_packages_{$owner}_{$repo}";
    $cache = new \Hub\Cache();
    
    // Check cache first
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        error_log("Package discovery: Serving from cache");
        return json_decode($cached, true);
    }
    
    // Fetch from GitHub
    $packages = [...]; // existing logic
    
    // Cache for 1 hour
    $cache->set($cacheKey, json_encode($packages), 3600);
    
    return $packages;
}
```

**Pros:**
- ✅ Immediate reduction in API calls
- ✅ Faster response times
- ✅ Works with any rate limit
- ✅ Already have Cache class in codebase

**Cons:**
- ⚠️ Package updates won't show for 1 hour
- ⚠️ Still need auth for high traffic

---

## ✅ Solution 4: Self-Hosted Package Index (ULTIMATE SOLUTION)
**Rate limit: Unlimited**

### Setup:
Create a GitHub Action that generates a package index JSON:

**.github/workflows/update-index.yml** (in package repo):
```yaml
name: Update Package Index
on:
  push:
    branches: [main]
    paths:
      - 'packages/**/*.hubpkg'
  workflow_dispatch:

jobs:
  update-index:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Generate package index
        run: |
          # Find all .hubpkg files
          find packages -name "*.hubpkg" -type f > package-list.txt
          
          # Create JSON index
          echo '{"packages":[' > packages.json
          first=true
          while IFS= read -r file; do
            if [ "$first" = false ]; then echo "," >> packages.json; fi
            first=false
            
            # Extract metadata and create JSON entry
            name=$(basename "$file" .hubpkg)
            path=$(dirname "$file")
            tags=$(echo "$path" | cut -d'/' -f2-)
            
            echo "{\"name\":\"$name\",\"path\":\"$file\",\"download_url\":\"https://raw.githubusercontent.com/R1CH4RD25/TheHub-Package-Repo/main/$file\",\"tags\":\"$tags\"}" >> packages.json
          done < package-list.txt
          echo ']}' >> packages.json
      
      - name: Commit index
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add packages.json
          git commit -m "Update package index" || exit 0
          git push
```

Then in `package-discovery.php`:
```php
// Fetch pre-built index instead of recursively searching
$indexUrl = "https://raw.githubusercontent.com/{$owner}/{$repo}/main/packages.json";
$response = file_get_contents($indexUrl);
$packages = json_decode($response, true)['packages'];
```

**Pros:**
- ✅ **1 API call instead of 10+** (90% reduction)
- ✅ Much faster
- ✅ Works with any rate limit
- ✅ Scales infinitely

**Cons:**
- ⚠️ Requires GitHub Action setup
- ⚠️ Index updates only on push

---

## 🎯 Recommended Approach

### Phase 1: Immediate (Today)
1. **Add GitHub Personal Access Token** → 5,000 requests/hour
2. **Implement caching** → Reduce API calls by 90%

### Phase 2: Optimization (This Week)
3. **Create package index** → 1 API call per search instead of 10+

### Phase 3: Scale (When Traffic Grows)
4. **Multiple GitHub tokens** → Rotate tokens for different users
5. **CDN caching** → Cache package index on Cloudflare

---

## Implementation Priority

```bash
# Quick wins (30 minutes):
1. Add GITHUB_API_TOKEN to .env
2. Update package-discovery.php to use token
3. Add cache layer to searchGitHubPackages()

# Medium-term (2 hours):
4. Create GitHub Action for package index
5. Update discovery to use index

# Long-term (as needed):
6. Implement token rotation
7. Add CDN/reverse proxy
```

---

## Expected Impact

| Solution | API Calls Saved | Implementation Time | Cost |
|----------|----------------|---------------------|------|
| Personal Token | +4,940/hour | 5 min | Free |
| Caching | 90% reduction | 15 min | Free |
| Package Index | 90% reduction | 1 hour | Free |
| GitHub App | +5,000/install | 3 hours | Free |
| CDN | 99% reduction | 2 hours | Free (Cloudflare) |

---

## Monitoring

Add rate limit tracking:
```php
// After each GitHub API call
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'X-RateLimit-Remaining:') !== false) {
            $remaining = (int)trim(explode(':', $header)[1]);
            error_log("GitHub API calls remaining: $remaining");
            
            if ($remaining < 10) {
                error_log("⚠️  WARNING: GitHub API rate limit almost exhausted!");
            }
        }
    }
}
```

---

**Bottom Line:** Add a GitHub token now (5 min), implement caching (15 min), and you'll handle 100x more traffic easily.
