# GitHub API Token Setup (5 Minutes)

## Why You Need This
- **Without token**: 60 API requests/hour (unauthenticated)
- **With token**: 5,000 API requests/hour (authenticated)
- **With caching**: ~450 package searches per hour

## Quick Setup

### Step 1: Generate GitHub Token (2 minutes)
1. Go to: **https://github.com/settings/tokens**
2. Click **"Generate new token (classic)"**
3. Name it: `TheHub Package Discovery`
4. Select **ONLY** this scope:
   - ✅ `public_repo` - Access public repositories (read-only)
5. Set expiration: **No expiration** or **1 year**
6. Click **"Generate token"**
7. **Copy the token** (starts with `ghp_`)

⚠️ **Important**: You can only see the token once! Copy it now.

### Step 2: Add Token to .env (1 minute)
```bash
# Edit your .env file
nano /var/www/woodson/thehub/.env

# Add this line (replace with your actual token):
GITHUB_API_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Save and exit (Ctrl+X, Y, Enter)
```

### Step 3: Done! ✅
That's it! The system will automatically:
- ✅ Use the token for all GitHub API requests
- ✅ Get 5,000 requests/hour instead of 60
- ✅ Cache results for 1 hour (90% reduction in API calls)
- ✅ Monitor rate limits and warn when low

## Verify It's Working

Check your logs after browsing packages:
```bash
tail -f /var/www/woodson/thehub/logs/php-errors.log | grep "GitHub API"
```

You should see:
```
GitHub API calls remaining: 4999  # High number = token is working!
Package discovery: Serving 1 packages from cache  # Caching is working!
```

## What If I Don't Add a Token?

The system still works! It will:
- ✅ Fall back to unauthenticated API calls (60/hour)
- ✅ Still use caching to reduce API usage
- ✅ Show warnings when rate limit is low

**But:** You'll be limited to ~5 package searches per hour without caching, or ~54 with caching.

## Security Notes

✅ **Safe to use**:
- Token only has `public_repo` access (read-only)
- Cannot modify any repositories
- Cannot access private repositories
- Cannot access your account settings

✅ **Best practices**:
- Keep token in `.env` (never commit to git)
- `.env` is already in `.gitignore`
- Rotate token yearly for security

## Troubleshooting

**"GitHub API calls remaining: 0"**
- You've hit the rate limit
- Wait 1 hour for reset, or add a token
- Caching helps prevent this

**"GitHub API request failed"**
- Check internet connection
- Verify token is valid (regenerate if needed)
- Check token has `public_repo` scope

**Cache not working?**
- Redis connection issues are normal (falls back to file cache)
- Cache files stored in `sessions/cache/` directory
- Automatically cleared after 1 hour

## Advanced: Multiple Tokens (Future Scaling)

When you have **high traffic**, you can:
1. Create multiple GitHub tokens (different accounts)
2. Rotate tokens per request
3. Get 5,000 requests/hour × number of tokens

But with caching, **one token handles 450+ searches/hour** easily!
