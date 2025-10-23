# Changelog - Bullying Report Package

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-22

### Added
- Initial release of Bullying Report package
- Anonymous submission capability
- Comprehensive incident tracking fields
- Evidence upload support (images, videos, documents)
- Severity level categorization (Low, Medium, High, Critical)
- Administrative workflow (Submitted → Under Review → Investigation → Resolved)
- Witness tracking capability
- Audit logging for all actions
- Role-based access control (Admin, Counselor, User)
- Email notification system for new reports
- Follow-up requirement tracking

### Security
- All file uploads validated for type and size
- XSS protection on all text fields
- SQL injection prevention through prepared statements
- Anonymous submissions do not store identifying information
- Access logs for all administrative actions

### Compatibility
- Requires The Hub v1.0.0 or higher
- PHP 8.2+ required
- MySQL 10.11+ required
- Bootstrap Icons for UI elements
