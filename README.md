# The Hub Package Repository

Official package repository for The Hub dynamic sections system. This repository contains validated `.hubpkg` packages that extend The Hub with custom forms, reporting tools, and workflows.

## 📦 Available Packages

| Package | Version | Description | Download |
|---------|---------|-------------|----------|
| **Bullying Report** | 1.0.0 | Anonymous bullying incident reporting system with comprehensive tracking | [Download](packages/reporting/bullying-report/bullying-report_1.0.0.hubpkg) |

## 🚀 Quick Start

### Installing a Package

1. Download the `.hubpkg` file from this repository
2. Log in to The Hub as an administrator
3. Navigate to **Admin** → **Package Manager**
4. Click **Upload Package** and select the `.hubpkg` file
5. Click **Validate Package** to run compatibility checks
6. Review the validation report
7. Click **Install** if all checks pass
8. Configure section access in **Admin** → **Sections**

### Package Format

The Hub packages use the `.hubpkg` format - a JSON-based package format containing:

- **Metadata**: Package ID, version, author, dependencies
- **Field Definitions**: Custom form fields with types and validation rules
- **Permissions**: Required user roles and capabilities
- **Menu Items**: Navigation configuration
- **Compatibility Checks**: System requirements and version constraints

## 📋 Package Specification

### Semantic Versioning

All packages follow [Semantic Versioning 2.0.0](https://semver.org/):

- **MAJOR** version: Incompatible API changes
- **MINOR** version: New functionality (backwards-compatible)
- **PATCH** version: Bug fixes (backwards-compatible)

### Required Fields

```json
{
  "package_id": "unique-package-id",
  "display_name": "Human Readable Name",
  "version": "1.0.0",
  "author": "Author Name",
  "description": "Package description",
  "hub_version_min": "1.0.0",
  "dependencies": [],
  "conflicts": [],
  "fields": [...],
  "permissions": {...},
  "menu_items": [...]
}
```

## 🔒 Security & Validation

All packages in this repository have passed The Hub's comprehensive validation system:

- ✅ **Structure Validation**: Required fields, format compliance
- ✅ **System Requirements**: PHP version, MySQL version, extensions
- ✅ **Dependency Resolution**: Required packages, conflict detection
- ✅ **Field Validation**: Data types, constraints, validation rules
- ✅ **Permission Analysis**: Role requirements, access controls
- ✅ **Security Scan**: SQL injection, XSS, path traversal checks
- ✅ **Database Impact**: Schema changes, migration safety
- ✅ **Menu Integration**: Navigation structure, conflicts
- ✅ **Version Compatibility**: Hub version requirements
- ✅ **Performance Impact**: Query complexity, resource usage

## 🛠️ Creating Your Own Package

### Package Structure Template

```json
{
  "package_id": "your-package-id",
  "display_name": "Your Package Name",
  "version": "1.0.0",
  "author": "Your Name",
  "description": "What your package does",
  "hub_version_min": "1.0.0",
  "hub_version_max": null,
  "dependencies": [],
  "conflicts": [],
  "fields": [
    {
      "field_name": "example_field",
      "label": "Example Field",
      "type": "text",
      "required": true,
      "validation_rules": {
        "max_length": 255
      },
      "help_text": "Helper text for users"
    }
  ],
  "permissions": {
    "view": ["user", "admin"],
    "create": ["admin"],
    "edit": ["admin"],
    "delete": ["admin"]
  },
  "menu_items": [
    {
      "label": "Your Section",
      "icon": "bi-icon-name",
      "order": 10
    }
  ]
}
```

### Field Types

Supported field types:
- `text` - Single-line text input
- `textarea` - Multi-line text input
- `number` - Numeric input
- `email` - Email address with validation
- `date` - Date picker
- `datetime` - Date and time picker
- `select` - Dropdown selection
- `multiselect` - Multiple selection
- `checkbox` - Boolean checkbox
- `radio` - Radio button group
- `file` - File upload

### Validation Rules

Available validation rules:
- `required`: Boolean - Field must have a value
- `min_length`: Integer - Minimum string length
- `max_length`: Integer - Maximum string length
- `min_value`: Number - Minimum numeric value
- `max_value`: Number - Maximum numeric value
- `pattern`: Regex - Custom validation pattern
- `allowed_extensions`: Array - File type restrictions
- `max_file_size`: Integer - File size limit in bytes

### Submit Your Package

To submit a package to this repository:

1. Create your `.hubpkg` file following the specification above
2. Test thoroughly in your Hub instance
3. Fork this repository
4. Add your package to the appropriate category folder
5. Update the "Available Packages" table in this README
6. Submit a pull request with:
   - Package file
   - Screenshot(s) of the section in action
   - Installation/configuration notes
   - Any special requirements

## 📖 Documentation

- [Package System Build Documentation](https://github.com/yourusername/thehub/blob/main/docs/PACKAGE_SYSTEM_BUILD_COMPLETE.md)
- [Dynamic Sections Roadmap](https://github.com/yourusername/thehub/blob/main/docs/DYNAMIC_SECTIONS_ROADMAP.md)
- [Modular Architecture](https://github.com/yourusername/thehub/blob/main/docs/MODULAR_ARCHITECTURE.md)

## 🤝 Contributing

Contributions are welcome! Please read our contributing guidelines before submitting packages.

### Package Review Criteria

Submitted packages will be reviewed for:
- Code quality and security
- Documentation completeness
- User experience design
- Compatibility with latest Hub version
- Test coverage (if applicable)

## 📜 License

This repository is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Individual packages may have their own licenses - check package documentation.

## 🏫 About The Hub

The Hub is a modular web application built for Woodson ISD to manage various administrative functions including vehicle management, fuel tracking, and custom dynamic sections for reporting and data collection.

**System Requirements:**
- PHP 8.2+
- MySQL 10.11+
- Apache/Nginx with mod_rewrite

## 📞 Support

For issues, questions, or feature requests:
- Open an issue in this repository
- Contact The Hub administrators at your institution

## 🔄 Version History

### Repository v1.0.0 (October 2025)
- Initial repository setup
- Package validation system
- Bullying Report v1.0.0 package

---

**Maintained by Woodson ISD Technology Department**
