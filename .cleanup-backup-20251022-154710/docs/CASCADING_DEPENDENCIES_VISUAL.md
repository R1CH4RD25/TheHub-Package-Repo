# 🌳 Cascading Dependencies - Visual Architecture

## Current Implementation

```
┌─────────────────────────────────────────────────────────────┐
│                   AUTHENTICATION & LOGIN                    │
│  (Top-Level Configuration)                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Authentication Methods:                                    │
│  ☑ Local User Accounts                                     │
│  ☑ Google OAuth ──────────────────┐                        │
│  ☐ Microsoft OAuth ──────────┐    │                        │
│                               │    │                        │
│  Access Restrictions:         │    │                        │
│  ☐ Require Domain Match       │    │                        │
│  📝 Allowed Domains           │    │                        │
│  🕐 Session Timeout            │    │                        │
└───────────────────────────────┼────┼────────────────────────┘
                                │    │
                ┌───────────────┘    └───────────────┐
                │                                    │
                ▼                                    ▼
┌──────────────────────────────┐  ┌──────────────────────────────┐
│  GOOGLE OAUTH & GROUPS       │  │ MICROSOFT OAUTH & GROUPS     │
│  (Level 1 Dependency)        │  │ (Level 1 Dependency)         │
├──────────────────────────────┤  ├──────────────────────────────┤
│  📝 Client ID                │  │ 📝 Client ID                 │
│  🔒 Client Secret            │  │ 🔒 Client Secret             │
│  🔗 Redirect URI             │  │ 🔗 Redirect URI              │
│                              │  │ 🏢 Tenant ID                 │
│  ☑ Enable Google Groups ──┐ │  │                              │
└────────────────────────────┼─┘  │ ☐ Enable Azure Groups ──┐   │
                             │    └────────────────────────┼───┘
                             │                             │
                             ▼                             ▼
        ┌────────────────────────────┐  ┌────────────────────────────┐
        │  GOOGLE GROUPS CONFIG      │  │  AZURE GROUPS CONFIG       │
        │  (Level 2 Dependency)      │  │  (Level 2 - Coming Soon)   │
        ├────────────────────────────┤  ├────────────────────────────┤
        │  📧 Admin Email            │  │  📧 Admin Email            │
        │  🔗 Group Associations     │  │  🔗 Group Associations     │
        │  📁 Service Account JSON   │  │  🔑 Service Principal      │
        └────────────────────────────┘  └────────────────────────────┘
```

---

## Future: Email Notifications System

```
┌─────────────────────────────────────────────────────────────┐
│               EMAIL CONFIGURATION (SMTP)                    │
│  (Existing - No dependencies)                               │
├─────────────────────────────────────────────────────────────┤
│  📧 SMTP Host                                               │
│  🔌 SMTP Port                                               │
│  👤 Username                                                │
│  🔒 Password                                                │
│  📬 From Email / Name                                       │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│               EMAIL NOTIFICATIONS                           │
│  (New Feature - Optional)                                   │
├─────────────────────────────────────────────────────────────┤
│  ☑ Enable Email Notifications ──────────────────┐          │
└──────────────────────────────────────────────────┼──────────┘
                                                   │
                                                   ▼
        ┌──────────────────────────────────────────────────────┐
        │  NOTIFICATION TYPES                                  │
        │  (Level 1 Dependency)                                │
        ├──────────────────────────────────────────────────────┤
        │  ☑ New User Registrations                           │
        │  ☑ Fuel Entry Alerts                                │
        │  ☑ Vehicle Maintenance Due                          │
        │  ☑ Low Fuel Warnings                                │
        │                                                      │
        │  ☑ Enable Email Digest ──────────────┐              │
        │  ☐ Enable Slack Notifications ───┐   │              │
        └───────────────────────────────────┼───┼──────────────┘
                                            │   │
                           ┌────────────────┘   └──────────────┐
                           │                                   │
                           ▼                                   ▼
        ┌────────────────────────────┐  ┌──────────────────────────────┐
        │  SLACK INTEGRATION         │  │  EMAIL DIGEST CONFIG         │
        │  (Level 2 Dependency)      │  │  (Level 2 Dependency)        │
        ├────────────────────────────┤  ├──────────────────────────────┤
        │  🔗 Webhook URL            │  │  🕐 Send Time (e.g., 8:00AM) │
        │  📝 Channel Name           │  │  ☑ Include Fuel Reports      │
        │  🎨 Custom Emoji           │  │  ☑ Include Vehicle Status    │
        │                            │  │  ☐ Include User Activity     │
        │  ☑ Enable Alerts ──┐       │  │                              │
        └────────────────────┼───────┘  │  ☑ Custom Templates ──┐      │
                             │          └──────────────────────┼───────┘
                             │                                 │
                             ▼                                 ▼
        ┌────────────────────────────┐  ┌──────────────────────────────┐
        │  SLACK ALERT CONFIG        │  │  EMAIL TEMPLATE EDITOR       │
        │  (Level 3 Dependency)      │  │  (Level 3 Dependency)        │
        ├────────────────────────────┤  ├──────────────────────────────┤
        │  ⚠️ Critical Events        │  │  📧 Header Template          │
        │  🔥 High Priority          │  │  📝 Body Template            │
        │  📢 General Updates        │  │  📎 Footer Template          │
        └────────────────────────────┘  │  🎨 CSS Styling              │
                                        └──────────────────────────────┘
```

---

## Future: API & Webhooks System

```
┌─────────────────────────────────────────────────────────────┐
│                    API ACCESS                               │
├─────────────────────────────────────────────────────────────┤
│  ☑ Enable API Access ────────────────────────────┐          │
└──────────────────────────────────────────────────┼──────────┘
                                                   │
                                                   ▼
        ┌──────────────────────────────────────────────────────┐
        │  API CONFIGURATION                                   │
        │  (Level 1 Dependency)                                │
        ├──────────────────────────────────────────────────────┤
        │  🔑 API Keys:                                        │
        │      [Generate New Key] [Revoke Key]                 │
        │  📊 Rate Limiting:                                   │
        │      🔢 Requests per minute: [100]                   │
        │      🔢 Requests per hour: [1000]                    │
        │                                                      │
        │  ☑ Enable Webhooks ──────────────────┐              │
        │  ☐ Enable GraphQL API ───────────┐   │              │
        └───────────────────────────────────┼───┼──────────────┘
                                            │   │
                           ┌────────────────┘   └──────────────┐
                           │                                   │
                           ▼                                   ▼
        ┌────────────────────────────┐  ┌──────────────────────────────┐
        │  GRAPHQL CONFIG            │  │  WEBHOOKS CONFIG             │
        │  (Level 2 Dependency)      │  │  (Level 2 Dependency)        │
        ├────────────────────────────┤  ├──────────────────────────────┤
        │  🔍 Query Depth Limit      │  │  📝 Webhook URLs:            │
        │  📈 Complexity Limit       │  │      • Fuel Entry: [URL]     │
        │  ☑ Enable Introspection    │  │      • Vehicle Update: [URL] │
        │  ☐ Require Authentication  │  │                              │
        └────────────────────────────┘  │  ☑ Event Filters ─────┐      │
                                        └──────────────────────┼───────┘
                                                               │
                                                               ▼
                                        ┌──────────────────────────────┐
                                        │  WEBHOOK FILTERS             │
                                        │  (Level 3 Dependency)        │
                                        ├──────────────────────────────┤
                                        │  ☑ Fuel entries > $50        │
                                        │  ☑ Vehicle status: critical  │
                                        │  ☐ User role: admin+         │
                                        │  📝 Custom JSON filter        │
                                        │                              │
                                        │  ☑ Retry Policy ──────┐      │
                                        └───────────────────────┼──────┘
                                                                │
                                                                ▼
                                        ┌──────────────────────────────┐
                                        │  RETRY CONFIGURATION         │
                                        │  (Level 4 Dependency)        │
                                        ├──────────────────────────────┤
                                        │  🔄 Max Retries: [3]         │
                                        │  ⏱️ Backoff: [Exponential]   │
                                        │  🕐 Timeout: [30s]           │
                                        └──────────────────────────────┘
```

---

## Future: Backup & Export System

```
┌─────────────────────────────────────────────────────────────┐
│              BACKUP & EXPORT                                │
├─────────────────────────────────────────────────────────────┤
│  ☑ Enable Automated Backups ──────────────────┐             │
└────────────────────────────────────────────────┼────────────┘
                                                 │
                                                 ▼
        ┌──────────────────────────────────────────────────────┐
        │  BACKUP CONFIGURATION                                │
        │  (Level 1 Dependency)                                │
        ├──────────────────────────────────────────────────────┤
        │  📅 Schedule:                                        │
        │      ⭕ Daily   ⭕ Weekly   ⭕ Monthly                │
        │  🕐 Backup Time: [02:00 AM]                          │
        │                                                      │
        │  📦 What to backup:                                  │
        │      ☑ Database                                      │
        │      ☑ Uploaded Files                                │
        │      ☐ Configuration Files                           │
        │                                                      │
        │  ☑ Retention Policy ──────────────┐                 │
        │  ☑ Cloud Storage ─────────────┐   │                 │
        └────────────────────────────────┼───┼─────────────────┘
                                         │   │
                        ┌────────────────┘   └──────────────┐
                        │                                   │
                        ▼                                   ▼
        ┌────────────────────────────┐  ┌──────────────────────────────┐
        │  CLOUD STORAGE             │  │  RETENTION POLICY            │
        │  (Level 2 Dependency)      │  │  (Level 2 Dependency)        │
        ├────────────────────────────┤  ├──────────────────────────────┤
        │  ☑ AWS S3 ─────────┐       │  │  🗓️ Keep Daily: [7 days]     │
        │  ☐ Google Cloud    │       │  │  🗓️ Keep Weekly: [4 weeks]   │
        │  ☐ Azure Blob      │       │  │  🗓️ Keep Monthly: [12 months]│
        └────────────────────┼───────┘  │  ☑ Auto-Delete Old ──┐       │
                             │          └──────────────────────┼───────┘
                             ▼                                 │
        ┌────────────────────────────┐                         ▼
        │  AWS S3 CONFIG             │  ┌──────────────────────────────┐
        │  (Level 3 Dependency)      │  │  DELETE CONFIRMATION         │
        ├────────────────────────────┤  │  (Level 3 Dependency)        │
        │  🔑 Access Key ID          │  ├──────────────────────────────┤
        │  🔒 Secret Access Key      │  │  ⚠️ Confirm auto-deletion    │
        │  🪣 Bucket Name            │  │  📧 Email notifications       │
        │  🌍 Region                 │  │  📊 Deletion log             │
        │  🔐 Encryption ─────┐       │  └──────────────────────────────┘
        └────────────────────┼───────┘
                             ▼
        ┌────────────────────────────┐
        │  ENCRYPTION OPTIONS        │
        │  (Level 4 Dependency)      │
        ├────────────────────────────┤
        │  ⭕ AES-256                │
        │  ⭕ AWS KMS                │
        │  🔑 Encryption Key         │
        └────────────────────────────┘
```

---

## Module-Specific Example: Fuel Management

```
┌─────────────────────────────────────────────────────────────┐
│              FUEL MANAGEMENT MODULE                         │
├─────────────────────────────────────────────────────────────┤
│  ☑ Enable Fuel Management Module ──────────────┐            │
└─────────────────────────────────────────────────┼───────────┘
                                                  │
                                                  ▼
        ┌──────────────────────────────────────────────────────┐
        │  FUEL MODULE SETTINGS                                │
        │  (Level 1 Dependency)                                │
        ├──────────────────────────────────────────────────────┤
        │  ⚙️ General:                                         │
        │      ☑ Require Vehicle Odometer                      │
        │      ☑ Validate Fuel Capacity                        │
        │      🔢 Max Gallons per Entry: [50]                  │
        │                                                      │
        │  ☑ Fuel Alerts ──────────────────┐                  │
        │  ☑ Fuel Reports ─────────────┐   │                  │
        │  ☑ Integration ──────────┐   │   │                  │
        └──────────────────────────┼───┼───┼──────────────────┘
                                   │   │   │
                  ┌────────────────┘   │   └──────────────┐
                  │                    │                   │
                  ▼                    ▼                   ▼
┌──────────────────────┐ ┌──────────────────┐ ┌────────────────────────┐
│  INTEGRATIONS        │ │  FUEL REPORTS    │ │  FUEL ALERTS           │
│  (Level 2)           │ │  (Level 2)       │ │  (Level 2)             │
├──────────────────────┤ ├──────────────────┤ ├────────────────────────┤
│  ☑ Fleet Card API    │ │  📊 Daily        │ │  ⚠️ Low Fuel Warning   │
│  ☐ Fuel Price API    │ │  📊 Weekly       │ │  🔥 High Usage Alert   │
│  ☐ Export to Excel   │ │  📊 Monthly      │ │  💰 Cost Threshold     │
└──────────────────────┘ │  ☑ Custom ──┐    │ │  ⏰ Frequency: [Daily] │
                         └─────────────┼────┘ └────────────────────────┘
                                       │
                                       ▼
                         ┌──────────────────────┐
                         │  CUSTOM REPORTS      │
                         │  (Level 3)           │
                         ├──────────────────────┤
                         │  📅 Date Range       │
                         │  🚗 Filter: Vehicles │
                         │  👤 Filter: Drivers  │
                         │  💰 Filter: Cost     │
                         └──────────────────────┘
```

---

## Key Patterns

### 🎯 Pattern 1: Simple Parent-Child
```
Parent Feature ──→ Child Settings
```
Example: Google OAuth → OAuth Config

### 🎯 Pattern 2: Multi-Child
```
        ┌──→ Child A
Parent ──┼──→ Child B
        └──→ Child C
```
Example: Email Notifications → [New Users, Fuel, Vehicles]

### 🎯 Pattern 3: Deep Nesting
```
Level 1 ──→ Level 2 ──→ Level 3 ──→ Level 4
```
Example: Backups → Cloud → AWS → Encryption

### 🎯 Pattern 4: Conditional (AND)
```
Parent A ──┐
           ├──→ Child (requires both)
Parent B ──┘
```
Example: API + Webhooks → Advanced Filtering

---

## 🎨 Visual Design Language

### Level 1 Dependencies
```css
padding-left: 2rem;
border-left: 3px solid var(--primary-color);
```

### Level 2 Dependencies
```css
padding-left: 2rem;
border-left: 3px solid var(--secondary-color);
```

### Level 3+ Dependencies
```css
padding-left: 2rem;
border-left: 3px solid var(--accent-color);
```

### Disabled State
```css
opacity: 0.6;
cursor: not-allowed;
background: var(--bg-muted);
```

---

## 📊 Complexity Limits

**Recommended Maximums:**
- **Depth**: 4-5 levels (deeper gets confusing)
- **Width**: 10 children per parent (more needs grouping)
- **Total**: 50-100 total options per section

**When to Break Apart:**
- If a section has >5 levels deep → Create separate module
- If a parent has >10 children → Group into subsections
- If users need >3 minutes to understand → Simplify

---

## ✅ Implementation Checklist

For each new cascading dependency:

- [ ] HTML: Add checkbox with `onchange` handler
- [ ] HTML: Add container with unique ID and `display: none`
- [ ] HTML: Apply visual hierarchy (indent + border)
- [ ] JS: Register in `initializeDependencies()`
- [ ] JS: Initialize in `populateAdvancedSettings()`
- [ ] JS: Gather in `gatherAdvancedSettings()`
- [ ] Backend: Map to `.env` variable
- [ ] Backend: Add to `system-config.php`
- [ ] Test: Show/hide works
- [ ] Test: Cascade disable works
- [ ] Test: Settings persist
- [ ] Docs: Update if adding new pattern

---

**Ready to go big time! 🚀**
