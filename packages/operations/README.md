# Operations Packages

Packages for school district operations, facilities management, fleet tracking, and maintenance systems.

## Directory Structure

```
operations/
├── fleet/              - Vehicle and fleet management
├── facilities/         - Building and facilities maintenance (future)
├── inventory/          - Equipment and inventory tracking (future)
└── procurement/        - Purchasing and procurement (future)
```

## Current Packages

### Fleet Management (operations/fleet/)
- **[vehicle-maintenance](fleet/vehicle-maintenance/)** `v1.0.0` - Comprehensive fleet tracking, fuel logging, and maintenance scheduling

## Planned Packages

### Facilities
- Building maintenance requests
- Work order management
- Room scheduling
- Key management

### Inventory
- Equipment tracking
- Asset management
- Supply ordering
- Barcode/QR scanning

### Procurement
- Purchase requisitions
- Vendor management
- Budget tracking
- Approval workflows

## Package Organization

Packages here follow multi-dimensional tagging:
- First tag: `operations` (department)
- Second tag: Subcategory (e.g., `fleet`, `facilities`, `inventory`)

**Example:**
- `operations/fleet/vehicle-maintenance` → Tags: `['operations', 'fleet', 'vehicles', 'maintenance']`
- `operations/facilities/work-orders` → Tags: `['operations', 'facilities', 'maintenance']`

This allows filtering by:
- All operations tools: Filter by `operations`
- All fleet tools: Filter by `fleet`
- Operations fleet tools: Filter by both `operations` AND `fleet`

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for guidelines on submitting operations packages.
