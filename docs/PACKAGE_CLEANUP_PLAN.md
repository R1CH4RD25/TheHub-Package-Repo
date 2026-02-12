# Package Cleanup & Migration Plan

**Date**: February 11, 2026  
**Purpose**: Remove Layer 1/2 packages before implementing Layer 3 architecture  
**Authority**: PACKAGE_ARCHITECTURE_SPEC.md v1.0.0-draft

---

## Current Package Inventory

| Package ID | Version | Format | Status | Installed | Action |
|------------|---------|--------|--------|-----------|--------|
| com.woodson.vehicle-maintenance | 2.1.0 | Layer 2 | Installed | 2026-01-15 | **Remove** then rebuild as Layer 3 |
| com.woodson.vehicle-request-form | 1.0.0 | Layer 1 | Not installed | N/A | **Delete** (never installed) |
| com.woodson.bullying-report | 1.0.0 | Layer 1 | Not installed | N/A | **Delete** (never installed) |

**Total packages**: 3  
**Layer 3 compliant**: 0  
**Requiring cleanup**: 3

---

## Why Cleanup Is Necessary

### Layer 1/2 packages DO NOT have:
- ❌ `pages[]` with component definitions
- ❌ `queries{}` with handler contracts
- ❌ `mutations{}` with audit events
- ❌ `policies{}` with RBAC + scope + rate limits
- ❌ `handlers{}` registry with interface compliance
- ❌ `compatibility{}` declarations
- ❌ Package signing/verification

### What they DO have (incompatible):
- Layer 1: `fields[]` array (ad-hoc form definitions)
- Layer 2: `entities[]` + `database.migrations` (partial structure)

**Result**: Cannot enforce sections 11-20 of Layer 3 spec without complete rebuild.

---

## Cleanup Strategy

### Option A: Clean Slate (Recommended)
**Fastest path to Layer 3 compliance**

1. **Backup existing package data** (JSON + database tables)
2. **Remove all packages** from section_packages, section_installations
3. **Drop package-specific tables** (vm_fuel_logs, vm_maintenance_events, etc.)
4. **Reset section definitions** (created by packages)
5. **Start fresh** with Layer 3 Student Directory pilot

**Pros**:
- No migration complexity
- No legacy compatibility needed
- Clean validation of Layer 3 from scratch

**Cons**:
- Loses vehicle-maintenance data (if production data exists)
- Requires rebuild of vehicle-maintenance as Layer 3

---

### Option B: Preserve & Migrate
**Safer if production data exists**

1. **Export vehicle-maintenance data** (fuel logs, maintenance events)
2. **Snapshot database schema** for vm_* tables
3. **Remove packages** from system
4. **Build Layer 3 vehicle-maintenance** with same entities
5. **Re-import data** after Layer 3 install
6. **Validate data integrity**

**Pros**:
- Preserves production data
- Tests Layer 3 migration path

**Cons**:
- More complex (2-3 days extra work)
- Delays Layer 3 pilot

---

## Recommended Action: Option A (Clean Slate)

**Rationale**:
- System is not live yet (no production data loss risk)
- Faster time to Layer 3 pilot
- Vehicle-maintenance will be rebuilt better with Layer 3 patterns
- Student Directory is better first pilot (clear security requirements)

---

## Cleanup Execution Plan

### Phase 1: Backup (Safety Net)
```bash
# Backup package JSON
php cli/backup-packages.php

# Backup database tables
mysqldump woodson_hub vm_fuel_logs vm_maintenance_events > /tmp/vehicle_maintenance_backup.sql

# Backup section definitions
mysqldump woodson_hub sections section_packages section_installations section_field_definitions > /tmp/sections_backup.sql
```

### Phase 2: Data Audit
```bash
# Check if any real data exists
php -r "
require 'src/bootstrap.php';
\$db = Hub\Database::getInstance();

echo \"Fuel logs: \" . \$db->query('SELECT COUNT(*) as c FROM vm_fuel_logs')->fetch()['c'] . \"\n\";
echo \"Maintenance events: \" . \$db->query('SELECT COUNT(*) as c FROM vm_maintenance_events')->fetch()['c'] . \"\n\";
"
```

**Decision point**: If counts > 0, consider Option B. If 0, proceed with Option A.

### Phase 3: Clean Removal
```bash
# Run automated cleanup script
php cli/cleanup-layer1-layer2-packages.php
```

### Phase 4: Verification
```bash
# Verify clean state
php -r "
require 'src/bootstrap.php';
\$db = Hub\Database::getInstance();

\$packages = \$db->query('SELECT COUNT(*) as c FROM section_packages')->fetch()['c'];
\$sections = \$db->query('SELECT COUNT(*) as c FROM sections WHERE slug LIKE \"com.%\"')->fetch()['c'];

echo \"Packages remaining: \$packages (should be 0)\n\";
echo \"Package sections remaining: \$sections (should be 0)\n\";

if (\$packages == 0 && \$sections == 0) {
    echo \"\n✅ CLEAN SLATE ACHIEVED\n\";
} else {
    echo \"\n⚠️  CLEANUP INCOMPLETE\n\";
}
"
```

---

## Post-Cleanup Migration Path

### After cleanup, rebuild as Layer 3:

1. **Student Directory** (new, Layer 3 pilot)
   - Use as reference implementation
   - Full pages/queries/mutations/policies
   - Password reveal pattern (token-based)
   - Timeline: Sprint 4 (Week 7-8)

2. **Vehicle Maintenance** (rebuild from Layer 2)
   - Convert entities → pages + queries + mutations
   - Add policies for fuel log entry, approval
   - Add handler registry
   - Timeline: Sprint 5 (Week 9-10)

3. **Vehicle Request Form** (rebuild from Layer 1)
   - Convert fields → form component JSON
   - Add submission mutation
   - Add approval workflow
   - Timeline: Sprint 6+ (post-pilot)

4. **Bullying Report** (rebuild from Layer 1)
   - Sensitive data handling (similar to student directory)
   - Reporting workflow
   - Timeline: Sprint 6+ (post-pilot)

---

## Cleanup Script

### Create: `cli/cleanup-layer1-layer2-packages.php`

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../src/bootstrap.php';

use Hub\Database;
use Hub\AuditLogger;

$db = Database::getInstance();

echo "=== LAYER 1/2 PACKAGE CLEANUP ===\n\n";

// Safety check
echo "This will remove ALL existing packages (Layer 1 and Layer 2).\n";
echo "Are you sure? Type 'YES' to confirm: ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 'YES') {
    echo "Aborted.\n";
    exit(1);
}

// Get packages to remove
$packages = $db->query('SELECT package_id FROM section_packages')->fetchAll(PDO::FETCH_COLUMN);

echo "\nPackages to remove: " . count($packages) . "\n";
foreach ($packages as $pkg) {
    echo "  - $pkg\n";
}

echo "\nStarting cleanup...\n\n";

// 1. Remove package installations
echo "[1/5] Removing package installations...\n";
$db->query('DELETE FROM section_installations');
echo "  ✓ section_installations cleared\n";

// 2. Remove package definitions
echo "[2/5] Removing package definitions...\n";
$db->query('DELETE FROM section_packages');
echo "  ✓ section_packages cleared\n";

// 3. Remove field definitions
echo "[3/5] Removing field definitions...\n";
$db->query('DELETE FROM section_field_definitions WHERE section_id IN (
    SELECT id FROM sections WHERE slug LIKE "com.%"
)');
echo "  ✓ section_field_definitions cleared\n";

// 4. Remove sections created by packages
echo "[4/5] Removing package sections...\n";
$db->query('DELETE FROM section_role_access WHERE section_id IN (
    SELECT id FROM sections WHERE slug LIKE "com.%"
)');
$db->query('DELETE FROM sections WHERE slug LIKE "com.%"');
echo "  ✓ Package sections removed\n";

// 5. Drop vehicle-maintenance tables (if exist)
echo "[5/5] Dropping package-specific tables...\n";
try {
    $db->query('DROP TABLE IF EXISTS vm_fuel_logs');
    echo "  ✓ vm_fuel_logs dropped\n";
} catch (Exception $e) {
    echo "  - vm_fuel_logs not found (OK)\n";
}

try {
    $db->query('DROP TABLE IF EXISTS vm_maintenance_events');
    echo "  ✓ vm_maintenance_events dropped\n";
} catch (Exception $e) {
    echo "  - vm_maintenance_events not found (OK)\n";
}

// Audit the cleanup
AuditLogger::log('system.packages_cleanup', [
    'packages_removed' => $packages,
    'reason' => 'Layer 3 migration - removing Layer 1/2 packages',
    'actor_id' => 1, // System
    'timestamp' => date('Y-m-d H:i:s')
]);

echo "\n✅ CLEANUP COMPLETE\n\n";
echo "Next steps:\n";
echo "1. Verify clean state: php -r \"require 'src/bootstrap.php'; ...\"\n";
echo "2. Begin Sprint 0: Platform Contract implementation\n";
echo "3. Build Student Directory as Layer 3 pilot\n";
```

---

## Verification Checklist

After cleanup, verify:

- [ ] `section_packages` table empty
- [ ] `section_installations` table empty
- [ ] `section_field_definitions` contains only core Hub fields
- [ ] `sections` table contains no `com.*` slugs
- [ ] `vm_fuel_logs` table does not exist
- [ ] `vm_maintenance_events` table does not exist
- [ ] Audit log shows cleanup event
- [ ] Backups exist at `/tmp/*_backup.sql`

---

## Rollback Plan (If Needed)

If cleanup causes issues:

```bash
# Restore from backups
mysql woodson_hub < /tmp/sections_backup.sql
mysql woodson_hub < /tmp/vehicle_maintenance_backup.sql

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

---

## Timeline

- **Now**: Review cleanup plan with team
- **Today**: Execute cleanup (15 minutes)
- **Today**: Verify clean state (5 minutes)
- **Tomorrow**: Start Sprint 0 (platform contract)
- **Week 7-8**: Student Directory pilot (first Layer 3 package)
- **Week 9-10**: Vehicle Maintenance rebuild (Layer 3)

---

## Success Criteria

Cleanup is successful when:

- ✅ Zero packages in `section_packages`
- ✅ Zero package-created sections
- ✅ Zero package-specific database tables
- ✅ Clean audit trail
- ✅ Team ready to start Layer 3 implementation

---

**Document Version**: 1.0  
**Last Updated**: February 11, 2026  
**Next Review**: After cleanup execution
