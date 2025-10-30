# Employee Evaluation

Brief description of what this package does (2-3 sentences).

## Features

- Feature 1
- Feature 2
- Feature 3

## Requirements

- **Hub Version**: >= 1.0.0
- **PHP**: >= 8.0
- **MySQL**: >= 5.7

## Installation

1. Download `employee-evaluation_1.0.0.hubpkg`
2. Navigate to **Admin → Package Manager**
3. Click **"Upload Package"**
4. Select the `.hubpkg` file
5. Review the compatibility report
6. Click **"Install"**

## Configuration

### Initial Setup

1. Go to **Sections → Section Access**
2. Grant appropriate roles:
   - `emp_view` → Staff, Teachers
   - `emp_manage` → Department Heads
   - `emp_admin` → Administrators

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | Text | Yes | Short title or summary |
| `description` | Textarea | No | Detailed description |
| `status` | Select | Yes | Current status (draft, submitted, etc.) |

## Usage

### For End Users

1. Navigate to the package from the main menu
2. Click "Submit Entry" to create a new record
3. Fill in the required fields
4. Click "Submit"

### For Administrators

1. Navigate to "View Records" to see all submissions
2. Use filters to find specific records
3. Click on a record to view details
4. Use actions to edit or update status

## Permissions

| Role | Access Level | Description |
|------|--------------|-------------|
| `emp_view` | Read-only | Can view records |
| `emp_manage` | Create & Edit | Can submit and edit records |
| `emp_admin` | Full Control | All permissions including delete |

## Screenshots

*Add screenshots here after taking them*

![Main View](screenshots/main-view.png)
![Admin Panel](screenshots/admin-view.png)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Support

- **Issues**: [GitHub Issues](https://github.com/your-org/your-repo/issues)
- **Email**: tech@woodsonisd.net
- **Documentation**: [Package Specification](../../docs/PACKAGE_SPECIFICATION_V2.md)

## License

MIT License - See [LICENSE](LICENSE) for details.

## Credits

Developed by Woodson ISD