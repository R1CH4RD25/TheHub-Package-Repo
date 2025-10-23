# Bullying Report Package

Anonymous bullying incident reporting system with comprehensive tracking and follow-up workflow.

## Version
1.0.0 (October 2025)

## Description
The Bullying Report package provides a secure, anonymous reporting system for students, parents, and staff to report bullying incidents. Features comprehensive tracking, administrative follow-up, and audit logging.

## Features
- ✅ Anonymous submission option
- ✅ Incident categorization and severity levels
- ✅ Location and witness tracking
- ✅ Photo/video evidence upload
- ✅ Administrative follow-up workflow
- ✅ Status tracking (Submitted → Under Review → Resolved)
- ✅ Audit logging for all actions

## System Requirements
- The Hub v1.0.0 or higher
- PHP 8.2+
- MySQL 10.11+

## Installation
1. Download `bullying-report_1.0.0.hubpkg`
2. Upload to The Hub via Admin → Package Manager
3. Run validation (all checks must pass)
4. Install package
5. Configure section access in Admin → Sections

## Permissions
- **View Reports**: Administrators, Counselors
- **Create Reports**: All authenticated users + anonymous
- **Edit Reports**: Administrators only
- **Delete Reports**: Super Administrators only

## Fields

### Reporter Information
- **reporter_type**: Select (Student, Parent, Staff, Anonymous)
- **reporter_name**: Text (optional if anonymous)
- **reporter_email**: Email (optional)
- **reporter_phone**: Text (optional)

### Incident Details
- **incident_date**: Date (required)
- **incident_time**: Time
- **incident_location**: Text (required)
- **incident_description**: Textarea (required, max 2000 chars)
- **severity_level**: Select (Low, Medium, High, Critical)

### Involved Parties
- **victim_name**: Text (required)
- **victim_grade**: Select (K-12)
- **bully_name**: Text (if known)
- **bully_grade**: Select (K-12)
- **witnesses**: Textarea (list of witnesses)

### Evidence
- **evidence_file**: File upload (images, videos, documents)
- **additional_notes**: Textarea

### Administrative Actions
- **status**: Select (Submitted, Under Review, Investigation, Resolved)
- **assigned_to**: Text (administrator name)
- **action_taken**: Textarea
- **resolution_date**: Date
- **follow_up_required**: Checkbox

## Screenshots

### Report Submission Form
![Submission Form](screenshots/form-view.png)

### Reports List View
![List View](screenshots/list-view.png)

### Administrative Actions
![Admin View](screenshots/admin-view.png)

## Configuration

### Access Control
By default, the following roles can access this section:
- Super Admin: Full access
- Admin: Full access
- Counselor: View and create only

Configure additional access in Admin → Sections → Bullying Reports.

### Email Notifications
Configure email notifications for new reports in Site Settings.

## Changelog

### v1.0.0 (October 2025)
- Initial release
- Anonymous reporting capability
- Complete incident tracking workflow
- Evidence upload support
- Administrative follow-up system

## Support
For issues or questions, please open an issue in the main package repository.

## License
MIT License - see repository LICENSE file for details.
